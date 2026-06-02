<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\Course;
use App\Models\StudentResponse;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class GradingService
{
    /**
     * Get pending grading responses for an assessment
     */
    public function getPendingGradingResponses(Assessment $assessment): Collection
    {
        return $assessment->responses()
            ->where('grading_status', 'pending_grading')
            ->whereNotNull('completed_at')
            ->with('student:id,name,email')
            ->get();
    }

    /**
     * Get pending grading summary for a course
     */
    public function getCoursePendingGradingSummary(Course $course): array
    {
        // Get all course assessments that may require manual grading
        $assessments = $course->assessments()
            ->where('is_template', false)
            ->withCount(['responses as pending_count' => function ($q) {
                $q->where('grading_status', 'pending_grading')
                  ->whereNotNull('completed_at');
            }])
            ->get()
            ->filter(function ($assessment) {
                // Filter assessments requiring manual grading or having open-ended questions
                return $assessment->requires_manual_grading || $assessment->hasOpenEndedQuestions();
            });

        $assessmentsWithPending = $assessments
            ->filter(fn($a) => $a->pending_count > 0)
            ->map(function ($assessment) {
                return [
                    'id' => $assessment->id,
                    'title' => $assessment->title,
                    'assessment_type' => $assessment->assessment_type,
                    'pending_count' => $assessment->pending_count,
                ];
            })
            ->values();

        return [
            'total_pending' => $assessmentsWithPending->sum('pending_count'),
            'assessments' => $assessmentsWithPending->toArray(),
        ];
    }

    /**
     * Submit manual grades for a response
     */
    public function submitGrades(
        StudentResponse $response,
        array $grades,
        int $graderId
    ): StudentResponse {
        $this->validateGradesStructure($grades, $response->assessment);

        $response->update([
            'manual_scores' => $grades,
            'grading_status' => 'graded',
            'graded_by' => $graderId,
            'graded_at' => now(),
        ]);

        $totalScore = $response->calculateTotalScore();
        $response->update(['score' => $totalScore]);

        return $response->fresh(['student', 'grader', 'assessment']);
    }

    /**
     * Validate that grades correspond to valid open-ended questions
     */
    private function validateGradesStructure(array $grades, Assessment $assessment): void
    {
        $openEndedIds = collect($assessment->questions)
            ->filter(fn($q) => in_array($q['type'] ?? '', ['text', 'essay', 'open_ended']))
            ->pluck('id')
            ->toArray();

        if (empty($openEndedIds)) {
            throw new InvalidArgumentException(
                'This assessment has no open-ended questions to grade'
            );
        }

        foreach ($grades as $grade) {
            if (!isset($grade['question_id']) || !isset($grade['score']) || !isset($grade['max_score'])) {
                throw new InvalidArgumentException(
                    'Each grade must have question_id, score and max_score'
                );
            }

            if (!in_array($grade['question_id'], $openEndedIds)) {
                throw new InvalidArgumentException(
                    "Question {$grade['question_id']} is not an open-ended question"
                );
            }

            if ($grade['score'] < 0 || $grade['score'] > $grade['max_score']) {
                throw new InvalidArgumentException(
                    "Invalid score for question {$grade['question_id']}: " .
                    "must be between 0 and {$grade['max_score']}"
                );
            }

            if ($grade['max_score'] <= 0) {
                throw new InvalidArgumentException(
                    "Maximum score must be greater than 0"
                );
            }
        }
    }

    /**
     * Determine grading status when a response is submitted
     */
    public function determineGradingStatus(Assessment $assessment): string
    {
        if ($assessment->requires_manual_grading) {
            return 'pending_grading';
        }

        if ($assessment->hasOpenEndedQuestions()) {
            return 'pending_grading';
        }

        return 'auto_graded';
    }

    /**
     * Get grading statistics for an assessment
     */
    public function getGradingStats(Assessment $assessment): array
    {
        $responses = $assessment->responses()->whereNotNull('completed_at');

        return [
            'total_completed' => $responses->count(),
            'auto_graded' => (clone $responses)->where('grading_status', 'auto_graded')->count(),
            'pending_grading' => (clone $responses)->where('grading_status', 'pending_grading')->count(),
            'graded' => (clone $responses)->where('grading_status', 'graded')->count(),
            'average_score' => $responses->whereNotNull('score')->avg('score'),
        ];
    }

    /**
     * Revert manual grading for a response
     */
    public function revertGrading(StudentResponse $response): StudentResponse
    {
        if ($response->grading_status !== 'graded') {
            throw new InvalidArgumentException('This response has not been manually graded');
        }

        $response->update([
            'manual_scores' => null,
            'grading_status' => 'pending_grading',
            'graded_by' => null,
            'graded_at' => null,
            'score' => null,
        ]);

        return $response->fresh();
    }

    /**
     * Recalculate grading status for all responses in a course.
     * Useful for updating responses created before the grading system was implemented.
     */
    public function recalculateGradingStatusForCourse(Course $course): array
    {
        $updated = 0;
        $assessments = $course->assessments()->get();

        foreach ($assessments as $assessment) {
            $shouldBePending = $assessment->requires_manual_grading || $assessment->hasOpenEndedQuestions();

            if ($shouldBePending) {
                // Update completed responses that are auto_graded but should be pending_grading
                $count = $assessment->responses()
                    ->whereNotNull('completed_at')
                    ->where(function ($query) {
                        $query->where('grading_status', 'auto_graded')
                              ->orWhereNull('grading_status');
                    })
                    ->whereNull('graded_at') // Not yet manually graded
                    ->update(['grading_status' => 'pending_grading']);

                $updated += $count;
            }
        }

        return [
            'updated_count' => $updated,
            'message' => "Updated {$updated} responses to pending grading status",
        ];
    }
}
