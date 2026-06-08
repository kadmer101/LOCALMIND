# LOCALMIND
LOCALMIND — UI/UX + FILE ATTACHMENTS + PHPSTAN L10 EXPANSION
Appends to the previous Laravel 13 + SQLite + Ollama spec.
=====================================================================

----------------------------------------------------------------------
PART A — UI/UX DESIGN PHILOSOPHY (for a slow, local, single-user app)
----------------------------------------------------------------------
The model is the bottleneck, so the UI's #1 job is MANAGING THE WAIT.
Design priorities, in order:
  1. STREAM tokens as they arrive (never a frozen screen).
  2. Always show STATE: idle / loading-model / generating / done / error.
  3. Make it obvious the AI runs locally & offline (trust + identity).
  4. Be light: no heavy JS framework. Plain JS + a tiny CSS system.
     (Avoid SPA frameworks — they cost RAM you don't have.)
  5. Keyboard-first: Enter to send, Shift+Enter for newline, Esc to stop.

Visual system (lightweight, no build-step bloat):
  - Use Tailwind via the Laravel 13 default Vite setup (purged = tiny).
  - Color: neutral grays + one accent (e.g. indigo-600).
  - Dark mode via `prefers-color-scheme` + a manual toggle (localStorage).
  - System font stack (no web-font download = faster first paint).
  - Max content width 768px; messages in a centered column.

----------------------------------------------------------------------
PART B — FEATURE LIST (chat-box UX, prioritized)
----------------------------------------------------------------------
MUST-HAVE (v1):
  [B1] Streaming responses (SSE) with a blinking cursor while generating.
  [B2] Stop / cancel button that aborts generation mid-stream.
  [B3] Message states: user bubble, assistant bubble, "thinking" skeleton,
       "loading model… (first run is slow)" banner, error bubble w/ retry.
  [B4] Auto-growing textarea; Enter sends, Shift+Enter = newline.
  [B5] Markdown rendering for assistant output (headings, lists, bold).
  [B6] Code blocks with syntax highlight + one-click "Copy".
  [B7] Conversation sidebar: list, new chat, rename, delete.
  [B8] Model switcher dropdown (llama3.2:1b / qwen2.5:3b) per conversation,
       with a small "faster" / "smarter" hint label.
  [B9] Token/speed meter: show tok/s and elapsed time after each reply
       (sets honest expectations — key for this hardware).
  [B10] Auto-scroll to bottom while streaming; pause auto-scroll if the
        user scrolls up (don't fight the reader).
  [B11] Copy-message and regenerate-last-response buttons.
  [B12] Dark/light toggle, persisted.

NICE-TO-HAVE (v2, note as future chapters):
  - File attachments (detailed below — included now as a guarded feature).
  - Export conversation to Markdown.
  - System-prompt editor per conversation.

----------------------------------------------------------------------
PART C — FILE ATTACHMENTS ("joined files") WITH HARD LIMITS
----------------------------------------------------------------------
WHY LIMITS MATTER ON THIS LAPTOP:
  A 1B-3B model + 8GB RAM + HDD cannot ingest large files. Big text
  blows the context window (slow + OOM risk). So we extract text,
  TRUNCATE hard, and feed only a budgeted slice to the model.

LIMITS (enforce on BOTH client and server):
  - Max files per message:        3
  - Max size per file:            5 MB
  - Max TOTAL size per message:   10 MB
  - Allowed types: .txt .md .csv .json .pdf .docx
    (Images are NOT supported: your models are text-only & no vision.)
  - Extracted text budget fed to model: 6000 characters TOTAL,
    truncated with a clear "[...truncated, file was longer]" marker.
  - Reject anything else with a friendly inline error.

TEXT EXTRACTION (pure PHP, no Python):
  - .txt/.md/.csv/.json : read file contents directly.
  - .pdf  : composer require smalot/pdfparser
  - .docx : composer require phpoffice/phpword
  Wrap extraction in try/catch; on failure show "Couldn't read this file."

UX FOR ATTACHMENTS:
  - Drag-and-drop zone over the input + a paperclip button.
  - Show file chips (name, size, type icon, remove ✕) before sending.
  - Live total-size bar that turns red past the limit and blocks send.
  - In the sent user bubble, show the attached file chips.

----------------------------------------------------------------------
PART D — UPDATED FOLDER STRUCTURE (additions only)
----------------------------------------------------------------------
localmind/
├── app/
│   ├── Http/Controllers/ChatController.php      (+ stream, + attachments)
│   ├── Services/
│   │   ├── OllamaService.php                     (+ chatStream())
│   │   └── FileExtractorService.php              <-- NEW
│   └── Support/
│       └── TextBudget.php                        <-- NEW (truncation helper)
├── config/ollama.php                             (+ limits block)
├── database/migrations/
│   └── ..._add_attachments_to_messages_table.php <-- NEW
├── resources/
│   ├── css/app.css                               (Tailwind)
│   ├── js/
│   │   ├── chat.js                               <-- NEW (SSE + UI logic)
│   │   ├── markdown.js                           <-- NEW (render + highlight)
│   │   └── attachments.js                        <-- NEW (dnd + validation)
│   └── views/chat/
│       ├── index.blade.php                       (full UI)
│       └── partials/
│           ├── sidebar.blade.php
│           ├── message.blade.php
│           └── composer.blade.php                (input + attach zone)
├── phpstan.neon                                  <-- NEW
├── tests/Feature/ChatTest.php                    <-- NEW
├── tests/Unit/FileExtractorTest.php              <-- NEW
└── vite.config.js

----------------------------------------------------------------------
PART E — CONFIG ADDITIONS: config/ollama.php
----------------------------------------------------------------------
'uploads' => [
    'max_files'        => 3,
    'max_file_bytes'   => 5 * 1024 * 1024,   // 5 MB
    'max_total_bytes'  => 10 * 1024 * 1024,  // 10 MB
    'allowed_ext'      => ['txt','md','csv','json','pdf','docx'],
    'context_char_budget' => 6000,
],
'models' => [
    'llama3.2:1b' => ['label' => 'Fast (1B)',   'hint' => 'Quick, lighter answers'],
    'qwen2.5:3b'  => ['label' => 'Smart (3B)',  'hint' => 'Better quality, slower'],
],

----------------------------------------------------------------------
PART F — STREAMING IN OllamaService.php (add this method)
----------------------------------------------------------------------
// Yields chunks of text as Ollama generates them (NDJSON stream).
public function chatStream(array $messages, ?string $model, callable $onChunk): void
{
    $response = Http::timeout(config('ollama.timeout'))
        ->withOptions(['stream' => true])
        ->post(config('ollama.base_url') . '/api/chat', [
            'model'    => $model ?? config('ollama.default_model'),
            'messages' => $messages,
            'stream'   => true,
            'options'  => config('ollama.options'),
        ]);

    $body = $response->toPsrResponse()->getBody();
    $buffer = '';
    while (!$body->eof()) {
        $buffer .= $body->read(1024);
        while (($pos = strpos($buffer, "\n")) !== false) {
            $line   = substr($buffer, 0, $pos);
            $buffer = substr($buffer, $pos + 1);
            if (trim($line) === '') {
                continue;
            }
            /** @var array{message?: array{content?: string}, done?: bool} $json */
            $json  = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            $chunk = $json['message']['content'] ?? '';
            if ($chunk !== '') {
                $onChunk($chunk);
            }
        }
    }
}

----------------------------------------------------------------------
PART G — SSE CONTROLLER ENDPOINT (ChatController, add streamSend)
----------------------------------------------------------------------
// routes/web.php
//   Route::post('/chat/stream', [ChatController::class, 'streamSend']);

public function streamSend(Request $r, OllamaService $ollama, FileExtractorService $extractor)
{
    $data = $r->validate([
        'message'         => 'required|string|max:4000',
        'conversation_id' => 'nullable|exists:conversations,id',
        'model'           => 'nullable|string',
        'files.*'         => 'file|max:5120',   // KB = 5MB; also checked in service
    ]);

    // ... resolve/create $conv, extract files via $extractor, build history ...
    // (validate count/total size against config('ollama.uploads'))

    return response()->stream(function () use ($ollama, $history, $conv) {
        $full = '';
        $ollama->chatStream($history, $conv->model, function (string $chunk) use (&$full) {
            $full .= $chunk;
            echo "data: " . json_encode(['delta' => $chunk]) . "\n\n";
            ob_flush(); flush();
        });
        // persist assistant message AFTER stream completes
        Message::create(['conversation_id' => $conv->id, 'role' => 'assistant', 'content' => $full]);
        echo "data: " . json_encode(['done' => true]) . "\n\n";
        ob_flush(); flush();
    }, 200, [
        'Content-Type'      => 'text/event-stream',
        'Cache-Control'     => 'no-cache',
        'X-Accel-Buffering' => 'no',
        'Connection'        => 'keep-alive',
    ]);
}

----------------------------------------------------------------------
PART H — FRONTEND STREAMING (resources/js/chat.js, core idea)
----------------------------------------------------------------------
// Uses fetch + ReadableStream so we can cancel via AbortController.
let controller = null;

async function streamMessage(payload) {
  controller = new AbortController();
  setState('generating');
  const bubble = appendAssistantBubble(); // empty, with blinking cursor
  const t0 = performance.now();
  let chars = 0;

  const res = await fetch('/chat/stream', {
    method: 'POST', signal: controller.signal,
    headers: { 'X-CSRF-TOKEN': csrf },
    body: payload,                  // FormData (text + files)
  });

  const reader = res.body.getReader();
  const dec = new TextDecoder();
  let buf = '';
  while (true) {
    const { value, done } = await reader.read();
    if (done) break;
    buf += dec.decode(value, { stream: true });
    for (const evt of buf.split('\n\n')) {
      if (!evt.startsWith('data:')) continue;
      const data = JSON.parse(evt.slice(5));
      if (data.delta) { chars += data.delta.length; appendToBubble(bubble, data.delta); }
      if (data.done)  { finalizeBubble(bubble); }
    }
    buf = buf.slice(buf.lastIndexOf('\n\n') + 2);
    if (autoScrollEnabled) scrollToBottom();
  }
  const secs = (performance.now() - t0) / 1000;
  showSpeedMeter(bubble, chars, secs);   // ~tok/s honesty meter
  setState('idle');
}

function stopGeneration() { controller?.abort(); setState('idle'); }

// Composer keybindings
textarea.addEventListener('keydown', (e) => {
  if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); send(); }
  if (e.key === 'Escape') stopGeneration();
});

