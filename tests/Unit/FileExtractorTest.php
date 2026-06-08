<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\FileExtractorService;
use App\Support\TextBudget;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FileExtractorTest extends TestCase
{
    #[Test]
    public function it_reads_plain_text(): void
    {
        $file = UploadedFile::fake()->createWithContent('note.txt', 'Hello LocalMind');

        $service = new FileExtractorService();
        $result  = $service->extract([$file]);

        $this->assertStringContainsString('Hello LocalMind', $result);
        $this->assertStringContainsString('--- FILE: note.txt ---', $result);
    }

    #[Test]
    public function it_truncates_to_char_budget(): void
    {
        config()->set('ollama.uploads.context_char_budget', 100);

        $big  = str_repeat('A', 5000);
        $file = UploadedFile::fake()->createWithContent('big.txt', $big);

        $service = new FileExtractorService();
        $result  = $service->extract([$file]);

        $this->assertLessThanOrEqual(
            100 + mb_strlen(TextBudget::TRUNCATION_MARKER),
            mb_strlen($result),
        );
        $this->assertStringContainsString('[...truncated', $result);
    }

    #[Test]
    public function it_rejects_unsupported_extension(): void
    {
        $file = UploadedFile::fake()->createWithContent('image.png', 'binarydata');

        $service = new FileExtractorService();
        $result  = $service->extract([$file]);

        // Unsupported types yield no extracted body (only the file header).
        $this->assertStringContainsString('--- FILE: image.png ---', $result);
        $this->assertStringNotContainsString('binarydata', $result);
    }

    #[Test]
    public function text_budget_clamp_is_multibyte_safe(): void
    {
        $text    = str_repeat('é', 50);
        $clamped = TextBudget::clamp($text, 10);

        $this->assertSame(10, mb_substr_count($clamped, 'é'));
        $this->assertStringContainsString('[...truncated', $clamped);
    }
}
