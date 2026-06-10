@php
    $attachmentsOn = \App\Support\Extensions::enabled('attachments');
    $sysPromptOn = \App\Support\Extensions::enabled('system_prompt');
@endphp
<footer class="border-t border-gray-200 dark:border-gray-800 px-4 py-3">
    <div class="mx-auto w-full max-w-3xl">

        {{-- Hidden state --}}
        <input type="hidden" id="conversation-id" value="{{ $active->id ?? '' }}">

        {{-- Inline flash (validation/limit messages) --}}
        <p id="flash" class="hidden mb-2 text-xs text-red-500" role="alert"></p>

        {{-- System-prompt editor (extension: system_prompt) --}}
        @if ($sysPromptOn)
            <div class="mb-2">
                <button type="button" id="sysprompt-toggle"
                        class="text-xs text-gray-400 hover:text-indigo-500 flex items-center gap-1">
                    ⚙ {{ __('sysprompt.label') }}
                </button>
                <div id="sysprompt-panel" class="hidden mt-1.5">
                    <textarea id="sysprompt-input" rows="2"
                              maxlength="{{ (int) \App\Support\Extensions::option('system_prompt', 'max_chars', 1000) }}"
                              class="w-full text-sm rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                              placeholder="{{ __('sysprompt.placeholder') }}">{{ $active->system_prompt ?? '' }}</textarea>
                </div>
            </div>
        @endif

        <form id="composer-form">
            <div id="composer-card"
                 class="rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm transition">

                @if ($attachmentsOn)
                    {{-- File chips --}}
                    <div id="file-chips" class="hidden flex-wrap gap-1.5 px-3 pt-3"></div>

                    {{-- Size bar --}}
                    <div id="size-wrap" class="hidden px-3 pt-2">
                        <div class="flex items-center justify-between text-[11px] text-gray-400 mb-1">
                            <span>{{ __('composer.attachments') }}</span>
                            <span id="size-label">0 B / 10 MB</span>
                        </div>
                        <div class="h-1.5 w-full rounded-full bg-gray-100 dark:bg-gray-800 overflow-hidden">
                            <div id="size-bar" class="h-full w-0 bg-indigo-500 transition-all"></div>
                        </div>
                    </div>
                @endif

                <div class="flex items-end gap-2 p-2">
                    @if ($attachmentsOn)
                        {{-- Paperclip --}}
                        <button type="button" id="paperclip-btn"
                                class="shrink-0 w-9 h-9 grid place-items-center rounded-lg text-gray-400 hover:text-indigo-500 hover:bg-gray-100 dark:hover:bg-gray-800"
                                aria-label="{{ __('composer.attach') }}">📎</button>
                        <input type="file" id="file-input" class="hidden" multiple
                               accept=".txt,.md,.csv,.json,.pdf,.docx">
                    @endif

                    {{-- Textarea --}}
                    <textarea id="composer-input" rows="1"
                              class="flex-1 resize-none bg-transparent px-1 py-2 max-h-52 focus:outline-none placeholder:text-gray-400"
                              placeholder="{{ __('composer.placeholder') }}"
                              aria-label="Message"></textarea>

                    {{-- Send / Stop --}}
                    <button type="submit" id="send-btn"
                            class="shrink-0 w-9 h-9 grid place-items-center rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white disabled:opacity-50"
                            aria-label="{{ __('composer.send') }}">➤</button>
                    <button type="button" id="stop-btn"
                            class="hidden shrink-0 h-9 px-3 grid place-items-center rounded-lg bg-red-500 hover:bg-red-600 text-white text-sm"
                            aria-label="{{ __('composer.stop') }}">■ {{ __('composer.stop') }}</button>
                </div>
            </div>
        </form>

        @if ($attachmentsOn)
            <p class="mt-2 text-center text-[11px] text-gray-400">
                {{ __('composer.dropzone_hint') }}
            </p>
        @endif
    </div>
</footer>
