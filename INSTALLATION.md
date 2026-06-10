# LocalMind — Step-by-Step Installation Guide

LocalMind is a **lightweight, offline, multi-language AI chat app** built on
**Laravel 13 + SQLite + Ollama**. It streams tokens from a locally-running LLM,
speaks **10 languages** (with full RTL support for Arabic), ships **8 toggleable
extensions**, supports file attachments (with hard limits), markdown + code
highlighting, a conversation sidebar, model switching, and dark mode — all with
**no SPA framework** and a tiny JS bundle.

> Everything runs on **your** machine. No data ever leaves your laptop.

This guide covers the base install (§1–§13), then **Multi-language setup**
(§14) and the **Extensions** system (§15).

---

## 0. What you'll end up with

- A web app at **http://localhost:8000**
- A local model served by **Ollama** at `http://127.0.0.1:11434`
- A single **SQLite** file as the database (`database/database.sqlite`)

---

## 1. Prerequisites

Install these once. Versions below are the minimums that were verified.

| Tool | Version | Why |
|------|---------|-----|
| **PHP** | 8.3 or 8.4 | Runs Laravel |
| PHP extensions | `sqlite3`, `pdo_sqlite`, `mbstring`, `xml`, `curl`, `zip`, `gd`, `bcmath` | DB, file parsing, HTTP |
| **Composer** | 2.x | PHP package manager |
| **Node.js** | 18+ (20+ recommended) | Builds the front-end assets |
| **npm** | 9+ | Ships with Node |
| **Ollama** | latest | Runs the local LLM |

### 1a. Install PHP + extensions

**macOS (Homebrew):**
```bash
brew install php composer node
```

**Ubuntu / Debian:**
```bash
sudo apt-get update
sudo apt-get install -y php php-cli php-sqlite3 php-mbstring php-xml \
    php-curl php-zip php-gd php-bcmath unzip
```

**Windows:** use [Laravel Herd](https://herd.laravel.com/) (bundles PHP +
Composer + Node) — easiest path on Windows/macOS.

### 1b. Install Composer (if not bundled)

```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

### 1c. Install Node.js + npm

Download the LTS from <https://nodejs.org> or use your package manager.

### 1d. Verify everything

```bash
php --version        # 8.3+ expected
php -m | grep -iE "sqlite|mbstring|curl|zip"   # extensions present
composer --version   # 2.x
node --version       # 18+
npm --version        # 9+
```

---

## 2. Install & run Ollama (the local AI engine)

LocalMind talks to Ollama over HTTP. Install it first.

1. **Install Ollama** — <https://ollama.com/download> (macOS, Windows, Linux).

   Linux one-liner:
   ```bash
   curl -fsSL https://ollama.com/install.sh | sh
   ```

2. **Start the Ollama server** (it usually auto-starts; if not):
   ```bash
   ollama serve
   ```
   It listens on `http://127.0.0.1:11434`.

3. **Pull the two models LocalMind ships with:**
   ```bash
   ollama pull llama3.2:1b   # "Fast (1B)"  – quick, lighter, English-first
   ollama pull qwen2.5:3b    # "Smart (3B)" – better quality + 29+ languages
   ```
   > On 8 GB RAM + HDD, the **first** generation per model is slow (the model
   > loads into RAM). LocalMind shows a *"loading model…"* banner for this.

   **Which model for which language?**
   - `llama3.2:1b` — fastest, lowest RAM (~1.5 GB). Great for **English**;
     weaker at non-English replies.
   - `qwen2.5:3b` — the **multilingual** pick (~2.5–3 GB). Reliable across
     **Arabic, Chinese, Spanish, French, German, Japanese, Korean, Portuguese,
     Russian, Hindi** and more. Recommended if you chat in any non-English
     language. See §14.

4. **Sanity-check Ollama is reachable:**
   ```bash
   curl http://127.0.0.1:11434/api/tags
   ```
   You should get a JSON list of installed models.

---

## 3. Get the project

```bash
git clone <your-repo-url> localmind
cd localmind
```

> If you already have this folder, just `cd` into it.

---

## 4. Install PHP dependencies

```bash
composer install
```

This pulls Laravel, the PDF parser (`smalot/pdfparser`), the Word reader
(`phpoffice/phpword`), and the dev tools (PHPStan + Larastan).

---

## 5. Install front-end dependencies

```bash
npm install
```

Installs Vite, Tailwind v4, `marked`, `highlight.js`, and `dompurify`.

---

## 6. Configure the environment

