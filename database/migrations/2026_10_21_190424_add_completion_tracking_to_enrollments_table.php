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
        Schema::table('enrollments', function (Blueprint $table) {
            // Set when every lesson is completed (the date on the certificate).
            $table->timestamp('completed_at')->nullable()->after('status');

            // Updated each time the student opens the learning page.
            $table->timestamp('last_accessed_at')->nullable()->after('completed_at');

            // Generated once, the first time the course is completed.
            $table->string('certificate_code', 20)->nullable()->unique()->after('last_accessed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropUnique(['certificate_code']);
            $table->dropColumn(['completed_at', 'last_accessed_at', 'certificate_code']);
        });
    }
};
