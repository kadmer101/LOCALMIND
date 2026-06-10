<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Supported UI locales
    |--------------------------------------------------------------------------
    |
    | The UI (buttons, labels, system prompts) is translated into these
    | languages. Each entry: native name (shown in the switcher), the BCP-47
    | tag sent to the browser, whether the script is right-to-left, and the
    | full English language name injected into the model's system prompt so it
    | answers in the matching language.
    |
    | All of these are well-covered by the multilingual `qwen2.5:3b` model
    | (29+ languages). `llama3.2:1b` is weaker outside English — the UI hints
    | at this in the model switcher.
    |
    */

    'supported' => [
        'en' => ['native' => 'English',  'regional' => 'en',    'dir' => 'ltr', 'name' => 'English'],
        'ar' => ['native' => 'العربية',   'regional' => 'ar',    'dir' => 'rtl', 'name' => 'Arabic'],
        'es' => ['native' => 'Español',  'regional' => 'es',    'dir' => 'ltr', 'name' => 'Spanish'],
        'fr' => ['native' => 'Français', 'regional' => 'fr',    'dir' => 'ltr', 'name' => 'French'],
        'de' => ['native' => 'Deutsch',  'regional' => 'de',    'dir' => 'ltr', 'name' => 'German'],
        'zh' => ['native' => '中文',       'regional' => 'zh',    'dir' => 'ltr', 'name' => 'Chinese'],
        'ja' => ['native' => '日本語',     'regional' => 'ja',    'dir' => 'ltr', 'name' => 'Japanese'],
        'pt' => ['native' => 'Português','regional' => 'pt',    'dir' => 'ltr', 'name' => 'Portuguese'],
        'ru' => ['native' => 'Русский',  'regional' => 'ru',    'dir' => 'ltr', 'name' => 'Russian'],
        'hi' => ['native' => 'हिन्दी',      'regional' => 'hi',    'dir' => 'ltr', 'name' => 'Hindi'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto-detection
    |--------------------------------------------------------------------------
    |
    | When enabled, the first visit with no stored preference detects the
    | language from the browser's Accept-Language header (falling back to the
    | app default). The choice is then persisted in the session + a cookie.
    |
    */

    'auto_detect' => (bool) env('LOCALE_AUTO_DETECT', true),

    // Cookie name + lifetime (days) used to remember the user's choice.
    'cookie'      => 'localmind_locale',
    'cookie_days' => 365,

];
