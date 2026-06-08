import { marked } from 'marked';
// Use the "common" build (~30 popular languages) instead of all 190+
// to keep the bundle light — RAM matters on this hardware.
import hljs from 'highlight.js/lib/common';
import DOMPurify from 'dompurify';

marked.setOptions({
    gfm: true,
    breaks: true,
});

/**
 * Render markdown to sanitized HTML with syntax-highlighted code blocks
 * and a one-click "Copy" button on each block.
 */
export function renderMarkdown(text) {
    const rawHtml = marked.parse(text ?? '');
    const clean = DOMPurify.sanitize(rawHtml, {
        ADD_ATTR: ['target', 'rel'],
    });

    const tpl = document.createElement('template');
    tpl.innerHTML = clean;

    // Highlight code + wrap with a copy button.
    tpl.content.querySelectorAll('pre code').forEach((code) => {
        try {
            hljs.highlightElement(code);
        } catch (_) {
            /* ignore */
        }
        const pre = code.closest('pre');
        if (pre && !pre.dataset.enhanced) {
            pre.dataset.enhanced = '1';
            pre.style.position = 'relative';
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className =
                'copy-code absolute top-2 right-2 text-xs px-2 py-0.5 rounded ' +
                'bg-white/10 hover:bg-white/20 text-gray-200 transition';
            btn.textContent = 'Copy';
            btn.setAttribute('aria-label', 'Copy code');
            btn.addEventListener('click', async () => {
                await navigator.clipboard.writeText(code.textContent ?? '');
                btn.textContent = 'Copied!';
                setTimeout(() => (btn.textContent = 'Copy'), 1200);
            });
            pre.appendChild(btn);
        }
    });

    // Open links in a new tab safely.
    tpl.content.querySelectorAll('a[href]').forEach((a) => {
        a.setAttribute('target', '_blank');
        a.setAttribute('rel', 'noopener noreferrer');
    });

    return tpl.innerHTML;
}