----------------------------------------------------------------------
PART I — ATTACHMENT VALIDATION (resources/js/attachments.js, core idea)
----------------------------------------------------------------------
const LIMITS = { maxFiles: 3, perFile: 5*1024*1024, total: 10*1024*1024,
                 ext: ['txt','md','csv','json','pdf','docx'] };
let staged = [];

function addFiles(fileList) {
  for (const f of fileList) {
    const ext = f.name.split('.').pop().toLowerCase();
    if (!LIMITS.ext.includes(ext))      return flash(`Unsupported: ${f.name}`);
    if (f.size > LIMITS.perFile)        return flash(`${f.name} > 5MB`);
    if (staged.length >= LIMITS.maxFiles) return flash('Max 3 files');
    staged.push(f);
  }
  const total = staged.reduce((s,f)=>s+f.size,0);
  if (total > LIMITS.total) { staged.pop(); return flash('Total > 10MB'); }
  renderChips(staged);              // name • size • remove ✕
  updateSizeBar(total, LIMITS.total); // red past limit, disables Send
}

// Drag & drop
dropZone.addEventListener('dragover', e => { e.preventDefault(); dropZone.classList.add('ring'); });
dropZone.addEventListener('drop', e => { e.preventDefault(); addFiles(e.dataTransfer.files); });

