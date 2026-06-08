<?php

declare(strict_types=1);

use App\Http\Controllers\ChatController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ChatController::class, 'index'])->name('chat.index');
Route::get('/c/{conversation}', [ChatController::class, 'index'])->name('chat.show');

Route::post('/chat/stream', [ChatController::class, 'streamSend'])->name('chat.stream');
Route::patch('/c/{conversation}', [ChatController::class, 'rename'])->name('chat.rename');
Route::delete('/c/{conversation}', [ChatController::class, 'destroy'])->name('chat.destroy');
