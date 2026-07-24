<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_performance_logs', function (Blueprint $table) {
            // Ollama's own explanation for why generation stopped: "stop" (natural
            // end/EOS token) vs "length" (hit num_predict and got cut off). Lets us
            // confirm truncation is actually happening, and exactly where, instead
            // of guessing from user reports of "incomplete answers".
            $table->string('done_reason', 20)->nullable()->after('tokens_per_second');
        });
    }

    public function down(): void
    {
        Schema::table('ai_performance_logs', function (Blueprint $table) {
            $table->dropColumn('done_reason');
        });
    }
};
