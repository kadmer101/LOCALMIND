<!-- markdownlint-disable MD013 MD033 -->

# 🧠 LocalMind

> A private, offline, **multilingual** AI chat app — Laravel 13 + SQLite + Ollama.
> Everything runs on **your** machine. No accounts, no cloud, no data leaves your laptop.

LocalMind is a lightweight web UI for chatting with a **locally-running LLM**
(via [Ollama](https://ollama.com)). It streams answers token-by-token, speaks
**10 languages** (with full right-to-left support for Arabic), and bundles
**8 toggleable extensions** — all with **no SPA framework** and a tiny JS
bundle, so it stays fast even on an **8 GB RAM / HDD** machine.

📖 **New here? Start with [INSTALLATION.md](INSTALLATION.md)** — a complete,
step-by-step setup guide (base install + multi-language + extensions).

---

## ✨ Features

### Chat experience
- **Streaming replies** over Server-Sent Events — text appears as it's generated.
- **Stop / cancel** mid-generation (`Esc` or the Stop button, via `AbortController`).
- **Live state**: idle · loading-model · generating · done · error.
- **Markdown + syntax-highlighted code** with one-click **Copy**.
- **Conversation sidebar**: new chat, rename, delete.
- **Per-conversation model switch**: `Fast (1B)` ↔ `Smart (3B)`.
- **Smart auto-scroll** (pauses if you scroll up to read).
- **Dark / light** theme, persisted in the browser.
- **Keyboard-first**: `Enter` sends, `Shift+Enter` newline, `Esc` stops.
- **Accessibility**: `aria-live` log, labelled buttons, reduced-motion support.

### 🌍 Multi-language (UI **and** AI replies)
- **10 languages**: English, Arabic, Spanish, French, German, Chinese,
  Japanese, Portuguese, Russian, Hindi.
- **Auto-detect** on first visit: session → cookie → `Accept-Language` → default.
- **RTL support** for Arabic (`dir="rtl"`, mirrored layout via CSS logical
  properties).
- **Reply-language steering**: the model is told to answer in the active
  language (best with the multilingual `qwen2.5:3b` model).
- Built on **native Laravel localization** — no extra package, no route
  prefixes → fast and simple.

### 🧩 Extensions (turn features on/off from one place)
Each capability is a self-contained **extension** toggled via `.env` /
`config/extensions.php`. Off = its UI **and** backend path vanish (no wasted
RAM/CPU):

| # | Extension | Purpose |
|---|-----------|---------|
| E1 | **Multilingual** | 🌐 language switcher + reply-language steering |
| E2 | **System prompt** | per-conversation persona/behaviour editor |
| E3 | **Export Markdown** | download a conversation as `.md` |
| E4 | **Speed meter** | chars · seconds · chars/sec after each reply |
| E5 | **Regenerate** | re-run the last prompt |
| E6 | **Context trim** | cap history sent to Ollama (memory guard for 8 GB) |
| E7 | **Model fallback** | use the default model if the chosen one isn't installed |
| E8 | **Attachments** | drag-and-drop file upload + text extraction |

➡️ Full descriptions, hardware costs and env flags: **[INSTALLATION.md §15](INSTALLATION.md#15-extensions-optional-toggleable-features)**.

### 📎 File attachments (extension E8)
- Up to **3 files**, **5 MB each**, **10 MB total** (enforced client **and** server).
- Types: `.txt .md .csv .json .pdf .docx` (text-only models — no images).
- Text is extracted in **pure PHP** (`smalot/pdfparser`, `phpoffice/phpword`)
  and **truncated to a 6000-char budget** before being sent to the model.

---

## 🏗️ Tech stack & architecture

| Layer | Choice | Why |
|-------|--------|-----|
| Backend | **Laravel 13** (PHP 8.3+) | Mature, batteries-included |
| Database | **SQLite** | Single file, zero-config, perfect for single-user |
| LLM runtime | **Ollama** (`llama3.2:1b`, `qwen2.5:3b`) | Local, offline, small models |
| Frontend | **Vanilla JS + Tailwind v4 + Vite** | No SPA framework = tiny RAM/bundle |
| Streaming | **SSE** over `fetch` + `ReadableStream` | Cancellable, no extra deps |
| i18n | **Native Laravel localization** | No package, no route prefixes |
| Quality | **PHPStan Level 10** (Larastan + strict-rules) | Max static safety |

### Request flow (a chat turn)

```
Browser (chat.js)                Laravel                         Ollama
─────────────────                ───────                         ──────
POST /chat/stream  ───────────▶  SetLocale middleware
  message, model,                resolves UI locale
  language, system_prompt,       │
  files[]                        ▼
                                 ChatController@streamSend
                                   ├─ StreamChatRequest (validate)
                                   ├─ resolveConversation()  (create/update)
                                   ├─ resolveModel()         (E7 fallback)
                                   ├─ FileExtractorService   (E8, budgeted)
                                   └─ PromptBuilder@build()
                                        ├─ system prompt (E2)
                                        ├─ language steer (E1)
                                        ├─ history (trimmed, E6)
                                        └─ extracted file text
                                   │
                                   ▼
                                 OllamaService@chatStream ──▶  POST /api/chat
   data: {"delta": "..."}  ◀────  echo SSE per chunk     ◀──  NDJSON stream
   data: {"done": true}    ◀────  persist assistant msg
```

### Key components

| File | Responsibility |
|------|----------------|
| `app/Http/Controllers/ChatController.php` | Streaming endpoint, export, conversation/model resolution |
| `app/Http/Controllers/LocaleController.php` | Persist UI language (session + cookie) |
| `app/Http/Middleware/SetLocale.php` | Resolve locale per request (session→cookie→Accept-Language→default) |
| `app/Services/OllamaService.php` | `chatStream()` (NDJSON), `installedModels()` |
| `app/Services/PromptBuilder.php` | Assemble messages: system prompt + language steer + trim + file context |
| `app/Services/FileExtractorService.php` | Pure-PHP text extraction with a hard char budget |
| `app/Support/Extensions.php` | Tiny façade over `config/extensions.php` feature flags |
| `config/locale.php` | The 10 supported locales (native name, RTL, model-steer name) |
| `config/extensions.php` | The 8 extension flags + options (env-driven) |
| `resources/js/chat.js` | SSE streaming, state machine, i18n strings, extension gating |
| `lang/*.json` | UI translations (10 files) |

### Data model

```
conversations
  id, title, model, language (default 'auto'), system_prompt (nullable), timestamps
messages
  id, conversation_id → conversations, role (user|assistant),
  content, attachments (JSON, nullable), timestamps
```

`language` and `system_prompt` are added by
`..._add_language_and_system_prompt_to_conversations_table` and power the
multilingual (E1) and system-prompt (E2) extensions.

---

## 🚀 Quick start

> Full details, prerequisites and troubleshooting in **[INSTALLATION.md](INSTALLATION.md)**.

```bash
# 0. Prereqs: PHP 8.3+, Composer, Node 18+, Ollama

# 1. Models (qwen2.5:3b is the multilingual one)
ollama pull llama3.2:1b
ollama pull qwen2.5:3b

# 2. Project
git clone <your-repo-url> localmind && cd localmind
composer install
npm install

# 3. Config + DB
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate

# 4. Build + run
npm run build
php artisan serve        # → http://localhost:8000
```

Make sure Ollama is running (`curl http://127.0.0.1:11434/api/tags`) before
sending a message.

---

## ⚙️ Configuration cheatsheet

All in `.env` (see `.env.example` for the full list):

```dotenv
# Ollama
OLLAMA_BASE_URL=http://127.0.0.1:11434
OLLAMA_DEFAULT_MODEL=llama3.2:1b      # or qwen2.5:3b for multilingual
OLLAMA_TIMEOUT=300

# Localization
APP_LOCALE=en
LOCALE_AUTO_DETECT=true               # detect from browser on first visit

# Extensions (all default true)
EXT_MULTILINGUAL=true
EXT_MULTILINGUAL_MODEL=qwen2.5:3b
EXT_SYSTEM_PROMPT=true
EXT_EXPORT_MARKDOWN=true
EXT_SPEED_METER=true
EXT_REGENERATE=true
EXT_CONTEXT_TRIM=true
EXT_CONTEXT_TRIM_MAX=12               # lower = less RAM on long chats
EXT_MODEL_FALLBACK=true
EXT_ATTACHMENTS=true
```

After any change: `php artisan config:clear` (and `npm run build` if you toggled
which UI controls render).

---

## 🧪 Quality & testing

LocalMind ships with a clean **PHPStan Level 10** baseline and a feature/unit
test suite (the streaming tests fake Ollama, so they run without it installed).

```bash
composer stan     # PHPStan Level 10  → [OK] No errors
php artisan test  # all tests pass
composer test     # both, back-to-back
```

Test coverage includes: streaming, conversation CRUD, upload limits, file
extraction, **locale switching & auto-detection**, **RTL rendering**,
**Markdown export**, **system-prompt persistence**, **language steering**, and
**context trimming / extension gating**.

> On 8 GB RAM, `composer stan` runs with `--memory-limit=512M` so PHPStan
> doesn't hog memory.

---

## 💡 Hardware notes (8 GB RAM / HDD)

- The **model is the bottleneck** — the UI's main job is managing the wait
  (streaming + clear state + an honest speed meter).
- Run **one** model at a time; switching unloads the previous one (first reply
  after a switch reloads from disk — slow on HDD, then fast).
- Use **`qwen2.5:3b`** for non-English; **`llama3.2:1b`** for fastest English.
- Keep **context-trim (E6) on** to cap memory on long conversations; lower
  `EXT_CONTEXT_TRIM_MAX` if RAM is tight.
- Disable extensions you don't use (e.g. `EXT_ATTACHMENTS=false`) to skip their
  work entirely.

---

## 📁 Project structure (highlights)

```
localmind/
├── app/
│   ├── Http/
│   │   ├── Controllers/{ChatController,LocaleController}.php
│   │   ├── Middleware/SetLocale.php
│   │   └── Requests/StreamChatRequest.php
│   ├── Models/{Conversation,Message}.php
│   ├── Services/{OllamaService,PromptBuilder,FileExtractorService}.php
│   └── Support/{Extensions,TextBudget}.php
├── config/{ollama,locale,extensions}.php
├── database/migrations/                 # conversations, messages, language+system_prompt
├── lang/{en,ar,es,fr,de,zh,ja,pt,ru,hi}.json
├── resources/
│   ├── js/{chat,markdown,attachments}.js
│   └── views/chat/{index.blade.php, partials/*}
├── tests/{Feature/{ChatTest,LocalizationTest,ExtensionsTest},Unit/FileExtractorTest}.php
├── INSTALLATION.md
├── phpstan.neon
└── vite.config.js
```

---

## 🔒 Privacy

LocalMind has **no telemetry, no external API calls, and no auth backends**. The
only network traffic is to your local Ollama at `127.0.0.1:11434`. Conversations
live in a local SQLite file you fully control.

---

Enjoy your private, offline, multilingual AI assistant. 🧠🌍🔒
