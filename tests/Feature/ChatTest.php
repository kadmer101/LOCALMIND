<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ChatTest extends TestCase
{
    use RefreshDatabase;

    private function fakeOllamaStream(): void
    {
        Http::fake([
            '*/api/chat' => Http::response(
                implode("\n", [
                    json_encode(['message' => ['content' => 'Hel']]),
                    json_encode(['message' => ['content' => 'lo'], 'done' => true]),
                ]) . "\n",
                200,
            ),
        ]);
    }

    #[Test]
    public function the_chat_page_loads(): void
    {
        $this->get('/')->assertOk()->assertSee('Ask me anything');
    }

    #[Test]
    public function it_creates_a_conversation_on_first_message(): void
    {
        $this->fakeOllamaStream();

        $response = $this->post('/chat/stream', [
            'message' => 'Hi there',
        ]);

        $response->assertOk();
        $body = $response->streamedContent();

        $this->assertStringContainsString('"delta":"Hel"', $body);
        $this->assertStringContainsString('"done":true', $body);

        $this->assertDatabaseCount('conversations', 1);
        $this->assertDatabaseHas('messages', ['role' => 'user', 'content' => 'Hi there']);
        $this->assertDatabaseHas('messages', ['role' => 'assistant', 'content' => 'Hello']);
    }

    #[Test]
    public function it_streams_assistant_reply(): void
    {
        $this->fakeOllamaStream();
        $conversation = Conversation::factory()->create();

        $response = $this->post('/chat/stream', [
            'message'         => 'Stream please',
            'conversation_id' => $conversation->id,
        ]);

        $response->assertOk();
        $this->assertStringContainsString('"delta":"lo"', $response->streamedContent());
        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'role'            => 'assistant',
            'content'         => 'Hello',
        ]);
    }

    #[Test]
    public function it_rejects_more_than_three_files(): void
    {
        $this->fakeOllamaStream();

        $files = [
            UploadedFile::fake()->createWithContent('a.txt', 'a'),
            UploadedFile::fake()->createWithContent('b.txt', 'b'),
            UploadedFile::fake()->createWithContent('c.txt', 'c'),
            UploadedFile::fake()->createWithContent('d.txt', 'd'),
        ];

        $this->post('/chat/stream', [
            'message' => 'Too many',
            'files'   => $files,
        ])->assertSessionHasErrors('files');
    }

    #[Test]
    public function it_rejects_files_over_total_limit(): void
    {
        $this->fakeOllamaStream();

        // Two files of 6 MB each = 12 MB total (> 10 MB), each within per-file
        // limit only if we bump it; instead use the explicit total-size guard.
        config()->set('ollama.uploads.max_total_bytes', 100); // 100 bytes total

        $files = [
            UploadedFile::fake()->createWithContent('a.txt', str_repeat('x', 80)),
            UploadedFile::fake()->createWithContent('b.txt', str_repeat('y', 80)),
        ];

        $this->post('/chat/stream', [
            'message' => 'Over total',
            'files'   => $files,
        ])->assertSessionHasErrors('files');
    }

    #[Test]
    public function it_renames_a_conversation(): void
    {
        $conversation = Conversation::factory()->create(['title' => 'Old']);

        $this->patch("/c/{$conversation->id}", ['title' => 'New name'])
            ->assertOk()
            ->assertJson(['ok' => true, 'title' => 'New name']);

        $this->assertDatabaseHas('conversations', ['id' => $conversation->id, 'title' => 'New name']);
    }

    #[Test]
    public function it_deletes_a_conversation_and_its_messages(): void
    {
        $conversation = Conversation::factory()->create();
        Message::factory()->create(['conversation_id' => $conversation->id]);

        $this->delete("/c/{$conversation->id}")->assertRedirect(route('chat.index'));

        $this->assertDatabaseMissing('conversations', ['id' => $conversation->id]);
        $this->assertDatabaseCount('messages', 0);
    }
}
