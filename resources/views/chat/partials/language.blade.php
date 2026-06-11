@php $current = app()->getLocale(); @endphp
<div class="relative" id="lang-switcher">
    <button type="button" id="lang-btn"
            class="flex items-center gap-1 text-sm rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-2.5 py-1.5 hover:border-indigo-400"
            aria-haspopup="true" aria-expanded="false" aria-label="{{ __('header.choose_language') }}">
        <span>🌐</span>
        <span class="hidden sm:inline">{{ $locales[$current]['native'] ?? strtoupper($current) }}</span>
    </button>
    <div id="lang-menu"
         class="hidden absolute end-0 mt-1 w-44 max-h-72 overflow-y-auto rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-lg z-20 py-1">
        @foreach ($locales as $code => $meta)
            <a href="{{ route('locale.switch', $code) }}"
               class="flex items-center justify-between px-3 py-1.5 text-sm hover:bg-gray-100 dark:hover:bg-gray-800 {{ $code === $current ? 'text-indigo-600 dark:text-indigo-400 font-medium' : '' }}">
                <span>{{ $meta['native'] }}</span>
                @if ($code === $current)<span>✓</span>@endif
            </a>
        @endforeach
    </div>
</div>
