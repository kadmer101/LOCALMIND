<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StreamChatRequest;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\FileExtractorService;
use App\Services\OllamaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        return view('chat.index', [
            'conversations' => $conversations,
            'active'        => $active,
            'models'        => $models,
            'defaultModel'  => $defaultModel,
        ]);
    }

    /**
     * Stream an assistant reply via Server-Sent Events.
     */
    public function streamSend(
        StreamChatRequest $request,
        OllamaService $ollama,
        FileExtractorService $extractor,
    ): StreamedResponse {
        $message = (string) $request->string('message');
        $files   = $request->uploadedFiles();

        $this->assertUploadLimits($files);

        $conversation = $this->resolveConversation(
            $request->input('conversation_id'),
            $request->input('model'),
            $message,
        );

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

        $history = $this->buildHistory($conversation, $extracted);

        return response()->stream(function () use ($ollama, $history, $conversation): void {
            $full = '';

            try {
                $ollama->chatStream(
                    $history,
                    $conversation->model,
                    function (string $chunk) use (&$full): void {
                        $full .= $chunk;
                        $this->emit(['delta' => $chunk]);
                    },
                );
            } catch (\Throwable $e) {
                $this->emit(['error' => 'Generation failed. Is Ollama running? ('
                    . $e->getMessage() . ')']);

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
     * Resolve an existing conversation or create a fresh one titled from the
     * first user message.
     */
    private function resolveConversation(mixed $conversationId, mixed $model, string $message): Conversation
    {
        /** @var string $defaultModel */
        $defaultModel  = config('ollama.default_model');
        $resolvedModel = is_string($model) && $model !== '' ? $model : $defaultModel;

        $title = Str::limit(trim($message), 48, '…');
        if ($title === '') {
            $title = 'New chat';
        }

        if (is_numeric($conversationId)) {
            $conversation = Conversation::findOrFail((int) $conversationId);
            if (is_string($model) && $model !== '' && $conversation->model !== $model) {
                $conversation->update(['model' => $model]);
            }

            return $conversation;
        }

        return Conversation::create([
            'title' => $title,
            'model' => $resolvedModel,
        ]);
    }

    /**
     * Build the message history (with optional attachment context) for Ollama.
     *
     * @return array<int, array{role: string, content: string}>
     */
    private function buildHistory(Conversation $conversation, string $extracted): array
    {
        $history = [];

        foreach ($conversation->messages()->orderBy('id')->get() as $msg) {
            $history[] = [
                'role'    => $msg->role,
                'content' => $msg->content,
            ];
        }

        if ($extracted !== '') {
            // Inject extracted file context just before generation.
            $history[] = [
                'role'    => 'system',
                'content' => "The user attached the following file content:\n" . $extracted,
            ];
        }

        return $history;
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
