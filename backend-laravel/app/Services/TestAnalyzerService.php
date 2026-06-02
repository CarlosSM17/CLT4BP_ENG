<?php

namespace App\Services;

class TestAnalyzerService
{
    public function analyzeTest(array $responses, array $correctAnswers): array
    {
        $totalQuestions = count($correctAnswers);
        $correctCount = 0;
        $incorrectQuestions = [];

        // If no questions with correct answers, return default values
        if ($totalQuestions === 0) {
            return [
                'total_questions' => 0,
                'correct_answers' => 0,
                'incorrect_answers' => 0,
                'percentage' => 0,
                'level' => 'no_data',
                'incorrect_questions' => []
            ];
        }

        foreach ($correctAnswers as $questionId => $correctAnswer) {
            if (isset($responses[$questionId])) {
                if ($responses[$questionId] === $correctAnswer) {
                    $correctCount++;
                } else {
                    $incorrectQuestions[] = $questionId;
                }
            }
        }

        $percentage = ($correctCount / $totalQuestions) * 100;

        return [
            'total_questions' => $totalQuestions,
            'correct_answers' => $correctCount,
            'incorrect_answers' => $totalQuestions - $correctCount,
            'percentage' => round($percentage, 2),
            'level' => $this->determineKnowledgeLevel($percentage),
            'incorrect_questions' => $incorrectQuestions
        ];
    }

    private function determineKnowledgeLevel(float $percentage): string
    {
        if ($percentage >= 75) return 'high';
        if ($percentage >= 50) return 'medium';
        return 'low';
    }

    public function analyzeBothTests(array $recallResults, array $comprehensionResults): array
    {
        $overallPercentage = ($recallResults['percentage'] + $comprehensionResults['percentage']) / 2;

        return [
            'recall' => $recallResults,
            'comprehension' => $comprehensionResults,
            'overall_level' => $this->determineKnowledgeLevel($overallPercentage),
            'overall_percentage' => round($overallPercentage, 2),
            'analysis' => $this->generateAnalysis($recallResults, $comprehensionResults)
        ];
    }

    private function generateAnalysis(array $recall, array $comprehension): string
    {
        if ($recall['level'] === 'high' && $comprehension['level'] === 'high') {
            return 'The student demonstrates excellent prior knowledge in both recalling and understanding information.';
        }

        if ($recall['level'] === 'low' && $comprehension['level'] === 'low') {
            return 'The student requires foundational instruction in both aspects: recall and comprehension.';
        }

        if ($recall['level'] > $comprehension['level']) {
            return 'The student can recall information but needs support in deep comprehension.';
        }

        return 'The student understands concepts but needs to reinforce the ability to recall details.';
    }
}
