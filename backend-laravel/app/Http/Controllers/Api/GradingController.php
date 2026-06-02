<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\Course;
use App\Models\StudentResponse;
use App\Services\GradingService;
use Illuminate\Http\Request;
use InvalidArgumentException;

class GradingController extends Controller
{
    private GradingService $gradingService;

    public function __construct(GradingService $gradingService)
    {
        $this->gradingService = $gradingService;
    }

    /**
     * GET /courses/{course}/pending-grading
     * Summary of pending grading assessments in a course
     */
    public function coursePendingGrading(Request $request, Course $course)
    {
        if (!$this->canManageCourse($request->user(), $course)) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }

        $summary = $this->gradingService->getCoursePendingGradingSummary($course);

        return response()->json([
            'course' => [
                'id' => $course->id,
                'title' => $course->title,
            ],
            'grading_summary' => $summary,
        ]);
    }

    /**
     * GET /courses/{course}/assessments/{assessment}/pending-grading
     * List pending grading responses for an assessment
     */
    public function pendingGrading(Request $request, Course $course, Assessment $assessment)
    {
        if (!$this->canManageCourse($request->user(), $course)) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }

        if ($assessment->course_id !== $course->id) {
            return response()->json([
                'message' => 'Assessment not found in this course',
            ], 404);
        }

        $responses = $this->gradingService->getPendingGradingResponses($assessment);

        $openEndedQuestions = collect($assessment->questions)
            ->filter(fn($q) => in_array($q['type'] ?? '', ['text', 'essay', 'open_ended']))
            ->values();

        $stats = $this->gradingService->getGradingStats($assessment);

        return response()->json([
            'assessment' => [
                'id' => $assessment->id,
                'title' => $assessment->title,
                'assessment_type' => $assessment->assessment_type,
            ],
            'open_ended_questions' => $openEndedQuestions,
            'pending_responses' => $responses,
            'count' => $responses->count(),
            'stats' => $stats,
        ]);
    }

    /**
     * GET /courses/{course}/assessments/{assessment}/responses/{response}
     * Get a specific response for grading
     */
    public function showResponse(
        Request $request,
        Course $course,
        Assessment $assessment,
        StudentResponse $response
    ) {
        if (!$this->canManageCourse($request->user(), $course)) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }

        if ($assessment->course_id !== $course->id) {
            return response()->json([
                'message' => 'Assessment not found in this course',
            ], 404);
        }

        if ($response->assessment_id !== $assessment->id) {
            return response()->json([
                'message' => 'Response not found for this assessment',
            ], 404);
        }

        $response->load(['student:id,name,email', 'grader:id,name', 'assessment']);

        $openEndedQuestions = collect($assessment->questions)
            ->filter(fn($q) => in_array($q['type'] ?? '', ['text', 'essay', 'open_ended']))
            ->map(function ($question) use ($response) {
                return [
                    'question' => $question,
                    'student_answer' => $response->responses[$question['id']] ?? null,
                    'manual_score' => collect($response->manual_scores ?? [])
                        ->firstWhere('question_id', $question['id']),
                ];
            })
            ->values();

        return response()->json([
            'response' => $response,
            'open_ended_details' => $openEndedQuestions,
        ]);
    }

    /**
     * POST /courses/{course}/assessments/{assessment}/responses/{response}/grade
     * Submit manual grades for a response
     */
    public function submitGrade(
        Request $request,
        Course $course,
        Assessment $assessment,
        StudentResponse $response
    ) {
        if (!$this->canManageCourse($request->user(), $course)) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }

        if ($assessment->course_id !== $course->id) {
            return response()->json([
                'message' => 'Assessment not found in this course',
            ], 404);
        }

        if ($response->assessment_id !== $assessment->id) {
            return response()->json([
                'message' => 'Response not found for this assessment',
            ], 404);
        }

        $validated = $request->validate([
            'grades' => 'required|array|min:1',
            'grades.*.question_id' => 'required|string',
            'grades.*.score' => 'required|numeric|min:0',
            'grades.*.max_score' => 'required|numeric|min:1',
            'grades.*.feedback' => 'nullable|string|max:1000',
        ]);

        try {
            $gradedResponse = $this->gradingService->submitGrades(
                $response,
                $validated['grades'],
                $request->user()->id
            );

            return response()->json([
                'message' => 'Grade saved successfully',
                'response' => $gradedResponse,
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'message' => 'Validation error',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * POST /courses/{course}/assessments/{assessment}/responses/{response}/revert-grade
     * Revert manual grade for a response
     */
    public function revertGrade(
        Request $request,
        Course $course,
        Assessment $assessment,
        StudentResponse $response
    ) {
        if (!$this->canManageCourse($request->user(), $course)) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }

        if ($assessment->course_id !== $course->id || $response->assessment_id !== $assessment->id) {
            return response()->json([
                'message' => 'Resource not found',
            ], 404);
        }

        try {
            $revertedResponse = $this->gradingService->revertGrading($response);

            return response()->json([
                'message' => 'Grade reverted successfully',
                'response' => $revertedResponse,
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'message' => 'Error',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * POST /courses/{course}/recalculate-grading
     * Recalculate grading status for all course responses
     */
    public function recalculateGradingStatus(Request $request, Course $course)
    {
        if (!$this->canManageCourse($request->user(), $course)) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }

        $result = $this->gradingService->recalculateGradingStatusForCourse($course);

        return response()->json([
            'message' => $result['message'],
            'updated_count' => $result['updated_count'],
        ]);
    }

    /**
     * Check if user can manage the course
     */
    private function canManageCourse($user, Course $course): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isInstructor() && $course->instructor_id === $user->id) {
            return true;
        }

        return false;
    }
}
