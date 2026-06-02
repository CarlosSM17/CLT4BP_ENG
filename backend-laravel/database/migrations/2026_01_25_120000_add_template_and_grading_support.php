<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add template and manual grading fields to assessments
        Schema::table('assessments', function (Blueprint $table) {
            $table->boolean('is_template')->default(false)->after('time_limit');
            $table->boolean('requires_manual_grading')->default(false)->after('is_template');
            $table->foreignId('source_template_id')
                ->nullable()
                ->after('requires_manual_grading')
                ->constrained('assessments')
                ->nullOnDelete();
        });

        // Make course_id nullable (for templates)
        Schema::table('assessments', function (Blueprint $table) {
            $table->foreignId('course_id')->nullable()->change();
        });

        // Add manual grading fields to student_responses
        Schema::table('student_responses', function (Blueprint $table) {
            $table->string('grading_status')->default('auto_graded')->after('completed_at');
            $table->json('manual_scores')->nullable()->after('grading_status');
            $table->foreignId('graded_by')
                ->nullable()
                ->after('manual_scores')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('graded_at')->nullable()->after('graded_by');
        });

        // Add index for grading_status
        Schema::table('student_responses', function (Blueprint $table) {
            $table->index('grading_status');
        });

        // Add index for templates
        Schema::table('assessments', function (Blueprint $table) {
            $table->index('is_template');
        });
    }

    public function down(): void
    {
        Schema::table('student_responses', function (Blueprint $table) {
            $table->dropIndex(['grading_status']);
            $table->dropForeign(['graded_by']);
            $table->dropColumn(['grading_status', 'manual_scores', 'graded_by', 'graded_at']);
        });

        Schema::table('assessments', function (Blueprint $table) {
            $table->dropIndex(['is_template']);
            $table->dropForeign(['source_template_id']);
            $table->dropColumn(['is_template', 'requires_manual_grading', 'source_template_id']);
        });

        // Revert course_id to non-nullable
        Schema::table('assessments', function (Blueprint $table) {
            $table->foreignId('course_id')->nullable(false)->change();
        });
    }
};
