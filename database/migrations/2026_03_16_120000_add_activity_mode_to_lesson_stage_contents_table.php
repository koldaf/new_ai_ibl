<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lesson_stage_contents', function (Blueprint $table) {
            $table->string('activity_mode', 20)->default('chat')->after('content_type');
        });
    }

    public function down(): void
    {
        Schema::table('lesson_stage_contents', function (Blueprint $table) {
            $table->dropColumn('activity_mode');
        });
    }
};