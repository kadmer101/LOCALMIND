<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Services\PromptBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ExtensionsTest extends TestCase
{
    use RefreshDatabase;

    private function fakeOllamaStream(): void
    {
        Http::fake([
            '*/api/chat' => Http::response(
                implode("\n", [
                    json_encode(['message' => ['content' => 'Hi'], 'done' => true]),
                ]) . "\n",
                200,
            ),
        ]);
    }

    /* ---------------------------------------------------------------- */
    /* E3 — Export to Markdown                                          */
    /* ---------------------------------------------------------------- */

    #[Test]
    public function it_exports_a_conversation_as_markdown(): void
    {
        config()->set('extensions.export_markdown.enabled', true);

        $conversation = Conversation::factory()->create(['title' => 'My Chat']);
        Message::factory()->create([
            'conversation_id' => $conversation->id,
            'role'            => 'user',
            'content'         => 'Hello there',
        ]);

        $response = $this->get(route('chat.export', $conversation));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/markdown; charset=UTF-8');
        $body = (string) $response->getContent();
        $this->assertStringContainsString('# My Chat', $body);
        $this->assertStringContainsString('Hello there', $body);
    }

    #[Test]
    public function export_is_hidden_when_the_extension_is_disabled(): void
    {
        config()->set('extensions.export_markdown.enabled', false);

        $conversation = Conversation::factory()->create();

        $this->get(route('chat.export', $conversation))->assertNotFound();
    }

    /* ---------------------------------------------------------------- */
    /* E2 — System prompt                                              */
    /* ---------------------------------------------------------------- */

    #[Test]
    public function it_persists_a_system_prompt_on_the_conversation(): void
    {
        config()->set('extensions.system_prompt.enabled', true);
        $this->fakeOllamaStream();

        $this->post('/chat/stream', [
            'message'       => 'Hi',
            'system_prompt' => 'You are a pirate.',
        ])->assertOk();

        $this->assertDatabaseHas('conversations', ['system_prompt' => 'You are a pirate.']);
    }

    #[Test]
    public function prompt_builder_prepends_system_prompt_and_language_steer(): void
    {
        config()->set('extensions.system_prompt.enabled', true);
        config()->set('extensions.multilingual.enabled', true);
        config()->set('extensions.multilingual.steer_model', true);

        $conversation = Conversation::factory()->create([
            'system_prompt' => 'Be concise.',
            'language'      => 'fr',
        ]);
        Message::factory()->create([
            'conversation_id' => $conversation->id,
            'role'            => 'user',
            'content'         => 'Bonjour',
        ]);

        $history = (new PromptBuilder())->build(
            $conversation,
            $conversation->messages()->orderBy('id')->get(),
        );

        $this->assertSame('system', $history[0]['role']);
        $this->assertStringContainsString('Be concise.', $history[0]['content']);
        // Language steer for French.
        $this->assertStringContainsString('French', $history[0]['content']);
    }

    #[Test]
    public function english_locale_gets_no_language_steer(): void
    {
        config()->set('extensions.system_prompt.enabled', false);
        config()->set('extensions.multilingual.enabled', true);

        $conversation = Conversation::factory()->create(['language' => 'en']);
        Message::factory()->create([
            'conversation_id' => $conversation->id,
            'role'            => 'user',
            'content'         => 'Hello',
        ]);

        $history = (new PromptBuilder())->build(
            $conversation,
            $conversation->messages()->orderBy('id')->get(),
        );

        // No system message for English with no custom prompt.
        $this->assertSame('user', $history[0]['role']);
    }

    /* ---------------------------------------------------------------- */
    /* E6 — Context trimming                                           */
    /* ---------------------------------------------------------------- */

    #[Test]
    public function it_trims_history_to_the_configured_window(): void
    {
        config()->set('extensions.context_trim.enabled', true);
        config()->set('extensions.context_trim.max_messages', 4);
        config()->set('extensions.system_prompt.enabled', false);
        config()->set('extensions.multilingual.enabled', false);

        $conversation = Conversation::factory()->create(['language' => 'en']);
        for ($i = 0; $i < 10; $i++) {
            Message::factory()->create([
                'conversation_id' => $conversation->id,
                'role'            => $i % 2 === 0 ? 'user' : 'assistant',
                'content'         => "msg {$i}",
            ]);
        }

        $history = (new PromptBuilder())->build(
            $conversation,
            $conversation->messages()->orderBy('id')->get(),
        );

        // Only the last 4 turns survive.
        $this->assertCount(4, $history);
        $this->assertStringContainsString('msg 6', $history[0]['content']);
        $this->assertStringContainsString('msg 9', $history[3]['content']);
    }

    /* ---------------------------------------------------------------- */
    /* E8 — Attachments gating                                         */
    /* ---------------------------------------------------------------- */

    #[Test]
    public function attachments_are_ignored_when_the_extension_is_disabled(): void
    {
        config()->set('extensions.attachments.enabled', false);
        $this->fakeOllamaStream();

        // A single valid file is sent, but with the extension OFF the controller
        // drops it before extraction: no attachment chip is persisted on the
        // user message.
        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('note.txt', 'secret content');

        $this->post('/chat/stream', [
            'message' => 'No files please',
            'files'   => [$file],
        ])->assertOk();

        $message = Message::query()->where('role', 'user')->firstOrFail();
        $this->assertNull($message->attachments);
    }
}
