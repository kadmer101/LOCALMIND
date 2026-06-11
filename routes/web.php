<?php

declare(strict_types=1);

use App\Http\Controllers\ChatController;
use App\Http\Controllers\LocaleController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ChatController::class, 'index'])->name('chat.index');
Route::get('/c/{conversation}', [ChatController::class, 'index'])->name('chat.show');

Route::post('/chat/stream', [ChatController::class, 'streamSend'])->name('chat.stream');
Route::patch('/c/{conversation}', [ChatController::class, 'rename'])->name('chat.rename');
Route::delete('/c/{conversation}', [ChatController::class, 'destroy'])->name('chat.destroy');

// Export a conversation to Markdown (extension: export_markdown).
Route::get('/c/{conversation}/export', [ChatController::class, 'export'])->name('chat.export');

// UI language switcher.
Route::get('/lang/{locale}', [LocaleController::class, 'switch'])->name('locale.switch');
