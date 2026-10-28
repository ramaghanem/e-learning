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
        Schema::create('courses', function (Blueprint $table) {
            $table->id();

             $table->string('title', 200)->unique();
            $table->string('short_description', 200);
            $table->text('description');

            $table->foreignId('category_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('instructor_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('difficulty');
            $table->string('language')->default('English');

            $table->string('thumbnail')->nullable();

            $table->json('learning_objectives')->nullable();
            $table->text('requirements')->nullable();
            $table->text('target_audience')->nullable();

            $table->unsignedInteger('duration_hours')->default(0);
            $table->unsignedTinyInteger('duration_minutes')->default(0);

            $table->decimal('price', 10, 2)->default(0);

            $table->string('status')->default('draft');

            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
