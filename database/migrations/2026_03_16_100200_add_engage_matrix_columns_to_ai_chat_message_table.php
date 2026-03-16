<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_chat_message', function (Blueprint $table) {
            $table->enum('classification', ['correct', 'partial', 'misconception', 'off_topic'])->nullable()->after('answer');
            $table->decimal('confidence', 4, 2)->nullable()->after('classification');
            $table->text('feedback_text')->nullable()->after('confidence');
            $table->text('follow_up_question')->nullable()->after('feedback_text');
            $table->enum('misconception_source', ['template', 'ai_candidate', 'none'])->default('none')->after('follow_up_question');
            $table->foreignId('misconception_id')->nullable()->after('misconception_source')->constrained('lesson_misconceptions')->nullOnDelete();
            $table->enum('engage_status', ['in_progress', 'complete', 'review_needed'])->nullable()->after('misconception_id');
            $table->string('completion_reason')->nullable()->after('engage_status');
            $table->enum('context_source', ['rag', 'stage_text', 'none'])->nullable()->after('completion_reason');
            $table->enum('retrieval_mode', ['vector', 'non_vector', 'none'])->nullable()->after('context_source');
            $table->unsignedTinyInteger('turn_index')->nullable()->after('retrieval_mode');
            $table->foreignId('parent_message_id')->nullable()->after('turn_index')->constrained('ai_chat_message')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ai_chat_message', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_message_id');
            $table->dropColumn('turn_index');
            $table->dropColumn('retrieval_mode');
            $table->dropColumn('context_source');
            $table->dropColumn('completion_reason');
            $table->dropColumn('engage_status');
            $table->dropConstrainedForeignId('misconception_id');
            $table->dropColumn('misconception_source');
            $table->dropColumn('follow_up_question');
            $table->dropColumn('feedback_text');
            $table->dropColumn('confidence');
            $table->dropColumn('classification');
        });
    }
};
