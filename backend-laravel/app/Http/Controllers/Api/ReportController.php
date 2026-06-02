<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\Course;
use App\Models\GroupProfile;
use App\Models\StudentProfile;
use App\Models\StudentResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ReportController extends Controller
{
    /**
     * Instructor report dashboard (complete group data)
     */
    public function instructorReport(Request $request, int $courseId)
    {
        $course = Course::findOrFail($courseId);

        // Verify permissions
        if (!$request->user()->isInstructor() && !$request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Load group profile
        $groupProfile = GroupProfile::where('course_id', $courseId)->first();
        $groupProfileData = null;
        if ($groupProfile) {
            $groupProfileData = array_merge($groupProfile->profile_data, [
                'student_count' => $groupProfile->student_count,
                'generated_at'  => $groupProfile->generated_at,
            ]);
        }

        // Load individual profiles
        $studentProfiles = StudentProfile::where('course_id', $courseId)
            ->with('student:id,name,email')
            ->get()
            ->map(function ($profile) {
                $data = $profile->profile_data ?? [];
                return [
                    'student_id'         => $profile->student_id,
                    'name'               => $profile->student->name ?? 'N/A',
                    'overall_motivation' => $data['profile_summary']['overall_motivation'] ?? null,
                    'overall_strategies' => $data['profile_summary']['overall_strategies'] ?? null,
                    'prior_knowledge'    => $data['profile_summary']['prior_knowledge'] ?? null,
                    'mslq_scores'        => $data['mslq_scores'] ?? [],
                    'knowledge'          => $data['knowledge_assessment'] ?? [],
                    'generated_at'       => $profile->generated_at,
                ];
            });

        // Compute scores for additional assessments (all students)
        $cognitiveLoad  = $this->computeAssessmentScores($courseId, 'cognitive_load');
        $courseInterest = $this->computeAssessmentScores($courseId, 'course_interest');
        $imms           = $this->computeAssessmentScores($courseId, 'imms');

        // Group pre/post comparison
        $prePost = $this->computeGroupPrePost($courseId);

        return response()->json([
            'group_profile'   => $groupProfileData,
            'cognitive_load'  => $cognitiveLoad,
            'course_interest' => $courseInterest,
            'imms'            => $imms,
            'pre_post'        => $prePost,
            'student_profiles' => $studentProfiles,
        ]);
    }

    /**
     * Student personal report
     */
    public function studentReport(Request $request, int $courseId)
    {
        $course  = Course::findOrFail($courseId);
        $student = $request->user();

        // Verify enrollment
        $isEnrolled = $course->students()->where('student_id', $student->id)->exists();
        if (!$isEnrolled) {
            return response()->json(['message' => 'You are not enrolled in this course'], 403);
        }

        // Load individual profile
        $studentProfile = StudentProfile::where('course_id', $courseId)
            ->where('student_id', $student->id)
            ->first();

        if (!$studentProfile) {
            return response()->json([
                'has_profile'   => false,
                'message'       => 'Your profile has not been generated yet. Complete the initial assessments.',
            ]);
        }

        $profileData = $studentProfile->profile_data ?? [];

        // Group averages for comparison
        $groupProfile    = GroupProfile::where('course_id', $courseId)->first();
        $groupAverages   = $groupProfile ? ($groupProfile->profile_data['mslq_averages'] ?? []) : [];

        // Additional student scores
        $cognitiveLoad  = $this->computeAssessmentScores($courseId, 'cognitive_load', $student->id);
        $courseInterest = $this->computeAssessmentScores($courseId, 'course_interest', $student->id);
        $imms           = $this->computeAssessmentScores($courseId, 'imms', $student->id);

        // Pre/post personal
        $prePost = $this->computeStudentPrePost($courseId, $student->id);

        return response()->json([
            'has_profile'    => true,
            'profile'        => [
                'mslq_scores'        => $profileData['mslq_scores'] ?? [],
                'knowledge'          => $profileData['knowledge_assessment'] ?? [],
                'profile_summary'    => $profileData['profile_summary'] ?? [],
                'recommendations'    => $profileData['recommendations'] ?? [],
            ],
            'group_averages'  => $groupAverages,
            'cognitive_load'  => $cognitiveLoad,
            'course_interest' => $courseInterest,
            'imms'            => $imms,
            'pre_post'        => $prePost,
        ]);
    }

    /**
     * Computes dimension averages for a Likert-type assessment for a course.
     * If $studentId is null, averages over all students.
     */
    private function computeAssessmentScores(int $courseId, string $type, ?int $studentId = null): array
    {
        $assessment = Assessment::where('course_id', $courseId)
            ->where('assessment_type', $type)
            ->where('is_template', false)
            ->first();

        if (!$assessment || empty($assessment->questions)) {
            return ['available' => false];
        }

        // Get responses
        $query = StudentResponse::where('assessment_id', $assessment->id);
        if ($studentId !== null) {
            $query->where('student_id', $studentId);
        }
        $responses = $query->whereNotNull('responses')->get();

        if ($responses->isEmpty()) {
            return ['available' => false];
        }

        // Group questions by dimension
        $questionsByDimension = collect($assessment->questions)
            ->groupBy('dimension')
            ->filter(fn($qs, $dim) => !empty($dim));

        // Accumulators by dimension
        $dimensionTotals = [];
        $dimensionCounts = [];
        $overallTotal    = 0;
        $overallCount    = 0;

        foreach ($responses as $response) {
            $answers = $response->responses ?? [];
            foreach ($questionsByDimension as $dim => $questions) {
                $dimTotal = 0;
                $dimCount = 0;
                foreach ($questions as $q) {
                    $qId = $q['id'] ?? null;
                    if ($qId !== null && isset($answers[$qId]) && is_numeric($answers[$qId])) {
                        $dimTotal += (float) $answers[$qId];
                        $dimCount++;
                        $overallTotal++;
                        $overallCount++;
                    }
                }
                if ($dimCount > 0) {
                    $dimensionTotals[$dim] = ($dimensionTotals[$dim] ?? 0) + ($dimTotal / $dimCount);
                    $dimensionCounts[$dim] = ($dimensionCounts[$dim] ?? 0) + 1;
                }
            }
        }

        // Calculate final averages
        $dimensionAverages = [];
        foreach ($dimensionTotals as $dim => $total) {
            $count = $dimensionCounts[$dim];
            $dimensionAverages[$dim] = round($total / $count, 2);
        }

        $overallAvg = $overallCount > 0 ? round($overallTotal / $overallCount, 2) : 0;

        return [
            'available'   => true,
            'dimensions'  => $dimensionAverages,
            'overall'     => $overallAvg,
            'respondents' => $responses->count(),
        ];
    }

    /**
     * Computes pre/post comparison for the complete group.
     * Pairs: recall, comprehension, mslq_motivation
     */
    private function computeGroupPrePost(int $courseId): array
    {
        $pairs = [
            'recall'          => ['initial' => 'recall_initial',          'final' => 'recall_final'],
            'comprehension'   => ['initial' => 'comprehension_initial',   'final' => 'comprehension_final'],
            'mslq_motivation' => ['initial' => 'mslq_motivation_initial', 'final' => 'mslq_motivation_final'],
        ];

        $result = [];

        foreach ($pairs as $key => $pair) {
            $initialAssessment = Assessment::where('course_id', $courseId)
                ->where('assessment_type', $pair['initial'])
                ->where('is_template', false)
                ->first();

            $finalAssessment = Assessment::where('course_id', $courseId)
                ->where('assessment_type', $pair['final'])
                ->where('is_template', false)
                ->first();

            if (!$initialAssessment || !$finalAssessment) {
                $result[$key] = ['available' => false];
                continue;
            }

            $initialScores = StudentResponse::where('assessment_id', $initialAssessment->id)
                ->whereNotNull('score')
                ->pluck('score', 'student_id');

            $finalScores = StudentResponse::where('assessment_id', $finalAssessment->id)
                ->whereNotNull('score')
                ->pluck('score', 'student_id');

            // Only students with both responses
            $commonIds = $initialScores->keys()->intersect($finalScores->keys());

            if ($commonIds->isEmpty()) {
                $result[$key] = ['available' => false];
                continue;
            }

            $initialAvg = $commonIds->map(fn($id) => (float) $initialScores[$id])->avg();
            $finalAvg   = $commonIds->map(fn($id) => (float) $finalScores[$id])->avg();

            $result[$key] = [
                'available'   => true,
                'initial_avg' => round($initialAvg, 2),
                'final_avg'   => round($finalAvg, 2),
                'change'      => round($finalAvg - $initialAvg, 2),
                'students'    => $commonIds->count(),
            ];
        }

        return $result;
    }

    /**
     * Computes pre/post comparison for a specific student.
     */
    private function computeStudentPrePost(int $courseId, int $studentId): array
    {
        $pairs = [
            'recall'        => ['initial' => 'recall_initial',        'final' => 'recall_final'],
            'comprehension' => ['initial' => 'comprehension_initial', 'final' => 'comprehension_final'],
        ];

        $result = [];

        foreach ($pairs as $key => $pair) {
            $initialAssessment = Assessment::where('course_id', $courseId)
                ->where('assessment_type', $pair['initial'])
                ->where('is_template', false)
                ->first();

            $finalAssessment = Assessment::where('course_id', $courseId)
                ->where('assessment_type', $pair['final'])
                ->where('is_template', false)
                ->first();

            if (!$initialAssessment || !$finalAssessment) {
                $result[$key] = ['available' => false];
                continue;
            }

            $initialResponse = StudentResponse::where('assessment_id', $initialAssessment->id)
                ->where('student_id', $studentId)
                ->first();

            $finalResponse = StudentResponse::where('assessment_id', $finalAssessment->id)
                ->where('student_id', $studentId)
                ->first();

            if (!$initialResponse) {
                $result[$key] = ['available' => false];
                continue;
            }

            $initial = (float) ($initialResponse->score ?? 0);

            if (!$finalResponse) {
                $result[$key] = [
                    'available' => false,
                    'initial'   => round($initial, 2),
                ];
                continue;
            }

            $final = (float) ($finalResponse->score ?? 0);

            $result[$key] = [
                'available' => true,
                'initial'   => round($initial, 2),
                'final'     => round($final, 2),
                'change'    => round($final - $initial, 2),
            ];
        }

        return $result;
    }
}
