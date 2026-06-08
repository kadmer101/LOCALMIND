<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class StreamChatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var int $maxFileBytes */
        $maxFileBytes = config('ollama.uploads.max_file_bytes', 5 * 1024 * 1024);
        $maxFileKb    = intdiv($maxFileBytes, 1024);

        /** @var int $maxFiles */
        $maxFiles = config('ollama.uploads.max_files', 3);

        /** @var array<int, string> $allowed */
        $allowed = config('ollama.uploads.allowed_ext', []);

        return [
            'message'         => 'required|string|max:4000',
            'conversation_id' => 'nullable|integer|exists:conversations,id',
            'model'           => 'nullable|string',
            'files'           => 'nullable|array|max:' . $maxFiles,
            'files.*'         => 'file|max:' . $maxFileKb . '|mimes:' . implode(',', $allowed),
        ];
    }

    /**
     * The uploaded files for this request, typed for static analysis.
     *
     * @return array<int, UploadedFile>
     */
    public function uploadedFiles(): array
    {
        /** @var array<int, UploadedFile> $files */
        $files = $this->file('files', []);

        return array_values($files);
    }
}