1. **Create your `.env`** (copy the example):
   ```bash
   cp .env.example .env
   ```

2. **Generate the app key:**
   ```bash
   php artisan key:generate
   ```

3. **Review the Ollama settings** in `.env` (defaults are fine for a standard
   local setup):
   ```dotenv
   OLLAMA_BASE_URL=http://127.0.0.1:11434
   OLLAMA_DEFAULT_MODEL=llama3.2:1b
   OLLAMA_TIMEOUT=300

   # Leave false for normal local use. Set true ONLY if you run behind an
   # HTTPS-terminating proxy and hit mixed-content errors.
   APP_FORCE_HTTPS=false
   ```

The database is **SQLite** out of the box (`DB_CONNECTION=sqlite`) — no DB
server needed.

---

## 7. Create the database & run migrations

```bash
# Create the empty SQLite file (skip if it already exists)
touch database/database.sqlite

# Build the tables: conversations, messages (+ attachments column)
php artisan migrate
```

You should see the LocalMind migrations run:
`create_conversations_table`, `create_messages_table`,
`add_attachments_to_messages_table`, and
`add_language_and_system_prompt_to_conversations_table` (adds the per-chat
**language** and **system_prompt** columns used by the multi-language and
system-prompt extensions).

---

## 8. Build the front-end assets

**Option A — Production build (recommended for "just run it"):**
```bash
npm run build
```

**Option B — Dev mode with hot reload (while editing CSS/JS):**
```bash
npm run dev
```
Leave this running in its own terminal; it serves assets via Vite.

---

## 9. Start the app

In a terminal:
```bash
php artisan serve
```

Now open **<http://localhost:8000>** in your browser.

> Make sure **Ollama is running** (Step 2) before you send a message.

### One-command dev (optional)

The project ships a convenience script that runs the PHP server, queue, logs,
and Vite together:
```bash
composer dev
```

---

## 10. Use it

- Type a message and press **Enter** (Shift+Enter for a newline).
- Watch the reply **stream in** token-by-token; press **Esc** or **Stop** to
  cancel mid-generation.
- Switch models per-conversation with the **Fast (1B) / Smart (3B)** dropdown.
- **Attach files** with the 📎 button or drag-and-drop:
  - up to **3 files**, **5 MB each**, **10 MB total**
  - types: `.txt .md .csv .json .pdf .docx`
  - text is extracted and **truncated to 6000 chars** before being sent.
- **Switch language** with the 🌐 menu (10 languages, Arabic is right-to-left).
  The whole UI re-translates and the model is told to answer in that language.
- **Set a system prompt** (persona / behaviour) per conversation via the
  system-prompt editor in the composer.
- **Export** any conversation to a Markdown file from the sidebar.
- **Sidebar:** new chat, rename (✎), delete (🗑).
- **Dark / light** toggle (🌙/☀️) — persisted in your browser.
- After each reply you get a **char/s speed meter**, plus **Copy** and
  **Regenerate** buttons.

> The language switcher, system-prompt editor, export, speed meter, regenerate
> and attachments are all **extensions** — turn any of them off in `.env`. See
> §15.

---

## 11. Verify the install (optional but recommended)

**Run the static analyser (PHPStan Level 10):**
```bash
composer stan
```
Expected: `[OK] No errors`.

**Run the test suite:**
```bash
php artisan test
```
Expected: all tests pass (unit + feature). The streaming tests use a fake
Ollama, so they work even without Ollama installed.

**Run both at once:**
```bash
composer test
```

---

## 12. Troubleshooting

| Symptom | Fix |
|---------|-----|
| Red error bubble: *"Is Ollama running?"* | Start Ollama: `ollama serve`. Confirm `curl http://127.0.0.1:11434/api/tags` works. |
| *"model 'llama3.2:1b' not found"* | Pull it: `ollama pull llama3.2:1b`. |
| First reply takes ages | Normal — the model is loading into RAM on first use. Subsequent replies are faster. |
| Page has no styling / blank JS | Run `npm run build` (or `npm run dev`). Hard-refresh the browser. |
| `could not find driver` (SQLite) | Install `php-sqlite3` / enable `pdo_sqlite` and restart PHP. |
| `Permission denied` on storage | `chmod -R 775 storage bootstrap/cache`. |
| 419 / CSRF errors | Run `php artisan key:generate`, then reload. |
| Mixed-content errors behind a proxy | Set `APP_FORCE_HTTPS=true` in `.env`, then `php artisan config:clear`. |
| Changed `.env` but nothing happens | `php artisan config:clear` (and restart `php artisan serve`). |
| Model replies in English despite a non-English UI | Use `qwen2.5:3b` (the multilingual model). `llama3.2:1b` is English-first. Keep `EXT_MULTILINGUAL_STEER=true`. |
| Language switcher / system-prompt editor missing | The matching extension is off — set `EXT_MULTILINGUAL=true` / `EXT_SYSTEM_PROMPT=true`, then `php artisan config:clear`. |
| Wrong language auto-detected on first visit | Pick one from the 🌐 menu (it sticks via session+cookie), or set `LOCALE_AUTO_DETECT=false` and `APP_LOCALE=` to your choice. |

