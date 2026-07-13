<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE ai_chat_message MODIFY context_source ENUM('rag', 'stage_text', 'teacher_question', 'none') NULL");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE ai_chat_message MODIFY context_source ENUM('rag', 'stage_text', 'none') NULL");
    }
};