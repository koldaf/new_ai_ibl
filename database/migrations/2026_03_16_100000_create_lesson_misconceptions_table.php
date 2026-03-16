<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_misconceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->constrained()->onDelete('cascade');
            $table->enum('stage', ['engage', 'explore', 'explain', 'elaborate', 'evaluate'])->default('engage');
            $table->string('concept_tag')->nullable();
            $table->string('label');
            $table->text('description')->nullable();
            $table->text('correct_concept')->nullable();
            $table->text('remediation_hint')->nullable();
            $table->enum('source', ['template', 'ai_candidate'])->default('template');
            $table->enum('status', ['pending_review', 'approved', 'rejected'])->default('approved');
            $table->decimal('confidence', 4, 2)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['lesson_id', 'stage']);
            $table->index(['source', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_misconceptions');
    }
};