---

## 13. Quick reference — full install from scratch

```bash
# 0. Prereqs: PHP 8.3+, Composer, Node 18+, Ollama installed

# 1. Models
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

---

## 14. Multi-language (UI + AI replies)

LocalMind is multilingual on **two** layers:

1. **The interface** — every label/button/message is translated.
2. **The AI's replies** — the model is instructed to answer in the active
   language.

### 14.1 How it works (the high-level design)

We use **native Laravel localization** — deliberately **no** extra package
(e.g. `mcamara/laravel-localization`). On an 8 GB / HDD machine, performance and
simplicity matter:

- **No route prefixes** (`/en/…`, `/fr/…`) — routing stays flat and fast.
- **No package overhead** — just `lang/{locale}.json` files + the built-in
  `__()` helper, which Laravel already caches.
- **One tiny middleware** (`App\Http\Middleware\SetLocale`) resolves the locale
  once per request.

**Locale auto-detection** runs in this priority order (first match wins):

1. **Session** — an explicit choice made this visit.
2. **Cookie** — a remembered choice from a previous visit (1 year).
3. **`Accept-Language`** header — the browser's preferred language
   (`$request->getPreferredLanguage()`), if `LOCALE_AUTO_DETECT=true`.
4. **App default** — `APP_LOCALE` (English).

The chosen UI locale is also passed to the AI: for any non-English language, a
short *"Always reply in <language>."* line is injected into the system prompt
(this is the **multilingual extension**, §15 / E1). Toggle that injection with
`EXT_MULTILINGUAL_STEER`.

### 14.2 Supported languages

10 languages ship out of the box (defined in `config/locale.php`):

| Code | Language | Script |
|------|----------|--------|
| `en` | English | LTR |
| `ar` | العربية (Arabic) | **RTL** |
| `es` | Español (Spanish) | LTR |
| `fr` | Français (French) | LTR |
| `de` | Deutsch (German) | LTR |
| `zh` | 中文 (Chinese) | LTR |
| `ja` | 日本語 (Japanese) | LTR |
| `pt` | Português (Portuguese) | LTR |
| `ru` | Русский (Russian) | LTR |
| `hi` | हिन्दी (Hindi) | LTR |

**Arabic renders right-to-left automatically** — the `<html dir="rtl">`
attribute flips the whole layout (we use CSS logical properties, so the sidebar,
icons and composer all mirror correctly).

### 14.3 The multilingual model (hardware-aware)

Translating the *UI* is free. Getting good *replies* in another language needs a
model that actually speaks it:

- **Use `qwen2.5:3b`** for non-English chats — it reliably covers all 10 UI
  languages (and ~29 total). RAM cost ≈ 2.5–3 GB; fits an 8 GB machine.
- `llama3.2:1b` is fine for English and fastest/lightest, but its non-English
  output is weaker — the model switcher hints at this.

```bash
ollama pull qwen2.5:3b
# Make it the default if you mostly chat non-English:
#   OLLAMA_DEFAULT_MODEL=qwen2.5:3b   (in .env)
```

> **8 GB tip:** run **one** model at a time. Switching models unloads the
> previous one from RAM; the next first-reply will reload (slow on HDD, then
> fast). Keep the **context-trim** extension on (E6) to cap memory on long chats.

### 14.4 Configure it

In `.env`:

```dotenv
APP_LOCALE=en               # default UI language
APP_FALLBACK_LOCALE=en      # used when a key is missing in a translation
LOCALE_AUTO_DETECT=true     # detect from the browser on first visit

