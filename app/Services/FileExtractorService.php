<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\TextBudget;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpWord\IOFactory;
use Smalot\PdfParser\Parser as PdfParser;
use Throwable;

/**
 * Extracts plain text from uploaded files (pure PHP, no Python), then clamps
 * the combined result to the configured character budget so the model is never
 * overwhelmed.
 */
class FileExtractorService
{
    /**
     * @param  array<int, UploadedFile>  $files
     */
    public function extract(array $files): string
    {
        /** @var int $budget */
        $budget = config('ollama.uploads.context_char_budget', 6000);
        $out    = '';

        foreach ($files as $file) {
            $name = $file->getClientOriginalName();
            $text = $this->readOne($file);
            $out .= "\n\n--- FILE: {$name} ---\n{$text}";

            if (mb_strlen($out) >= $budget) {
                break;
            }
        }

        return TextBudget::clamp(ltrim($out), $budget);
    }

    private function readOne(UploadedFile $file): string
    {
        $ext  = strtolower($file->getClientOriginalExtension());
        $path = $file->getRealPath();

        if ($path === false) {
            return "[Couldn't read this file.]";
        }

        try {
            return match ($ext) {
                'txt', 'md', 'csv', 'json' => $this->readPlainText($path),
                'pdf'                      => $this->readPdf($path),
                'docx'                     => $this->readDocx($path),
                default                    => '',
            };
        } catch (Throwable) {
            return "[Couldn't read this file.]";
        }
    }

    private function readPlainText(string $path): string
    {
        $contents = file_get_contents($path);

        return $contents === false ? '' : $contents;
    }

    private function readPdf(string $path): string
    {
        return (new PdfParser())->parseFile($path)->getText();
    }

    private function readDocx(string $path): string
    {
        $phpWord = IOFactory::load($path);
        $text    = '';

        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                if (method_exists($element, 'getText')) {
                    /** @var mixed $value */
                    $value = $element->getText();
                    if (is_string($value)) {
                        $text .= $value . "\n";
                    }
                }
            }
        }

        return $text;
    }
}