----------------------------------------------------------------------
PART J — FileExtractorService.php (pure PHP, budgeted)
----------------------------------------------------------------------
<?php
namespace App\Services;

use Illuminate\Http\UploadedFile;
use Smalot\PdfParser\Parser as PdfParser;
use PhpOffice\PhpWord\IOFactory;

class FileExtractorService
{
    /** @param array<int, UploadedFile> $files */
    public function extract(array $files): string
    {
        $budget = (int) config('ollama.uploads.context_char_budget');
        $out = '';
        foreach ($files as $file) {
            $text = $this->readOne($file);
            $out .= "\n\n--- FILE: {$file->getClientOriginalName()} ---\n{$text}";
            if (strlen($out) >= $budget) {
                $out = substr($out, 0, $budget) . "\n[...truncated, file was longer]";
                break;
            }
        }
        return $out;
    }

    private function readOne(UploadedFile $file): string
    {
        $ext = strtolower($file->getClientOriginalExtension());
        return match ($ext) {
            'txt','md','csv','json' => (string) file_get_contents($file->getRealPath()),
            'pdf'  => (new PdfParser())->parseFile($file->getRealPath())->getText(),
            'docx' => $this->readDocx($file->getRealPath()),
            default => '',
        };
    }

    private function readDocx(string $path): string
    {
        $phpWord = IOFactory::load($path);
        $text = '';
        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $el) {
                if (method_exists($el, 'getText')) {
                    $text .= $el->getText() . "\n";
                }
            }
        }
        return $text;
    }
}

