<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_performance_logs', function (Blueprint $table) {
            $table->enum('bloom_level', [
                'remember',
                'understand',
                'apply',
                'analyze',
                'evaluate',
                'create',
            ])->nullable()->after('stage');

            $table->decimal('bloom_confidence', 4, 2)->nullable()->after('bloom_level');
            $table->index(['caller', 'bloom_level']);
        });
    }

    public function down(): void
    {
        Schema::table('ai_performance_logs', function (Blueprint $table) {
            $table->dropIndex(['caller', 'bloom_level']);
            $table->dropColumn('bloom_confidence');
            $table->dropColumn('bloom_level');
        });
    }
};
