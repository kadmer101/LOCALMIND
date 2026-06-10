<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            // 'auto' = follow the active UI locale; or a specific locale code.
            $table->string('language')->default('auto')->after('model');
            // Per-conversation system prompt (extension: system_prompt).
            $table->text('system_prompt')->nullable()->after('language');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->dropColumn(['language', 'system_prompt']);
        });
    }
};
