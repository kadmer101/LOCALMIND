<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Conversation;
use App\Support\Extensions;

/**
 * Assembles the final message array sent to Ollama, applying (where their
 * extensions are enabled):
 *   - a per-conversation system prompt,
 *   - language steering ("always reply in <language>"),
 *   - extracted file context,
 *   - context-window trimming (memory guard for small machines).
 *
 * @phpstan-type ChatMessage array{role: string, content: string}
 */
class PromptBuilder
{
    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\Message>  $messages
     * @return array<int, array{role: string, content: string}>
     */
    public function build(Conversation $conversation, \Illuminate\Support\Collection $messages, string $extracted = ''): array
    {
        $history = [];

        // 1. System prompt(s) come first.
        $system = $this->systemPrompt($conversation);
        if ($system !== '') {
            $history[] = ['role' => 'system', 'content' => $system];
        }

        // 2. Conversation turns (optionally trimmed to the last N).
        $turns = $messages->values();

        if (Extensions::enabled('context_trim')) {
            $max = Extensions::intOption('context_trim', 'max_messages', 12);
            if ($max > 0 && $turns->count() > $max) {
                $turns = $turns->slice($turns->count() - $max)->values();
            }
        }

        foreach ($turns as $msg) {
            $history[] = ['role' => $msg->role, 'content' => $msg->content];
        }

        // 3. Extracted file context (attachments extension) just before reply.
        if ($extracted !== '') {
            $history[] = [
                'role'    => 'system',
                'content' => "The user attached the following file content:\n" . $extracted,
            ];
        }

        return $history;
    }

    /**
     * Compose the system prompt from the per-conversation prompt + language
     * steering instruction.
     */
    public function systemPrompt(Conversation $conversation): string
    {
        $parts = [];

        if (Extensions::enabled('system_prompt')) {
            $custom = trim((string) $conversation->system_prompt);
            if ($custom === '') {
                $custom = trim(Extensions::stringOption('system_prompt', 'default', ''));
            }
            if ($custom !== '') {
                $parts[] = $custom;
            }
        }

        if (Extensions::enabled('multilingual') && (bool) Extensions::option('multilingual', 'steer_model', true)) {
            $steer = $this->languageSteer($conversation);
            if ($steer !== '') {
                $parts[] = $steer;
            }
        }

        return implode("\n\n", $parts);
    }

    /**
     * Build the "always reply in <language>" instruction for the conversation's
     * resolved language ('auto' follows the active UI locale).
     */
    private function languageSteer(Conversation $conversation): string
    {
        $locale = $conversation->language === 'auto' || $conversation->language === ''
            ? app()->getLocale()
            : $conversation->language;

        if ($locale === 'en') {
            return ''; // No steering needed for the default language.
        }

        /** @var array<string, array<string, string>> $supported */
        $supported = config('locale.supported', []);
        $name      = $supported[$locale]['name'] ?? null;

        if ($name === null) {
            return '';
        }

        // Use the translated steering string in the target language too.
        return (string) __('lang.reply_in', ['language' => $name], $locale);
    }
}
