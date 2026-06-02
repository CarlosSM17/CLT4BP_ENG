<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\StudentProfile;
use App\Models\StudentResponse;
use App\Models\User;

class StudentProfileGeneratorService
{
    private MslqProcessorService $mslqProcessor;
    private TestAnalyzerService $testAnalyzer;

    public function __construct(
        MslqProcessorService $mslqProcessor,
        TestAnalyzerService $testAnalyzer
    ) {
        $this->mslqProcessor = $mslqProcessor;
        $this->testAnalyzer = $testAnalyzer;
    }

    public function generateProfile(int $studentId, int $courseId): StudentProfile
    {
        // Get all required responses
        $mslqMotivation = $this->getAssessmentResponses(
            $studentId, $courseId, 'mslq_motivation_initial'
        );
        $mslqStrategies = $this->getAssessmentResponses(
            $studentId, $courseId, 'mslq_strategies'
        );
        $recallTest = $this->getAssessmentResponses(
            $studentId, $courseId, 'recall_initial'
        );
        $comprehensionTest = $this->getAssessmentResponses(
            $studentId, $courseId, 'comprehension_initial'
        );

        // Process full MSLQ
        $allMslqResponses = array_merge(
            $mslqMotivation['responses'] ?? [],
            $mslqStrategies['responses'] ?? []
        );
        $mslqAnalysis = $this->mslqProcessor->processResponses($allMslqResponses);

        // Analyze tests
        $recallAnalysis = $this->testAnalyzer->analyzeTest(
            $recallTest['responses'] ?? [],
            $recallTest['correct_answers'] ?? []
        );
        $comprehensionAnalysis = $this->testAnalyzer->analyzeTest(
            $comprehensionTest['responses'] ?? [],
            $comprehensionTest['correct_answers'] ?? []
        );
        $testsAnalysis = $this->testAnalyzer->analyzeBothTests(
            $recallAnalysis,
            $comprehensionAnalysis
        );

        // Extract all MSLQ scores flattened for the frontend
        $mslqScores = $this->flattenMslqScores($mslqAnalysis);

        // Build complete profile
        $profileData = [
            'student_info' => [
                'student_id' => $studentId,
                'course_id' => $courseId,
                'name' => User::find($studentId)->name
            ],
            'mslq_analysis' => $mslqAnalysis,
            'mslq_scores' => $mslqScores,
            'knowledge_assessment' => $testsAnalysis,
            'profile_summary' => $this->generateProfileSummary(
                $mslqAnalysis,
                $testsAnalysis
            ),
            'recommendations' => $this->generateRecommendations(
                $mslqAnalysis,
                $testsAnalysis
            )
        ];

        // Save or update profile
        return StudentProfile::updateOrCreate(
            [
                'student_id' => $studentId,
                'course_id' => $courseId
            ],
            [
                'profile_data' => $profileData,
                'generated_at' => now()
            ]
        );
    }

    private function getAssessmentResponses(
        int $studentId,
        int $courseId,
        string $assessmentType
    ): array {
        $assessment = Assessment::where('course_id', $courseId)
            ->where('assessment_type', $assessmentType)
            ->first();

        if (!$assessment) {
            return ['responses' => [], 'correct_answers' => []];
        }

        $response = StudentResponse::where('assessment_id', $assessment->id)
            ->where('student_id', $studentId)
            ->first();

        $correctAnswers = collect($assessment->questions ?? [])
            ->filter(fn($q) => isset($q['correct_answer']))
            ->mapWithKeys(fn($q) => [$q['id'] => $q['correct_answer']])
            ->toArray();

        return [
            'responses' => $response ? $response->responses : [],
            'correct_answers' => $correctAnswers,
        ];
    }

