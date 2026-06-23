<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('engage_mcq_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->constrained()->onDelete('cascade');
            $table->string('stage', 20)->default('engage');
            $table->text('question');
            $table->string('option_a');
            $table->string('option_b');
            $table->string('option_c');
            $table->string('option_d');
            $table->enum('correct_option', ['a', 'b', 'c', 'd']);
            $table->text('feedback_option_a')->nullable();
            $table->text('feedback_option_b')->nullable();
            $table->text('feedback_option_c')->nullable();
            $table->text('feedback_option_d')->nullable();
            $table->timestamps();

            $table->unique(['lesson_id', 'stage']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('engage_mcq_questions');
    }
};