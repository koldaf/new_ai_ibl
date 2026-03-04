<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('lesson_stage_contents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->constrained()->onDelete('cascade');
            $table->enum('stage', ['engage', 'explore', 'explain', 'elaborate', 'evaluate']);
            $table->enum('content_type', ['text', 'wysiwyg'])->default('text');
            $table->longText('content')->nullable();
            $table->timestamps();

            $table->unique(['lesson_id', 'stage']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('lesson_stage_contents');
    }
};