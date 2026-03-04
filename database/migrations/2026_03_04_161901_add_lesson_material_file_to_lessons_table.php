<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            //
            $table->string('lesson_material_file')->nullable()->after('description');
            $table->enum('processing_status', ['pending', 'processing', 'completed', 'failed'])->default('pending')->after('lesson_material_file');
            $table->string('vector_store_path')->nullable()->after('processing_status');    
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            //
            $table->dropColumn('lesson_material_file');
            $table->dropColumn('processing_status');
            $table->dropColumn('vector_store_path');
        });
    }
};
