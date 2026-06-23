<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_chat_message', function (Blueprint $table) {
            // Change classification enum to include new values for checkpoint logic
            $table->enum('classification', [
                'correct',
                'partial',
                'misconception',
                'off_topic',
                'question_repetition',
                'attempts_exhausted'
            ])->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('ai_chat_message', function (Blueprint $table) {
            // Revert to original enum values
            $table->enum('classification', [
                'correct',
                'partial',
                'misconception',
                'off_topic'
            ])->nullable()->change();
        });
    }
};
