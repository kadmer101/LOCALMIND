<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Thin wrapper around the local Ollama HTTP API.
 *
 * @phpstan-type ChatMessage array{role: string, content: string}
 */
class OllamaService
{
    /**
     * Stream a chat completion, invoking $onChunk for each text delta as
     * Ollama generates it (NDJSON stream).
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @param  callable(string): void                             $onChunk
     */
    public function chatStream(array $messages, ?string $model, callable $onChunk): void
    {
        /** @var string $baseUrl */
        $baseUrl = config('ollama.base_url');
        /** @var int $timeout */
        $timeout = config('ollama.timeout');
        /** @var string $defaultModel */
        $defaultModel = config('ollama.default_model');
        /** @var array<string, mixed> $options */
        $options = config('ollama.options', []);

        $response = Http::timeout($timeout)
            ->withOptions(['stream' => true])
            ->post($baseUrl . '/api/chat', [
                'model'    => $model ?? $defaultModel,
                'messages' => $messages,
                'stream'   => true,
                'options'  => $options,
            ]);

        $body   = $response->toPsrResponse()->getBody();
        $buffer = '';

        while (! $body->eof()) {
            $buffer .= $body->read(1024);

            while (($pos = strpos($buffer, "\n")) !== false) {
                $line   = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 1);

                if (trim($line) === '') {
                    continue;
                }

                /** @var array{message?: array{content?: string}, done?: bool} $json */
                $json  = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                $chunk = $json['message']['content'] ?? '';

                if ($chunk !== '') {
                    $onChunk($chunk);
                }
            }
        }
    }

    /**
     * List models currently installed in the local Ollama instance.
     *
     * @return array<int, string>
     */
    public function installedModels(): array
    {
        /** @var string $baseUrl */
        $baseUrl = config('ollama.base_url');

        try {
            $response = Http::timeout(5)->get($baseUrl . '/api/tags');

            /** @var array{models?: array<int, array{name?: string}>} $data */
            $data   = $response->json() ?? [];
            $models = $data['models'] ?? [];

            $names = array_map(
                static fn (array $m): string => $m['name'] ?? '',
                $models,
            );

            return array_values(array_filter(
                $names,
                static fn (string $name): bool => $name !== '',
            ));
        } catch (\Throwable) {
            return [];
        }
    }
}
