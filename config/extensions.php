<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| LocalMind extensions
|--------------------------------------------------------------------------
|
| Each "extension" is an optional, self-contained feature you can turn on or
| off from a single place. Toggling one off removes its UI and its backend
| code path entirely (no dead controls, no wasted RAM/CPU). Most are driven by
| env() so you can flip them per-machine without editing code.
|
| Read the matching section in INSTALLATION.md ("Extensions") for what each
| one does, its hardware cost, and how to configure it.
|
| Convention: every extension has an `enabled` flag; some add extra options.
|
*/

return [

    /*
    | E1 — Multilingual UI + replies (recommended ON)
    | Translates the interface and instructs the model to answer in the
    | active language. Practically free; needs a multilingual model
    | (qwen2.5:3b) for non-English replies.
    */
    'multilingual' => [
        'enabled'         => (bool) env('EXT_MULTILINGUAL', true),
        // Inject a "reply in <language>" instruction into the system prompt.
        'steer_model'     => (bool) env('EXT_MULTILINGUAL_STEER', true),
        // Recommend the multilingual model when a non-English locale is active.
        'recommend_model' => env('EXT_MULTILINGUAL_MODEL', 'qwen2.5:3b'),
    ],

    /*
    | E2 — System-prompt editor (per conversation)
    | A small editor to set the assistant's behaviour/persona for a chat.
    | Free; uses a few tokens of context per request.
    */
    'system_prompt' => [
        'enabled'     => (bool) env('EXT_SYSTEM_PROMPT', true),
        'max_chars'   => (int) env('EXT_SYSTEM_PROMPT_MAX', 1000),
        'default'     => env('EXT_SYSTEM_PROMPT_DEFAULT', ''),
    ],

    /*
    | E3 — Export conversation to Markdown
    | Download any conversation as a .md file. Free.
    */
    'export_markdown' => [
        'enabled' => (bool) env('EXT_EXPORT_MARKDOWN', true),
    ],

    /*
    | E4 — Token / speed meter
    | Shows chars, seconds and chars/sec after each reply — honest
    | expectations for slow hardware. Free.
    */
    'speed_meter' => [
        'enabled' => (bool) env('EXT_SPEED_METER', true),
    ],

    /*
    | E5 — Regenerate last response. Free.
    */
    'regenerate' => [
        'enabled' => (bool) env('EXT_REGENERATE', true),
    ],

    /*
    | E6 — Context window trimming (memory guard)
    | Keep only the last N messages in the prompt sent to Ollama. Lower N =
    | less RAM/latency on long chats. Critical guard for 8GB machines.
    */
    'context_trim' => [
        'enabled'       => (bool) env('EXT_CONTEXT_TRIM', true),
        'max_messages'  => (int) env('EXT_CONTEXT_TRIM_MAX', 12),
    ],

    /*
    | E7 — Auto model fallback
    | If the selected model isn't installed in Ollama, transparently fall
    | back to the default model instead of erroring. Free.
    */
    'model_fallback' => [
        'enabled' => (bool) env('EXT_MODEL_FALLBACK', true),
    ],

    /*
    | E8 — File attachments
    | The drag-and-drop + extract-text feature. Turn OFF to hide the
    | paperclip and skip file parsing entirely (saves the PDF/Word
    | dependencies' work). Limits live in config/ollama.php.
    */
    'attachments' => [
        'enabled' => (bool) env('EXT_ATTACHMENTS', true),
    ],

];