    private function generateProfileSummary(array $mslq, array $tests): array
    {
        return [
            'overall_motivation' => $mslq['summary']['overall_motivation_level'],
            'overall_strategies' => $mslq['summary']['overall_strategies_level'],
            'prior_knowledge' => $tests['overall_level'],
            'key_strengths' => array_merge(
                $mslq['summary']['strengths'],
                $tests['overall_level'] === 'high' ? ['prior_knowledge'] : []
            ),
            'areas_for_support' => array_merge(
                $mslq['summary']['weaknesses'],
                $tests['overall_level'] === 'low' ? ['prior_knowledge'] : []
            )
        ];
    }

    private function generateRecommendations(array $mslq, array $tests): array
    {
        $recommendations = [];

        // Recommendations based on motivation
        if ($mslq['summary']['overall_motivation_level'] === 'low') {
            $recommendations[] = 'Apply ARCS strategies with emphasis on Attention and Relevance';
            $recommendations[] = 'Include real-world examples and practical applications';
        }

        // Recommendations based on strategies
        if ($mslq['summary']['overall_strategies_level'] === 'low') {
            $recommendations[] = 'Provide explicit study strategy guides';
            $recommendations[] = 'Model metacognitive processes through verbal protocols';
        }

        // Recommendations based on prior knowledge
        if ($tests['overall_level'] === 'low') {
            $recommendations[] = 'Use CLT effects for novices: worked examples, completion problems';
            $recommendations[] = 'Minimize extraneous cognitive load';
        } elseif ($tests['overall_level'] === 'high') {
            $recommendations[] = 'Apply CLT effects for experts: self-explanation, imagination';
            $recommendations[] = 'Consider expertise reversal for some materials';
        }

        return $recommendations;
    }

    /**
     * Flattens MSLQ scores for easy frontend access
     */
    private function flattenMslqScores(array $mslqAnalysis): array
    {
        $flattened = [];

        // Extract motivation dimensions
        if (isset($mslqAnalysis['motivation'])) {
            foreach ($mslqAnalysis['motivation'] as $dimension => $data) {
                $flattened[$dimension] = [
                    'average' => $data['average'] ?? 0,
                    'level' => $data['level'] ?? 'low',
                    'raw_score' => $data['raw_score'] ?? 0,
                    'items_answered' => $data['items_answered'] ?? 0,
                    'items_total' => $data['items_total'] ?? 0,
                ];
            }
        }

        // Extract cognitive strategy dimensions
        if (isset($mslqAnalysis['strategies']['cognitive'])) {
            foreach ($mslqAnalysis['strategies']['cognitive'] as $dimension => $data) {
                $flattened[$dimension] = [
                    'average' => $data['average'] ?? 0,
                    'level' => $data['level'] ?? 'low',
                    'raw_score' => $data['raw_score'] ?? 0,
                    'items_answered' => $data['items_answered'] ?? 0,
                    'items_total' => $data['items_total'] ?? 0,
                ];
            }
        }

        // Extract metacognitive dimensions
        if (isset($mslqAnalysis['strategies']['metacognitive'])) {
            foreach ($mslqAnalysis['strategies']['metacognitive'] as $dimension => $data) {
                $flattened[$dimension] = [
                    'average' => $data['average'] ?? 0,
                    'level' => $data['level'] ?? 'low',
                    'raw_score' => $data['raw_score'] ?? 0,
                    'items_answered' => $data['items_answered'] ?? 0,
                    'items_total' => $data['items_total'] ?? 0,
                ];
            }
        }

        // Extract resource management dimensions
        if (isset($mslqAnalysis['strategies']['resource_management'])) {
            foreach ($mslqAnalysis['strategies']['resource_management'] as $dimension => $data) {
                $flattened[$dimension] = [
                    'average' => $data['average'] ?? 0,
                    'level' => $data['level'] ?? 'low',
                    'raw_score' => $data['raw_score'] ?? 0,
                    'items_answered' => $data['items_answered'] ?? 0,
                    'items_total' => $data['items_total'] ?? 0,
                ];
            }
        }

        return $flattened;
    }
}
