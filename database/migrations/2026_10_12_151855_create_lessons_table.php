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
        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
               $table->foreignId('section_id')
            ->constrained()
            ->cascadeOnDelete();

        $table->string('title', 200);

        // video, article, quiz
        $table->string('type', 20);

        $table->unsignedInteger('order')->default(0);

        // Video lesson
        $table->string('video_url')->nullable();
        $table->string('video_path')->nullable();
        $table->string('video_thumbnail')->nullable();
        $table->unsignedInteger('duration_minutes')->nullable();

        // Article lesson
        $table->longText('content')->nullable();
        $table->unsignedInteger('reading_time_minutes')->nullable();

        $table->boolean('is_free_preview')->default(false);

        $table->timestamps();

        $table->index(['section_id', 'order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lessons');
    }
};
