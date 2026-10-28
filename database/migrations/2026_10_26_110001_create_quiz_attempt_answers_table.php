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
        // One row per question of an attempt, created when the attempt starts.
        // The question is copied, so editing or deleting it later does not
        // change the attempt or its result.
        Schema::create('quiz_attempt_answers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('quiz_attempt_id')
                ->constrained()
                ->cascadeOnDelete();


            $table->foreignId('quiz_question_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->unsignedInteger('position');

            // Copy of the question when the attempt started
            $table->longText('question');
            $table->unsignedInteger('points');
            $table->json('options'); // [{"id": 1, "text": "..."}, ...] in order

          
            $table->unsignedBigInteger('correct_option_id');
            $table->unsignedBigInteger('selected_option_id')->nullable();
            $table->timestamp('answered_at')->nullable();

            // Filled when the attempt is submitted
            $table->boolean('is_correct')->nullable();
            $table->unsignedInteger('points_earned')->nullable();

            $table->timestamps();

            $table->unique(['quiz_attempt_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quiz_attempt_answers');
    }
};
