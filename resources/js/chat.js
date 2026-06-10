import { renderMarkdown } from './markdown.js';
import { initAttachments, getStagedFiles, clearStaged } from './attachments.js';

const $ = (sel, root = document) => root.querySelector(sel);
const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));

const csrf = $('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

// Server-provided locale, extension flags and translated strings.
const LM = window.LocalMind ?? { ext: {}, i18n: {} };
const T = LM.i18n ?? {};
const EXT = LM.ext ?? {};
const isOn = (name) => Boolean(EXT?.[name]?.enabled);
// printf-style single-%s substitution for client-side limit messages.
const fmt = (tpl, val) => (tpl ?? '').replace('%s', val);

const els = {
    log: $('#message-log'),
    textarea: $('#composer-input'),
    sendBtn: $('#send-btn'),
    stopBtn: $('#stop-btn'),
    form: $('#composer-form'),
    statePill: $('#state-pill'),
    emptyState: $('#empty-state'),
    convId: $('#conversation-id'),
    modelSelect: $('#model-select'),
    sysPrompt: $('#sysprompt-input'),
    flash: $('#flash'),
    dropZone: $('#composer-card'),
    fileInput: $('#file-input'),
    paperclip: $('#paperclip-btn'),
    chips: $('#file-chips'),
    sizeBar: $('#size-bar'),
    sizeLabel: $('#size-label'),
    sizeWrap: $('#size-wrap'),
};

let controller = null;
let autoScroll = true;
let overLimit = false;

/* ------------------------------------------------------------------ */
/* Theme                                                               */
/* ------------------------------------------------------------------ */
function applyTheme(theme) {
    document.documentElement.classList.toggle('dark', theme === 'dark');
    localStorage.setItem('lm-theme', theme);
    const icon = $('#theme-icon');
    if (icon) icon.textContent = theme === 'dark' ? '☀️' : '🌙';
}
$('#theme-toggle')?.addEventListener('click', () => {
    const next = document.documentElement.classList.contains('dark') ? 'light' : 'dark';
    applyTheme(next);
});

/* ------------------------------------------------------------------ */
/* State pill                                                          */
/* ------------------------------------------------------------------ */
const STATE_LABELS = {
    idle: { text: T.ready ?? 'Ready · offline', cls: 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300' },
    loading: { text: T.loading ?? 'Loading model…', cls: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300' },
    generating: { text: T.generating ?? 'Generating…', cls: 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300' },
    done: { text: T.done ?? 'Done', cls: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300' },
    error: { text: T.error ?? 'Error', cls: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300' },
};
function setState(state) {
    const s = STATE_LABELS[state] ?? STATE_LABELS.idle;
    els.statePill.textContent = s.text;
    els.statePill.className = `text-xs px-2.5 py-1 rounded-full font-medium ${s.cls}`;
    els.sendBtn.classList.toggle('hidden', state === 'generating');
    els.stopBtn.classList.toggle('hidden', state !== 'generating');
}

function flash(msg) {
    els.flash.textContent = msg;
    els.flash.classList.remove('hidden');
    clearTimeout(flash._t);
    flash._t = setTimeout(() => els.flash.classList.add('hidden'), 3500);
}

/* ------------------------------------------------------------------ */
/* Scrolling                                                           */
/* ------------------------------------------------------------------ */
function scrollToBottom() {
    els.log.scrollTop = els.log.scrollHeight;
}
els.log?.addEventListener('scroll', () => {
    const nearBottom = els.log.scrollHeight - els.log.scrollTop - els.log.clientHeight < 60;
    autoScroll = nearBottom;
});

/* ------------------------------------------------------------------ */
/* Bubble builders                                                     */
/* ------------------------------------------------------------------ */
function escapeHtml(s) {
    const d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
}

function attachmentChipsHtml(files) {
    if (!files || files.length === 0) return '';
    const icon = { txt: '📄', md: '📝', csv: '📊', json: '🧾', pdf: '📕', docx: '📘' };
    const chips = files
        .map((f) => {
            const name = f.name ?? f;
            const ext = (name.split('.').pop() || '').toLowerCase();
            return `<span class="inline-flex items-center gap-1 text-xs bg-white/15 rounded-full px-2 py-0.5">${icon[ext] || '📎'} ${escapeHtml(name)}</span>`;
        })
        .join('');
    return `<div class="flex flex-wrap gap-1.5 mt-2">${chips}</div>`;
}

function appendUserBubble(text, files) {
    hideEmpty();
    const row = document.createElement('div');
    row.className = 'flex justify-end';
    row.innerHTML =
        `<div class="max-w-[85%] rounded-2xl rounded-br-sm bg-indigo-600 text-white px-4 py-2.5 shadow-sm">
            <div class="whitespace-pre-wrap break-words">${escapeHtml(text)}</div>
            ${attachmentChipsHtml(files)}
        </div>`;
    els.log.appendChild(row);
    scrollToBottom();
}

function appendAssistantBubble() {
    hideEmpty();
    const row = document.createElement('div');
    row.className = 'flex justify-start group';
    row.innerHTML =
        `<div class="max-w-[85%] w-full">
            <div class="rounded-2xl rounded-bl-sm bg-gray-100 dark:bg-gray-800 px-4 py-3 shadow-sm">
                <div class="prose-chat text-gray-800 dark:text-gray-100" data-content>
                    <div class="space-y-2" data-skeleton>
                        <div class="skeleton-line w-2/3"></div>
                        <div class="skeleton-line w-5/6"></div>
                        <div class="skeleton-line w-1/2"></div>
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-3 mt-1.5 px-1 text-xs text-gray-400 opacity-0 group-hover:opacity-100 transition" data-toolbar></div>
        </div>`;
    els.log.appendChild(row);
    scrollToBottom();
    return row;
}

function bubbleContentEl(bubble) {
    return $('[data-content]', bubble);
}

function finalizeBubble(bubble, raw, meta) {
    const content = bubbleContentEl(bubble);
    content.classList.remove('cursor-blink');
    content.innerHTML = renderMarkdown(raw);

    const toolbar = $('[data-toolbar]', bubble);
    toolbar.innerHTML = '';

    // Speed meter (extension: speed_meter).
    if (meta && isOn('speed_meter')) {
        const m = document.createElement('span');
        m.textContent = `${meta.chars} chars · ${meta.secs.toFixed(1)}s · ~${meta.cps.toFixed(0)} ch/s`;
        toolbar.appendChild(m);
    }

    const copyLabel = T.copy ?? 'Copy';
    const copyBtn = mkToolBtn(copyLabel, copyLabel, async () => {
        await navigator.clipboard.writeText(raw);
        copyBtn.textContent = T.copied ?? 'Copied!';
        setTimeout(() => (copyBtn.textContent = copyLabel), 1200);
    });
    toolbar.appendChild(copyBtn);

    // Regenerate (extension: regenerate).
    if (isOn('regenerate')) {
        const regenLabel = T.regenerate ?? 'Regenerate';
        toolbar.appendChild(mkToolBtn(regenLabel, regenLabel, () => regenerate()));
    }
}

function mkToolBtn(label, aria, handler) {
    const b = document.createElement('button');
    b.type = 'button';
    b.className = 'hover:text-indigo-500 transition';
    b.textContent = label;
    b.setAttribute('aria-label', aria);
    b.addEventListener('click', handler);
    return b;
}

function appendErrorBubble(msg) {
    const row = document.createElement('div');
    row.className = 'flex justify-start';
    row.innerHTML =
        `<div class="max-w-[85%] rounded-2xl rounded-bl-sm bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 px-4 py-3 text-sm text-red-700 dark:text-red-300">
            <p>${escapeHtml(msg)}</p>
            <p class="mt-1 text-xs opacity-80">${escapeHtml(T.ollamaHint ?? 'Tip: make sure Ollama is running (ollama serve).')}</p>
            <button type="button" class="mt-2 text-xs font-medium underline" data-retry>${escapeHtml(T.retry ?? 'Retry')}</button>
        </div>`;
    $('[data-retry]', row).addEventListener('click', () => regenerate());
    els.log.appendChild(row);
    scrollToBottom();
}

function hideEmpty() {
    els.emptyState?.classList.add('hidden');
}

/* ------------------------------------------------------------------ */
/* Streaming                                                           */
/* ------------------------------------------------------------------ */
let lastPayloadText = '';
let lastPayloadFiles = [];

function buildPayload(text, files) {
    const fd = new FormData();
    fd.append('message', text);
    if (els.convId.value) fd.append('conversation_id', els.convId.value);
    if (els.modelSelect) fd.append('model', els.modelSelect.value);
    // Per-conversation language follows the active UI locale (multilingual ext).
    if (isOn('multilingual')) fd.append('language', LM.locale ?? 'auto');
    if (isOn('system_prompt') && els.sysPrompt) {
        fd.append('system_prompt', els.sysPrompt.value ?? '');
    }
    if (isOn('attachments')) files.forEach((f) => fd.append('files[]', f));
    return fd;
}

async function send() {
    const text = els.textarea.value.trim();
    if (!text || overLimit) return;

    const files = isOn('attachments') ? getStagedFiles() : [];
    lastPayloadText = text;
    lastPayloadFiles = files.slice();

    appendUserBubble(text, files);

    const fd = buildPayload(text, files);

    els.textarea.value = '';
    autoSize();
    if (isOn('attachments')) clearStaged();

    await stream(fd);
}

async function regenerate() {
    if (!lastPayloadText) return;
    await stream(buildPayload(lastPayloadText, lastPayloadFiles));
}

async function stream(formData) {
    controller = new AbortController();
    setState('loading');
    const bubble = appendAssistantBubble();
    const content = bubbleContentEl(bubble);
    const t0 = performance.now();
    let full = '';
    let chars = 0;
    let started = false;

    try {
        const res = await fetch('/chat/stream', {
            method: 'POST',
            signal: controller.signal,
            headers: { 'X-CSRF-TOKEN': csrf, Accept: 'text/event-stream' },
            body: formData,
        });

        if (!res.ok) {
            const data = await res.json().catch(() => ({}));
            throw new Error(data?.message || `Server returned ${res.status}`);
        }

        const reader = res.body.getReader();
        const dec = new TextDecoder();
        let buf = '';

        while (true) {
            const { value, done } = await reader.read();
            if (done) break;
            buf += dec.decode(value, { stream: true });

            const events = buf.split('\n\n');
            buf = events.pop() ?? '';

            for (const evt of events) {
                const line = evt.split('\n').find((l) => l.startsWith('data:'));
                if (!line) continue;
                const data = JSON.parse(line.slice(5).trim());

                if (data.error) throw new Error(data.error);

                if (data.delta) {
                    if (!started) {
                        started = true;
                        setState('generating');
                        content.innerHTML = '';
                        content.classList.add('cursor-blink');
                    }
                    full += data.delta;
                    chars += data.delta.length;
                    content.innerHTML = renderMarkdown(full);
                    content.classList.add('cursor-blink');
                }

                if (data.done) {
                    if (data.conversation_id && !els.convId.value) {
                        els.convId.value = data.conversation_id;
                        history.replaceState({}, '', `/c/${data.conversation_id}`);
                    }
                }
            }
            if (autoScroll) scrollToBottom();
        }

        const secs = (performance.now() - t0) / 1000;
        finalizeBubble(bubble, full, { chars, secs, cps: chars / Math.max(secs, 0.001) });
        setState('done');
        setTimeout(() => setState('idle'), 1500);
    } catch (err) {
        bubble.remove();
        if (err.name === 'AbortError') {
            if (full) finalizeBubble(appendAssistantBubble(), full + `\n\n_${T.stopped ?? '(stopped)'}_`);
            setState('idle');
        } else {
            appendErrorBubble(err.message || (T.error ?? 'Generation failed.'));
            setState('error');
        }
    } finally {
        controller = null;
        els.textarea.focus();
    }
}

function stopGeneration() {
    controller?.abort();
}

/* ------------------------------------------------------------------ */
/* Composer behaviour                                                  */
/* ------------------------------------------------------------------ */
function autoSize() {
    els.textarea.style.height = 'auto';
    els.textarea.style.height = Math.min(els.textarea.scrollHeight, 200) + 'px';
}

els.textarea?.addEventListener('input', autoSize);
els.textarea?.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        send();
    }
    if (e.key === 'Escape') stopGeneration();
});
els.form?.addEventListener('submit', (e) => {
    e.preventDefault();
    send();
});
els.stopBtn?.addEventListener('click', stopGeneration);

/* Example prompt chips */
$$('[data-example]').forEach((chip) =>
    chip.addEventListener('click', () => {
        els.textarea.value = chip.dataset.example;
        autoSize();
        els.textarea.focus();
    }),
);

/* ------------------------------------------------------------------ */
/* Sidebar: rename / delete                                            */
/* ------------------------------------------------------------------ */
$$('[data-rename]').forEach((btn) =>
    btn.addEventListener('click', async (e) => {
        e.preventDefault();
        e.stopPropagation();
        const id = btn.dataset.rename;
        const current = btn.dataset.title ?? '';
        const title = prompt(T.renamePrompt ?? 'Rename conversation:', current);
        if (!title) return;
        await fetch(`/c/${id}`, {
            method: 'PATCH',
            headers: { 'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json' },
            body: JSON.stringify({ title }),
        });
        const label = $(`[data-title-label="${id}"]`);
        if (label) label.textContent = title;
    }),
);

$$('[data-delete]').forEach((form) =>
    form.addEventListener('submit', (e) => {
        if (!confirm(T.deleteConfirm ?? 'Delete this conversation?')) e.preventDefault();
    }),
);

/* ------------------------------------------------------------------ */
/* Language switcher dropdown                                          */
/* ------------------------------------------------------------------ */
(function languageMenu() {
    const btn = $('#lang-btn');
    const menu = $('#lang-menu');
    if (!btn || !menu) return;
    const close = () => {
        menu.classList.add('hidden');
        btn.setAttribute('aria-expanded', 'false');
    };
    btn.addEventListener('click', (e) => {
        e.stopPropagation();
        const hidden = menu.classList.toggle('hidden');
        btn.setAttribute('aria-expanded', String(!hidden));
    });
    document.addEventListener('click', (e) => {
        if (!menu.contains(e.target) && e.target !== btn) close();
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') close();
    });
})();

/* ------------------------------------------------------------------ */
/* System-prompt editor toggle                                         */
/* ------------------------------------------------------------------ */
(function systemPrompt() {
    const toggle = $('#sysprompt-toggle');
    const panel = $('#sysprompt-panel');
    if (!toggle || !panel) return;
    // Open automatically if a prompt is already set.
    if (els.sysPrompt && els.sysPrompt.value.trim()) panel.classList.remove('hidden');
    toggle.addEventListener('click', () => panel.classList.toggle('hidden'));
})();

/* ------------------------------------------------------------------ */
/* Init                                                                */
/* ------------------------------------------------------------------ */
(function init() {
    const stored = localStorage.getItem('lm-theme');
    const prefersDark = window.matchMedia?.('(prefers-color-scheme: dark)').matches;
    applyTheme(stored ?? (prefersDark ? 'dark' : 'light'));

    setState('idle');

    // Attachments are an optional extension: only wire them up when the
    // extension is enabled AND the drop-zone DOM is present.
    if (isOn('attachments') && els.dropZone) {
        const att = initAttachments({
            dropZone: els.dropZone,
            fileInput: els.fileInput,
            paperclip: els.paperclip,
            chipsEl: els.chips,
            sizeBar: els.sizeBar,
            sizeLabel: els.sizeLabel,
            flash,
        });
        att.onChange((_files, meta) => {
            overLimit = meta.overLimit;
            els.sendBtn.disabled = meta.overLimit;
            els.sendBtn.classList.toggle('opacity-50', meta.overLimit);
        });
    }

    // Render any server-provided markdown (existing assistant messages).
    $$('[data-render-md]').forEach((el) => {
        el.innerHTML = renderMarkdown(el.textContent || '');
    });

    scrollToBottom();
    els.textarea?.focus();
})();
