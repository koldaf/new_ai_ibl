<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('lesson_embeddings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->constrained()->onDelete('cascade');
            $table->string('source_type'); // e.g., 'engage_text', 'explore_pdf', 'ai_setup_pdf', 'quiz_question', etc.
            $table->string('source_id')->nullable(); // could be media id or content id
            $table->text('chunk_text');
            $table->string('vector_path')->nullable(); // depends on model dimension; use 768 for nomic-embed-text, 384 for all-MiniLM-L6-v2, etc.
            $table->integer('chunk_index')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('lesson_embeddings');
    }
};