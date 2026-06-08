<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'LocalMind') }}</title>
    {{-- Prevent dark-mode flash --}}
    <script>
        (function () {
            const t = localStorage.getItem('lm-theme');
            const d = window.matchMedia('(prefers-color-scheme: dark)').matches;
            if (t === 'dark' || (!t && d)) document.documentElement.classList.add('dark');
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-white text-gray-900 dark:bg-gray-950 dark:text-gray-100 antialiased">
<div class="flex h-full">

    {{-- Sidebar --}}
    @include('chat.partials.sidebar')

    {{-- Main chat column --}}
    <main class="flex-1 flex flex-col min-w-0">

        {{-- Top bar --}}
        <header class="flex items-center justify-between gap-3 px-4 h-14 border-b border-gray-200 dark:border-gray-800">
            <div class="flex items-center gap-2 min-w-0">
                <span class="font-semibold tracking-tight">{{ config('app.name', 'LocalMind') }}</span>
                <span class="hidden sm:inline text-xs text-gray-400">· runs locally &amp; offline</span>
            </div>

            <div class="flex items-center gap-2">
                {{-- Model switcher --}}
                <div class="relative">
                    <select id="model-select"
                            class="text-sm rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-2.5 py-1.5 pr-7 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            aria-label="Choose model">
                        @foreach ($models as $key => $meta)
                            <option value="{{ $key }}"
                                @selected(($active->model ?? $defaultModel) === $key)>
                                {{ $meta['label'] }} — {{ $meta['hint'] }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <span id="state-pill" class="text-xs px-2.5 py-1 rounded-full font-medium bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300">Ready</span>

                <button id="theme-toggle" type="button"
                        class="w-9 h-9 grid place-items-center rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800"
                        aria-label="Toggle dark mode">
                    <span id="theme-icon">🌙</span>
                </button>
            </div>
        </header>

        {{-- Message log --}}
        <div id="message-log"
             class="flex-1 overflow-y-auto px-4 py-6"
             aria-live="polite" aria-label="Conversation">
            <div class="mx-auto w-full max-w-3xl space-y-4">

                @if (! $active || $active->messages->isEmpty())
                    @include('chat.partials.empty')
                @endif

                @if ($active)
                    @foreach ($active->messages as $message)
                        @include('chat.partials.message', ['message' => $message])
                    @endforeach
                @endif

            </div>
        </div>

        {{-- Composer --}}
        @include('chat.partials.composer')
    </main>
</div>
</body>
</html>
