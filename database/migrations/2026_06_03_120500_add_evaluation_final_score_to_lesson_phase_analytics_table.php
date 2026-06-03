<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lesson_phase_analytics', function (Blueprint $table) {
            $table->unsignedTinyInteger('evaluation_final_score')->nullable()->after('reflection_quality_final');
            $table->index(['lesson_id', 'stage', 'evaluation_final_score'], 'lpa_lesson_stage_eval_score_idx');
        });
    }

    public function down(): void
    {
        Schema::table('lesson_phase_analytics', function (Blueprint $table) {
            $table->dropIndex('lpa_lesson_stage_eval_score_idx');
            $table->dropColumn('evaluation_final_score');
        });
    }
};
