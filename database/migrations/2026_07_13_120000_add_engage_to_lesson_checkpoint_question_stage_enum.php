<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE lesson_checkpoint_questions MODIFY COLUMN stage ENUM('engage','explore','explain','elaborate') NOT NULL");
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::table('lesson_checkpoint_questions')
                ->where('stage', 'engage')
                ->update(['stage' => 'explore']);

            DB::statement("ALTER TABLE lesson_checkpoint_questions MODIFY COLUMN stage ENUM('explore','explain','elaborate') NOT NULL");
        }
    }
};
