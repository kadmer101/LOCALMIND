<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StreamChatRequest;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\FileExtractorService;
use App\Services\OllamaService;
use App\Services\PromptBuilder;
use App\Support\Extensions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatController extends Controller
{
    public function index(?Conversation $conversation = null): View
    {
        $conversations = Conversation::query()
            ->orderByDesc('updated_at')
            ->get(['id', 'title', 'model', 'updated_at']);

        $active = $conversation?->loadMissing('messages');

        /** @var array<string, array{label: string, hint: string}> $models */
        $models = config('ollama.models', []);

        /** @var string $defaultModel */
        $defaultModel = config('ollama.default_model');

        /** @var array<string, array<string, string>> $locales */
        $locales = config('locale.supported', []);

        return view('chat.index', [
            'conversations' => $conversations,
            'active'        => $active,
            'models'        => $models,
            'defaultModel'  => $defaultModel,
            'locales'       => $locales,
        ]);
    }

    /**
     * Stream an assistant reply via Server-Sent Events.
     */
    public function streamSend(
        StreamChatRequest $request,
        OllamaService $ollama,
        FileExtractorService $extractor,
        PromptBuilder $promptBuilder,
    ): StreamedResponse {
        $message = (string) $request->string('message');

        // Attachments are only processed when the extension is enabled.
        $files = Extensions::enabled('attachments') ? $request->uploadedFiles() : [];
        $this->assertUploadLimits($files);

        $conversation = $this->resolveConversation($request, $message);

        // Build chips + extracted text from any attachments.
        $attachments = $this->buildAttachmentChips($files);
        $extracted   = $files === [] ? '' : $extractor->extract($files);

        // Persist the user message.
        Message::create([
            'conversation_id' => $conversation->id,
            'role'            => 'user',
            'content'         => $message,
            'attachments'     => $attachments === [] ? null : $attachments,
        ]);

        $model   = $this->resolveModel($conversation, $ollama);
        $history = $promptBuilder->build(
            $conversation,
            $conversation->messages()->orderBy('id')->get(),
            $extracted,
        );

        return response()->stream(function () use ($ollama, $history, $conversation, $model): void {
            $full = '';

            try {
                $ollama->chatStream(
                    $history,
                    $model,
                    function (string $chunk) use (&$full): void {
                        $full .= $chunk;
                        $this->emit(['delta' => $chunk]);
                    },
                );
            } catch (\Throwable $e) {
                $this->emit(['error' => __('error.generation_failed')
                    . ' (' . $e->getMessage() . ')']);

                return;
            }

            if ($full !== '') {
                Message::create([
                    'conversation_id' => $conversation->id,
                    'role'            => 'assistant',
                    'content'         => $full,
                ]);
                $conversation->touch();
            }

            $this->emit(['done' => true, 'conversation_id' => $conversation->id]);
        }, 200, [
            'Content-Type'      => 'text/event-stream',
            'Cache-Control'     => 'no-cache',
            'X-Accel-Buffering' => 'no',
            'Connection'        => 'keep-alive',
        ]);
    }

    public function rename(Request $request, Conversation $conversation): JsonResponse
    {
        $request->validate([
            'title' => 'required|string|max:120',
        ]);

        $conversation->update(['title' => (string) $request->string('title')]);

        return response()->json(['ok' => true, 'title' => $conversation->title]);
    }

    public function destroy(Conversation $conversation): RedirectResponse
    {
        $conversation->delete();

        return redirect()->route('chat.index');
    }

    /**
     * Export a conversation as a downloadable Markdown file.
     */
    public function export(Conversation $conversation): Response
    {
        abort_unless(Extensions::enabled('export_markdown'), 404);

        $conversation->loadMissing('messages');

        $lines   = [];
        $lines[] = '# ' . $conversation->title;
        $lines[] = '';
        $lines[] = '> Exported from LocalMind · model: ' . ($conversation->model ?? 'default');
        $lines[] = '';

        foreach ($conversation->messages as $msg) {
            $who     = $msg->role === 'user' ? '🧑 You' : '🤖 Assistant';
            $lines[] = '## ' . $who;
            $lines[] = '';
            $lines[] = $msg->content;
            $lines[] = '';
        }

        $body     = implode("\n", $lines);
        $slug     = Str::slug($conversation->title);
        $slug     = $slug !== '' ? $slug : 'conversation';
        $filename = $slug . '.md';

        return response($body, 200, [
            'Content-Type'        => 'text/markdown; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Resolve an existing conversation or create a fresh one titled from the
     * first user message, applying model / language / system-prompt inputs.
     */
    private function resolveConversation(StreamChatRequest $request, string $message): Conversation
    {
        $model         = $request->input('model');
        /** @var string $defaultModel */
        $defaultModel  = config('ollama.default_model');
        $resolvedModel = is_string($model) && $model !== '' ? $model : $defaultModel;

        $language = $request->input('language');
        $language = is_string($language) && $language !== '' ? $language : 'auto';

        $system = null;
        if (Extensions::enabled('system_prompt')) {
            $raw    = $request->input('system_prompt');
            $max    = Extensions::intOption('system_prompt', 'max_chars', 1000);
            $system = is_string($raw) ? Str::limit(trim($raw), $max, '') : null;
            $system = $system === '' ? null : $system;
        }

        $conversationId = $request->input('conversation_id');

        $title = Str::limit(trim($message), 48, '…');
        if ($title === '') {
            $title = 'New chat';
        }

        if (is_numeric($conversationId)) {
            $conversation = Conversation::findOrFail((int) $conversationId);
            $updates      = [];
            if (is_string($model) && $model !== '' && $conversation->model !== $model) {
                $updates['model'] = $model;
            }
            if ($conversation->language !== $language) {
                $updates['language'] = $language;
            }
            if (Extensions::enabled('system_prompt') && $conversation->system_prompt !== $system) {
                $updates['system_prompt'] = $system;
            }
            if ($updates !== []) {
                $conversation->update($updates);
            }

            return $conversation;
        }

        return Conversation::create([
            'title'         => $title,
            'model'         => $resolvedModel,
            'language'      => $language,
            'system_prompt' => $system,
        ]);
    }

    /**
     * Pick the model to use, falling back to the default when the requested
     * model isn't installed in Ollama (model_fallback extension).
     */
    private function resolveModel(Conversation $conversation, OllamaService $ollama): ?string
    {
        $model = $conversation->model;

        if ($model === null || ! Extensions::enabled('model_fallback')) {
            return $model;
        }

        $installed = $ollama->installedModels();
        if ($installed !== [] && ! in_array($model, $installed, true)) {
            /** @var string $default */
            $default = config('ollama.default_model');

            return $default;
        }

        return $model;
    }

    /**
     * @param  array<int, UploadedFile>  $files
     * @return array<int, array{name: string, size: int, ext: string}>
     */
    private function buildAttachmentChips(array $files): array
    {
        return array_map(static function (UploadedFile $file): array {
            return [
                'name' => $file->getClientOriginalName(),
                'size' => (int) $file->getSize(),
                'ext'  => strtolower($file->getClientOriginalExtension()),
            ];
        }, $files);
    }

    /**
     * Enforce per-message upload limits server-side (defence in depth).
     *
     * @param  array<int, UploadedFile>  $files
     */
    private function assertUploadLimits(array $files): void
    {
        /** @var int $maxFiles */
        $maxFiles = config('ollama.uploads.max_files', 3);
        /** @var int $maxTotal */
        $maxTotal = config('ollama.uploads.max_total_bytes', 10 * 1024 * 1024);

        $validator = Validator::make([], []);

        if (count($files) > $maxFiles) {
            $validator->errors()->add('files', "Too many files (max {$maxFiles}).");
        }

        $total = array_sum(array_map(static fn (UploadedFile $f): int => (int) $f->getSize(), $files));
        if ($total > $maxTotal) {
            $mb = round($maxTotal / 1024 / 1024);
            $validator->errors()->add('files', "Total upload size exceeds {$mb} MB.");
        }

        if ($validator->errors()->isNotEmpty()) {
            throw new \Illuminate\Validation\ValidationException($validator);
        }
    }

    /**
     * Emit a single SSE event.
     *
     * @param  array<string, mixed>  $payload
     */
    private function emit(array $payload): void
    {
        echo 'data: ' . json_encode($payload, JSON_THROW_ON_ERROR) . "\n\n";

        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
    }
}
