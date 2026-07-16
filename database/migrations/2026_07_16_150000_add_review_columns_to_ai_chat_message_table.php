<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_chat_message', function (Blueprint $table) {
            $table->timestamp('reviewed_at')->nullable()->after('parent_message_id');
            $table->foreignId('reviewed_by')->nullable()->after('reviewed_at')->constrained('users')->nullOnDelete();
            $table->enum('review_verdict', ['correct', 'incorrect'])->nullable()->after('reviewed_by');
            $table->string('corrected_classification')->nullable()->after('review_verdict');
            $table->text('review_notes')->nullable()->after('corrected_classification');

            $table->index('reviewed_at');
        });
    }

    public function down(): void
    {
        Schema::table('ai_chat_message', function (Blueprint $table) {
            $table->dropIndex(['reviewed_at']);
            $table->dropColumn('review_notes');
            $table->dropColumn('corrected_classification');
            $table->dropColumn('review_verdict');
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn('reviewed_at');
        });
    }
};
