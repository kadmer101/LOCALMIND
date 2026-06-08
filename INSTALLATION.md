# LocalMind — Step-by-Step Installation Guide

LocalMind is a **lightweight, offline AI chat app** built on **Laravel 13 +
SQLite + Ollama**. It streams tokens from a locally-running LLM, supports file
attachments (with hard limits), markdown + code highlighting, a conversation
sidebar, model switching, and dark mode — all with **no SPA framework** and a
tiny JS bundle.

> Everything runs on **your** machine. No data ever leaves your laptop.

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
   ollama pull llama3.2:1b   # "Fast (1B)"  – quick, lighter answers
   ollama pull qwen2.5:3b    # "Smart (3B)" – better quality, slower
   ```
   > On 8 GB RAM + HDD, the **first** generation per model is slow (the model
   > loads into RAM). LocalMind shows a *"loading model…"* banner for this.

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

You should see the three LocalMind migrations run:
`create_conversations_table`, `create_messages_table`,
`add_attachments_to_messages_table`.

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
- **Sidebar:** new chat, rename (✎), delete (🗑).
- **Dark / light** toggle (🌙/☀️) — persisted in your browser.
- After each reply you get a **char/s speed meter**, plus **Copy** and
  **Regenerate** buttons.

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

Enjoy your private, offline AI assistant. 🧠🔒
