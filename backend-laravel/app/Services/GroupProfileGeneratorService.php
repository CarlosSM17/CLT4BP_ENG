<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\GroupProfile;
use App\Models\StudentProfile;
use App\Models\StudentResponse;
use App\Models\User;

class GroupProfileGeneratorService
{
    public function generateGroupProfile(int $courseId): GroupProfile
    {
        $studentProfiles = StudentProfile::where('course_id', $courseId)->get();

        if ($studentProfiles->isEmpty()) {
            throw new \Exception('No individual profiles found to generate group profile');
        }

        $groupData = [
            'mslq_averages' => $this->calculateMslqAverages($studentProfiles),
            'knowledge_averages' => $this->calculateKnowledgeAverages($studentProfiles),
            'distribution' => $this->calculateDistribution($studentProfiles),
            'group_summary' => $this->generateGroupSummary($studentProfiles),
            'teaching_recommendations' => $this->generateTeachingRecommendations($studentProfiles)
        ];

        return GroupProfile::updateOrCreate(
            ['course_id' => $courseId],
            [
                'profile_data' => $groupData,
                'student_count' => $studentProfiles->count(),
                'generated_at' => now()
            ]
        );
    }

    private function calculateMslqAverages($profiles): array
    {
        $dimensions = [
            'intrinsic_goal_orientation', 'extrinsic_goal_orientation',
            'task_value', 'control_beliefs', 'self_efficacy', 'test_anxiety',
            'rehearsal', 'elaboration', 'organization', 'critical_thinking',
            'metacognitive_self_regulation', 'time_management',
            'effort_regulation', 'peer_learning', 'help_seeking'
        ];

        $averages = [];

        foreach ($dimensions as $dimension) {
            $values = $profiles->map(function($profile) use ($dimension) {
                return $this->getDimensionValue($profile->profile_data, $dimension);
            })->filter()->values();

            if ($values->isNotEmpty()) {
                $average = $values->avg();
                $averages[$dimension] = [
                    'average' => round($average, 2),
                    'level' => $this->determineLevel($average)
                ];
            }
        }

        return $averages;
    }

    private function getDimensionValue(array $profileData, string $dimension): ?float
    {
        // Search in motivation
        if (isset($profileData['mslq_analysis']['motivation'][$dimension])) {
            return $profileData['mslq_analysis']['motivation'][$dimension]['average'];
        }

        // Search in strategies
        foreach (['cognitive', 'metacognitive', 'resource_management'] as $category) {
            if (isset($profileData['mslq_analysis']['strategies'][$category][$dimension])) {
                return $profileData['mslq_analysis']['strategies'][$category][$dimension]['average'];
            }
        }

        return null;
    }

    private function calculateKnowledgeAverages($profiles): array
    {
        $recallScores = $profiles->pluck('profile_data.knowledge_assessment.recall.percentage');
        $comprehensionScores = $profiles->pluck('profile_data.knowledge_assessment.comprehension.percentage');

        $avgRecall = $recallScores->avg();
        $avgComprehension = $comprehensionScores->avg();
        $overall = ($avgRecall + $avgComprehension) / 2;

        return [
            'recall' => [
                'average' => round($avgRecall, 2),
                'level' => $this->determineKnowledgeLevel($avgRecall)
            ],
            'comprehension' => [
                'average' => round($avgComprehension, 2),
                'level' => $this->determineKnowledgeLevel($avgComprehension)
            ],
            'overall' => [
                'average' => round($overall, 2),
                'level' => $this->determineKnowledgeLevel($overall)
            ]
        ];
    }

    private function calculateDistribution($profiles): array
    {
        $distribution = [
            'motivation_levels' => ['high' => 0, 'medium' => 0, 'low' => 0],
            'strategies_levels' => ['high' => 0, 'medium' => 0, 'low' => 0],
            'knowledge_levels' => ['high' => 0, 'medium' => 0, 'low' => 0]
        ];

        foreach ($profiles as $profile) {
            $data = $profile->profile_data;

            $motivationLevel = $data['profile_summary']['overall_motivation'] ?? 'medium';
            $strategiesLevel = $data['profile_summary']['overall_strategies'] ?? 'medium';
            $knowledgeLevel = $data['profile_summary']['prior_knowledge'] ?? 'medium';

            if (isset($distribution['motivation_levels'][$motivationLevel])) {
                $distribution['motivation_levels'][$motivationLevel]++;
            }
            if (isset($distribution['strategies_levels'][$strategiesLevel])) {
                $distribution['strategies_levels'][$strategiesLevel]++;
            }
            if (isset($distribution['knowledge_levels'][$knowledgeLevel])) {
                $distribution['knowledge_levels'][$knowledgeLevel]++;
            }
        }

        return $distribution;
    }

    private function generateGroupSummary($profiles): array
    {
        $distribution = $this->calculateDistribution($profiles);
        $total = $profiles->count();

        return [
            'predominant_motivation' => $this->getPredominantLevel($distribution['motivation_levels']),
            'predominant_strategies' => $this->getPredominantLevel($distribution['strategies_levels']),
            'predominant_knowledge' => $this->getPredominantLevel($distribution['knowledge_levels']),
            'group_characteristics' => $this->describeGroupCharacteristics($distribution, $total)
        ];
    }

    private function getPredominantLevel(array $levels): string
    {
        arsort($levels);
        return array_key_first($levels);
    }

    private function describeGroupCharacteristics(array $distribution, int $total): string
    {
        $motivationHigh = ($distribution['motivation_levels']['high'] / $total) * 100;
        $knowledgeLow = ($distribution['knowledge_levels']['low'] / $total) * 100;

        if ($motivationHigh > 60 && $knowledgeLow < 30) {
            return 'Highly motivated group with good prior knowledge';
        } elseif ($motivationHigh < 30 && $knowledgeLow > 50) {
            return 'Group requiring motivational support and foundational content';
        } elseif ($motivationHigh > 50) {
            return 'Motivated group with variable prior knowledge';
        } else {
            return 'Heterogeneous group requiring instructional differentiation';
        }
    }

    private function generateTeachingRecommendations($profiles): array
    {
        $distribution = $this->calculateDistribution($profiles);
        $recommendations = [];

        $predominantKnowledge = $this->getPredominantLevel($distribution['knowledge_levels']);

        if ($predominantKnowledge === 'low') {
            $recommendations[] = 'Prioritize CLT effects for novices in group material';
            $recommendations[] = 'Use worked examples and completion problems';
        } elseif ($predominantKnowledge === 'high') {
            $recommendations[] = 'Apply CLT effects for experts in group material';
            $recommendations[] = 'Encourage self-explanation and problem solving';
        } else {
            $recommendations[] = 'Combine CLT effects for different levels';
            $recommendations[] = 'Consider individual material for extreme cases';
        }

        $predominantMotivation = $this->getPredominantLevel($distribution['motivation_levels']);

        if ($predominantMotivation === 'low') {
            $recommendations[] = 'Emphasize ARCS strategies throughout all material';
            $recommendations[] = 'Include multiple relevant examples and practical applications';
        }

        return $recommendations;
    }

    private function determineLevel(float $average): string
    {
        if ($average >= 5.5) return 'high';
        if ($average >= 3.5) return 'medium';
        return 'low';
    }

    private function determineKnowledgeLevel(float $percentage): string
    {
        if ($percentage >= 75) return 'high';
        if ($percentage >= 50) return 'medium';
        return 'low';
    }
}