----------------------------------------------------------------------
PART K — ACCESSIBILITY & POLISH CHECKLIST
----------------------------------------------------------------------
  - aria-live="polite" on the message log so screen readers announce
    streamed text without spamming.
  - Buttons have aria-labels (Copy, Stop, Delete, New chat).
  - Focus returns to textarea after send.
  - Color contrast >= WCAG AA in both themes.
  - Respect prefers-reduced-motion (disable cursor blink/skeleton anim).
  - Empty state: a friendly "Ask me anything — I run entirely on your
    laptop, offline." card with 3 example prompt chips.
  - Error state: red bubble + "Retry" + a hint to check Ollama is running.

=====================================================================
PART L — PHPSTAN LEVEL 10 (dev + tests)
=====================================================================

INSTALL:
  composer require --dev phpstan/phpstan larastan/larastan
  composer require --dev phpstan/phpstan-strict-rules     (optional, recommended)

NOTE on Level 10: it is the MAX (very strict — treats every `mixed`
as suspect). Larastan adds Laravel awareness (Eloquent, facades,
container) so L10 is achievable. Expect to add real type hints,
PHPDoc generics for collections, and JSON-decode shape annotations.

phpstan.neon
----------------------------------------------------------------------
includes:
    - vendor/larastan/larastan/extension.neon
    - vendor/phpstan/phpstan-strict-rules/rules.neon   # if installed

parameters:
    level: 10
    paths:
        - app
        - tests
        - config
        - routes
    # SQLite/Laravel runtime stubs handled by larastan.
    checkMissingIterableValueType: true
    checkGenericClassInNonGenericObjectType: true
    treatPhpDocTypesAsCertain: true
    ignoreErrors:
        # Add ONLY when justified, with a comment. Prefer fixing types.
        # - '#Cannot call method getText\(\) on .*#'  # PhpWord dynamic elems
    excludePaths:
        - app/Http/Middleware/*   # framework scaffolding if noisy

CODE PATTERNS REQUIRED TO PASS L10 (apply throughout):
  - Type every parameter, property, and return (no untyped `function`).
  - Annotate Eloquent relations & collections with generics:
      /** @return \Illuminate\Database\Eloquent\Relations\HasMany<Message, $this> */
  - Annotate every json_decode shape (see PART F example).
  - Use array shapes in PHPDoc: /** @param array<int, UploadedFile> $files */
  - Never return bare `mixed`; narrow with assertions or casts.
  - Validated request data: cast explicitly, e.g.
      $message = (string) $r->string('message');

COMPOSER SCRIPTS (composer.json):
  "scripts": {
      "stan": "phpstan analyse --memory-limit=512M",
      "test": "phpstan analyse --memory-limit=512M && php artisan test"
  }
  # On 8GB RAM, --memory-limit=512M keeps PHPStan from eating your RAM.
  # Run `composer stan` before every commit.

CI / PRE-COMMIT (optional but tutorial-worthy):
  - A git pre-commit hook running `composer stan` on staged PHP.

----------------------------------------------------------------------
PART M — TESTS (must also pass PHPStan L10)
----------------------------------------------------------------------
tests/Unit/FileExtractorTest.php
  - it_reads_plain_text()
  - it_truncates_to_char_budget()
  - it_rejects_unsupported_extension()   (assert empty/handled)

tests/Feature/ChatTest.php
  - it_creates_a_conversation_on_first_message()
  - it_rejects_more_than_three_files()
  - it_rejects_files_over_total_limit()
  - it_streams_assistant_reply()  (fake Ollama via Http::fake() returning NDJSON)

Example Http::fake for streaming test:
  Http::fake([
    '*/api/chat' => Http::response(
      implode("\n", [
        json_encode(['message'=>['content'=>'Hel']]),
        json_encode(['message'=>['content'=>'lo'], 'done'=>true]),
      ]) . "\n", 200
    ),
  ]);
=====================================================================
