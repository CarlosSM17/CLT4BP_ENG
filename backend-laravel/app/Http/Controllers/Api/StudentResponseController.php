<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\Course;
use App\Models\StudentResponse;
use App\Services\GradingService;
use Illuminate\Http\Request;

class StudentResponseController extends Controller
{
    /**
     * Start assessment (create start record)
     */
    public function start(Request $request, Course $course, Assessment $assessment)
    {
        // Verify user is a student
        if (!$request->user()->isStudent()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Verify enrollment in the course
        $isEnrolled = $course->students()->where('student_id', $request->user()->id)->exists();
        if (!$isEnrolled) {
            return response()->json(['message' => 'You are not enrolled in this course'], 403);
        }

        // Verify assessment is active
        if (!$assessment->is_active) {
            return response()->json(['message' => 'This assessment is not available'], 403);
        }

        // Check if a response already exists
        $existingResponse = StudentResponse::where('assessment_id', $assessment->id)
            ->where('student_id', $request->user()->id)
            ->first();

        if ($existingResponse) {
            if ($existingResponse->completed_at) {
                return response()->json([
                    'message' => 'You have already completed this assessment',
                    'response' => $existingResponse,
                ], 400);
            }

            // Exists but not completed, continue with it
            return response()->json([
                'message' => 'Continuing assessment',
                'response' => $existingResponse,
            ]);
        }

        // Create new response
        $response = StudentResponse::create([
            'assessment_id' => $assessment->id,
            'student_id' => $request->user()->id,
            'responses' => [],
            'started_at' => now(),
        ]);

        return response()->json([
            'message' => 'Assessment started',
            'response' => $response,
        ], 201);
    }

    /**
     * Save response (can be partial or complete)
     */
    public function save(Request $request, Course $course, Assessment $assessment)
    {
        // Verify user is a student
        if (!$request->user()->isStudent()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'responses' => 'required|array',
            'is_final' => 'sometimes|boolean',
        ]);

        // Find or create response
        $studentResponse = StudentResponse::where('assessment_id', $assessment->id)
            ->where('student_id', $request->user()->id)
            ->first();

        if (!$studentResponse) {
            $studentResponse = StudentResponse::create([
                'assessment_id' => $assessment->id,
                'student_id' => $request->user()->id,
                'responses' => $validated['responses'],
                'started_at' => now(),
            ]);
        } else {
            $studentResponse->update([
                'responses' => $validated['responses'],
            ]);
        }

        // If final submission
        if (isset($validated['is_final']) && $validated['is_final']) {
            $timeSpent = $studentResponse->started_at
                ? (int) now()->diffInSeconds($studentResponse->started_at)
                : null;

            // Determine grading status
            $gradingService = app(GradingService::class);
            $gradingStatus = $gradingService->determineGradingStatus($assessment);

            $studentResponse->update([
                'completed_at' => now(),
                'time_spent' => $timeSpent,
                'grading_status' => $gradingStatus,
            ]);

            // Calculate score for auto-gradable questions (multiple choice with correct_answer)
            // Always calculated: for auto_graded it's the final score,
            // for pending_grading it's a partial score from the MC section
            // that will be overwritten by calculateTotalScore() when the instructor grades
            $score = $studentResponse->calculateScore();
            if ($score !== null) {
                $studentResponse->update(['score' => $score]);
            }

            $responseMessage = $gradingStatus === 'pending_grading'
                ? 'Assessment completed. Pending instructor grading.'
                : 'Assessment completed';

            return response()->json([
                'message' => $responseMessage,
                'response' => $studentResponse->fresh(),
                'grading_status' => $gradingStatus,
            ]);
        }

        return response()->json([
            'message' => 'Response saved',
            'response' => $studentResponse,
        ]);
    }

    /**
     * Get student response
     */
    public function show(Request $request, Course $course, Assessment $assessment)
    {
        $response = StudentResponse::where('assessment_id', $assessment->id)
            ->where('student_id', $request->user()->id)
            ->with('assessment')
            ->first();

        if (!$response) {
            return response()->json(['message' => 'This assessment has not been started'], 404);
        }

        return response()->json([
            'response' => $response,
        ]);
    }

    /**
     * List all student responses in a course
     */
    public function myResponses(Request $request, Course $course)
    {
        $responses = StudentResponse::whereHas('assessment', function($query) use ($course) {
                $query->where('course_id', $course->id);
            })
            ->where('student_id', $request->user()->id)
            ->with('assessment')
            ->get();

        return response()->json([
            'responses' => $responses,
        ]);
    }

    /**
     * View all responses for an assessment (instructor)
     */
    public function assessmentResponses(Request $request, Course $course, Assessment $assessment)
    {
        // Verify permissions
        if (!$request->user()->isInstructor() && !$request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $responses = StudentResponse::where('assessment_id', $assessment->id)
            ->with('student:id,name,email')
            ->get();

        return response()->json([
            'responses' => $responses,
            'stats' => [
                'total' => $responses->count(),
                'completed' => $responses->whereNotNull('completed_at')->count(),
                'average_score' => $responses->whereNotNull('score')->avg('score'),
                'average_time' => $responses->whereNotNull('time_spent')->avg('time_spent'),
            ],
        ]);
    }
}