EXT_MULTILINGUAL=true        # the 🌐 switcher + reply-language steering
EXT_MULTILINGUAL_STEER=true  # inject "reply in <language>" into the prompt
EXT_MULTILINGUAL_MODEL=qwen2.5:3b   # the model recommended for non-English
```

After any `.env` change: `php artisan config:clear`.

### 14.5 Add or edit a language

1. Copy the base file: `cp lang/en.json lang/it.json` (e.g. Italian).
2. Translate the **values** (keep the keys and any `:placeholder` tokens
   unchanged).
3. Register it in `config/locale.php` under `supported`:
   ```php
   'it' => ['native' => 'Italiano', 'regional' => 'it', 'dir' => 'ltr', 'name' => 'Italian'],
   ```
   (`name` is the English language name used to steer the model; set `dir` to
   `rtl` for right-to-left scripts.)
4. `php artisan config:clear` — it now appears in the 🌐 menu.

---

## 15. Extensions (optional, toggleable features)

LocalMind's "nice-to-have" features are packaged as **extensions**: each is a
self-contained capability you turn **on/off from one place** (`.env` →
`config/extensions.php`). Turning one off removes both its UI **and** its backend
code path — no dead buttons, no wasted RAM/CPU. This keeps the app lean on
modest hardware: enable only what you use.

### 15.1 The extension registry

All flags live in **`config/extensions.php`**, each driven by an `env()` so you
can flip them per machine without editing code. In Blade/PHP they're checked via
the tiny `App\Support\Extensions` façade
(`Extensions::enabled('export_markdown')`); in the browser they're exposed on
`window.LocalMind.ext` so the JS hides the matching controls too.

### 15.2 The extensions

| # | Extension | What it does | Hardware cost | Env flag(s) |
|---|-----------|--------------|---------------|-------------|
| **E1** | **Multilingual** | 🌐 language switcher + tells the model to reply in the active language (see §14). | Free for the UI; non-English replies want `qwen2.5:3b`. | `EXT_MULTILINGUAL`, `EXT_MULTILINGUAL_STEER`, `EXT_MULTILINGUAL_MODEL` |
| **E2** | **System prompt** | Per-conversation editor to set the assistant's persona/behaviour. | Free; adds a few tokens of context per request. | `EXT_SYSTEM_PROMPT`, `EXT_SYSTEM_PROMPT_MAX`, `EXT_SYSTEM_PROMPT_DEFAULT` |
| **E3** | **Export to Markdown** | Download any conversation as a `.md` file from the sidebar. | Free. | `EXT_EXPORT_MARKDOWN` |
| **E4** | **Speed meter** | Shows chars · seconds · chars/sec after each reply — honest expectations on slow hardware. | Free. | `EXT_SPEED_METER` |
| **E5** | **Regenerate** | Re-run the model on the last prompt. | One extra generation when used. | `EXT_REGENERATE` |
| **E6** | **Context trim** | Send only the last *N* messages to Ollama. **The key memory guard for 8 GB machines** on long chats. | Saves RAM/latency. | `EXT_CONTEXT_TRIM`, `EXT_CONTEXT_TRIM_MAX` |
| **E7** | **Model fallback** | If the chosen model isn't installed, transparently fall back to the default instead of erroring. | Free. | `EXT_MODEL_FALLBACK` |
| **E8** | **Attachments** | Drag-and-drop file upload + text extraction (`.txt .md .csv .json .pdf .docx`). Off = no paperclip, no parsing work. | Parsing PDFs/Word uses CPU/RAM briefly. | `EXT_ATTACHMENTS` |

### 15.3 Configure them

All defaults are **on**. To customise, set the flags in `.env` (full list is in
`.env.example`), e.g. a lean English-only, low-memory profile:

```dotenv
# Lean profile for a tight 8 GB / HDD box
EXT_MULTILINGUAL=false       # English only → hide the language switcher
EXT_ATTACHMENTS=false        # skip file parsing entirely
EXT_CONTEXT_TRIM=true        # keep the memory guard ON
EXT_CONTEXT_TRIM_MAX=8       # even shorter window = less RAM on long chats
EXT_SPEED_METER=true         # keep honest timing feedback
```

Then apply:

```bash
php artisan config:clear
npm run build      # only needed if you changed which UI controls render
```

### 15.4 How to add your own extension

1. Add a block to `config/extensions.php` with an `enabled` flag (and any
   options), driven by `env()`.
2. Gate the **backend** with `Extensions::enabled('your_key')` (controller /
   service).
3. Gate the **frontend**: the flag is already on `window.LocalMind.ext`; in
   `resources/js/chat.js` use `isOn('your_key')`, and in Blade wrap the markup
   in `@if(\App\Support\Extensions::enabled('your_key'))`.
4. Document its env flag(s) in `.env.example` and in the table above.

---

Enjoy your private, offline, multilingual AI assistant. 🧠🌍🔒
