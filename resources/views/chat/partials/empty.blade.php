<div id="empty-state" class="flex flex-col items-center text-center py-16">
    <div class="w-14 h-14 grid place-items-center rounded-2xl bg-indigo-100 dark:bg-indigo-900/40 text-2xl mb-4">
        🧠
    </div>
    <h1 class="text-xl font-semibold">{{ __('empty.title') }}</h1>
    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400 max-w-sm">
        {{ __('empty.subtitle') }}
    </p>

    <div class="mt-6 flex flex-wrap justify-center gap-2 max-w-md">
        <button type="button" data-example="{{ __('empty.example_sse') }}"
                class="text-sm rounded-full border border-gray-200 dark:border-gray-700 px-3 py-1.5 hover:border-indigo-400 hover:text-indigo-500 transition">
            {{ __('empty.example_sse') }}
        </button>
        <button type="button" data-example="{{ __('empty.example_linked_list') }}"
                class="text-sm rounded-full border border-gray-200 dark:border-gray-700 px-3 py-1.5 hover:border-indigo-400 hover:text-indigo-500 transition">
            {{ __('empty.example_linked_list') }}
        </button>
        <button type="button" data-example="{{ __('empty.example_clean_code') }}"
                class="text-sm rounded-full border border-gray-200 dark:border-gray-700 px-3 py-1.5 hover:border-indigo-400 hover:text-indigo-500 transition">
            {{ __('empty.example_clean_code') }}
        </button>
    </div>
</div>
