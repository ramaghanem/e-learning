<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        
        Schema::table('lessons', function (Blueprint $table) {
            // null = no time limit
            $table->unsignedInteger('quiz_time_limit_minutes')->nullable()->after('reading_time_minutes');

            // Percentage needed to pass
            $table->unsignedTinyInteger('quiz_passing_score')->default(70)->after('quiz_time_limit_minutes');

            // null = unlimited attempts
            $table->unsignedInteger('quiz_max_attempts')->nullable()->after('quiz_passing_score');

            // Students can review their answers on the results page
            $table->boolean('quiz_show_review')->default(true)->after('quiz_max_attempts');
        });
    }


    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->dropColumn([
                'quiz_time_limit_minutes',
                'quiz_passing_score',
                'quiz_max_attempts',
                'quiz_show_review',
            ]);
        });
    }
};
