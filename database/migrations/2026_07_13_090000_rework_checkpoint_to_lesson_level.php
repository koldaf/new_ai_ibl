<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Fresh-start requirement: old stage checkpoint data is intentionally discarded.
        DB::table('lesson_checkpoint_questions')->delete();
        DB::table('lesson_checkpoint_corpora')->delete();

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE lesson_checkpoint_corpora MODIFY stage ENUM('explore','explain','elaborate') NULL");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE lesson_checkpoint_corpora MODIFY stage ENUM('explore','explain','elaborate') NOT NULL");
        }
    }
};
