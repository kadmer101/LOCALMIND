/**
 * Client-side attachment staging + validation. Mirrors the server limits in
 * config/ollama.php so users get instant feedback before sending.
 */
const LIMITS = {
    maxFiles: 3,
    perFile: 5 * 1024 * 1024, // 5 MB
    total: 10 * 1024 * 1024, // 10 MB
    ext: ['txt', 'md', 'csv', 'json', 'pdf', 'docx'],
};

const EXT_ICON = {
    txt: '📄', md: '📝', csv: '📊', json: '🧾', pdf: '📕', docx: '📘',
};

let staged = [];
let onChange = () => {};

function formatSize(bytes) {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(0)} KB`;
    return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}

export function getStagedFiles() {
    return staged;
}

export function clearStaged() {
    staged = [];
    onChange(staged, { total: 0, overLimit: false });
}

export function initAttachments({ dropZone, fileInput, paperclip, chipsEl, sizeBar, sizeLabel, flash }) {
    const notify = () => {
        const total = staged.reduce((s, f) => s + f.size, 0);
        const overLimit = total > LIMITS.total;
        renderChips();
        renderSizeBar(total, overLimit);
        onChange(staged, { total, overLimit });
    };

    onChange = notify.dispatch || (() => {});

    function addFiles(fileList) {
        for (const f of Array.from(fileList)) {
            const ext = (f.name.split('.').pop() || '').toLowerCase();
            if (!LIMITS.ext.includes(ext)) { flash(`Unsupported file: ${f.name}`); continue; }
            if (f.size > LIMITS.perFile) { flash(`${f.name} is over 5 MB`); continue; }
            if (staged.length >= LIMITS.maxFiles) { flash('Max 3 files per message'); break; }
            if (staged.some((s) => s.name === f.name && s.size === f.size)) continue;
            staged.push(f);
        }
        const total = staged.reduce((s, f) => s + f.size, 0);
        if (total > LIMITS.total) {
            staged.pop();
            flash('Total attachment size exceeds 10 MB');
        }
        notify();
    }

    function renderChips() {
        chipsEl.innerHTML = '';
        chipsEl.classList.toggle('hidden', staged.length === 0);
        staged.forEach((f, i) => {
            const ext = (f.name.split('.').pop() || '').toLowerCase();
            const chip = document.createElement('span');
            chip.className =
                'inline-flex items-center gap-1.5 text-xs bg-gray-100 dark:bg-gray-800 ' +
                'border border-gray-200 dark:border-gray-700 rounded-full pl-2 pr-1 py-1';
            chip.innerHTML =
                `<span>${EXT_ICON[ext] || '📎'}</span>` +
                `<span class="max-w-[160px] truncate">${f.name}</span>` +
                `<span class="text-gray-400">${formatSize(f.size)}</span>`;
            const x = document.createElement('button');
            x.type = 'button';
            x.className = 'ml-0.5 w-4 h-4 grid place-items-center rounded-full hover:bg-gray-300 dark:hover:bg-gray-600';
            x.setAttribute('aria-label', `Remove ${f.name}`);
            x.textContent = '✕';
            x.addEventListener('click', () => { staged.splice(i, 1); notify(); });
            chip.appendChild(x);
            chipsEl.appendChild(chip);
        });
    }

    function renderSizeBar(total, overLimit) {
        const pct = Math.min(100, (total / LIMITS.total) * 100);
        sizeBar.parentElement.classList.toggle('hidden', staged.length === 0);
        sizeBar.style.width = `${pct}%`;
        sizeBar.classList.toggle('bg-red-500', overLimit);
        sizeBar.classList.toggle('bg-indigo-500', !overLimit);
        sizeLabel.textContent = `${formatSize(total)} / 10 MB`;
        sizeLabel.classList.toggle('text-red-500', overLimit);
    }

    // Wire up controls.
    paperclip?.addEventListener('click', () => fileInput.click());
    fileInput?.addEventListener('change', (e) => { addFiles(e.target.files); fileInput.value = ''; });

    if (dropZone) {
        ['dragover', 'dragenter'].forEach((evt) =>
            dropZone.addEventListener(evt, (e) => {
                e.preventDefault();
                dropZone.classList.add('ring-2', 'ring-indigo-400');
            }),
        );
        ['dragleave', 'drop'].forEach((evt) =>
            dropZone.addEventListener(evt, (e) => {
                e.preventDefault();
                dropZone.classList.remove('ring-2', 'ring-indigo-400');
            }),
        );
        dropZone.addEventListener('drop', (e) => addFiles(e.dataTransfer.files));
    }

    // Expose a setter for the consumer to subscribe to changes.
    return {
        onChange(cb) { onChange = cb; },
        addFiles,
    };
}
