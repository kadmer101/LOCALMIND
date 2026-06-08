<aside class="hidden md:flex w-72 shrink-0 flex-col border-r border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-900">
    <div class="p-3">
        <a href="{{ route('chat.index') }}"
           class="flex items-center justify-center gap-2 w-full rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-3 py-2.5 transition">
            <span class="text-base leading-none">＋</span> New chat
        </a>
    </div>

    <nav class="flex-1 overflow-y-auto px-2 pb-3 space-y-0.5" aria-label="Conversations">
        @forelse ($conversations as $c)
            <div class="group flex items-center rounded-lg hover:bg-gray-200/60 dark:hover:bg-gray-800
                        {{ ($active && $active->id === $c->id) ? 'bg-gray-200/80 dark:bg-gray-800' : '' }}">
                <a href="{{ route('chat.show', $c) }}"
                   class="flex-1 min-w-0 px-3 py-2 text-sm truncate">
                    <span data-title-label="{{ $c->id }}">{{ $c->title }}</span>
                </a>

                <button type="button"
                        class="px-1.5 text-gray-400 hover:text-indigo-500 opacity-0 group-hover:opacity-100"
                        data-rename="{{ $c->id }}" data-title="{{ $c->title }}"
                        aria-label="Rename conversation">✎</button>

                <form method="POST" action="{{ route('chat.destroy', $c) }}" data-delete class="px-1.5">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                            class="text-gray-400 hover:text-red-500 opacity-0 group-hover:opacity-100"
                            aria-label="Delete conversation">🗑</button>
                </form>
            </div>
        @empty
            <p class="px-3 py-6 text-xs text-gray-400 text-center">No conversations yet.</p>
        @endforelse
    </nav>

    <div class="p-3 border-t border-gray-200 dark:border-gray-800 text-[11px] text-gray-400 leading-relaxed">
        🔒 Everything stays on your machine.<br>
        Powered by <span class="font-medium">Ollama</span> + Laravel.
    </div>
</aside>
