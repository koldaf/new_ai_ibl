<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('lesson_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->constrained()->onDelete('cascade');
            $table->enum('stage', ['engage', 'explore', 'explain', 'elaborate', 'evaluate']);
            $table->enum('media_type', ['video', 'image', 'pdf', 'phet_html', 'csv']);
            $table->string('file_path');
            $table->string('file_name');
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->integer('order')->default(0);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('lesson_media');
    }
};