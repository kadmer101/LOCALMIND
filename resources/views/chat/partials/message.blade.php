@php
    $isUser = $message->role === 'user';
    $icon = ['txt' => '📄', 'md' => '📝', 'csv' => '📊', 'json' => '🧾', 'pdf' => '📕', 'docx' => '📘'];
@endphp

@if ($isUser)
    <div class="flex justify-end">
        <div class="max-w-[85%] rounded-2xl rounded-br-sm bg-indigo-600 text-white px-4 py-2.5 shadow-sm">
            <div class="whitespace-pre-wrap break-words">{{ $message->content }}</div>
            @if (! empty($message->attachments))
                <div class="flex flex-wrap gap-1.5 mt-2">
                    @foreach ($message->attachments as $att)
                        <span class="inline-flex items-center gap-1 text-xs bg-white/15 rounded-full px-2 py-0.5">
                            {{ $icon[$att['ext']] ?? '📎' }} {{ $att['name'] }}
                        </span>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
@else
    <div class="flex justify-start group">
        <div class="max-w-[85%] w-full">
            <div class="rounded-2xl rounded-bl-sm bg-gray-100 dark:bg-gray-800 px-4 py-3 shadow-sm">
                <div class="prose-chat text-gray-800 dark:text-gray-100" data-render-md>{{ $message->content }}</div>
            </div>
        </div>
    </div>
@endif
