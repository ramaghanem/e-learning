<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per student attempt at a quiz lesson.
        Schema::create('quiz_attempts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('lesson_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('student_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->unsignedInteger('attempt_number');


            $table->unsignedInteger('time_limit_minutes')->nullable();
            $table->unsignedTinyInteger('passing_score');
            $table->unsignedInteger('total_points');

            $table->timestamp('started_at');
            $table->timestamp('expires_at')->nullable();   // null = no time limit
            $table->timestamp('submitted_at')->nullable(); // null = still in progress
            $table->boolean('timed_out')->default(false);  // submitted because the time ran out

            // Filled when the attempt is submitted
            $table->unsignedInteger('score')->nullable();
            $table->decimal('percentage', 5, 2)->nullable();
            $table->boolean('passed')->nullable();

            $table->timestamps();

            // Also stops two attempts with the same number being created at once.
            $table->unique(['lesson_id', 'student_id', 'attempt_number']);
            $table->index(['student_id', 'lesson_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quiz_attempts');
    }
};
