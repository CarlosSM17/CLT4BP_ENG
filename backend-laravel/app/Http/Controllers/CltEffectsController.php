<?php

namespace App\Http\Controllers;

use App\Models\CltEffectsSelection;
use App\Models\Course;
use App\Services\AiServiceClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CltEffectsController extends Controller
{
    private AiServiceClient $aiService;

    // Static CLT effects definition (fallback if Python service is unavailable)
    private const CLT_EFFECTS = [
        // Effects for New Knowledge
        [
            'id' => 'goal_free',
            'name' => 'Goal-Free Effect',
            'description' => 'Remove specific goals to reduce cognitive load and allow exploration',
            'category' => 'new_knowledge',
            'application_guidance' => 'Present problems without specific objectives, allowing the student to explore different solutions'
        ],
        [
            'id' => 'worked_example',
            'name' => 'Worked Example Effect',
            'description' => 'Show fully solved examples with step-by-step explanations',
            'category' => 'new_knowledge',
            'application_guidance' => 'Include fully worked examples before similar tasks'
        ],
        [
            'id' => 'completion_problem',
            'name' => 'Completion Problem Effect',
            'description' => 'Provide partially solved problems to complete',
            'category' => 'new_knowledge',
            'application_guidance' => 'Give partially started problems that the student must complete'
        ],
        [
            'id' => 'split_attention',
            'name' => 'Split Attention Effect',
            'description' => 'Spatially integrate related information to avoid attention splitting',
            'category' => 'new_knowledge',
            'application_guidance' => 'Keep explanatory text close to related diagrams/code'
        ],
        [
            'id' => 'modality',
            'name' => 'Modality Effect',
            'description' => 'Use a combination of presentation modes (visual + auditory)',
            'category' => 'new_knowledge',
            'application_guidance' => 'Combine textual explanations with verbal descriptions when possible'
        ],
        [
            'id' => 'redundancy',
            'name' => 'Redundancy Effect',
            'description' => 'Avoid redundant information that adds no value',
            'category' => 'new_knowledge',
            'application_guidance' => 'Eliminate duplicate information; present each concept once clearly'
        ],
        [
            'id' => 'variability',
            'name' => 'Variability Effect',
            'description' => 'Use multiple varied examples of the same concept',
            'category' => 'new_knowledge',
            'application_guidance' => 'Provide several examples showing different applications of the same concept'
        ],
        [
            'id' => 'isolated_elements',
            'name' => 'Isolated Elements Effect',
            'description' => 'Present complex elements in isolation first',
            'category' => 'new_knowledge',
            'application_guidance' => 'Introduce complex concepts element by element before combining them'
        ],
        [
            'id' => 'element_interactivity',
            'name' => 'Element Interactivity Effect',
            'description' => 'Manage interactivity between information elements',
            'category' => 'new_knowledge',
            'application_guidance' => 'Structure content to minimize elements that must be processed simultaneously'
        ],
        // Effects for Prior Knowledge
        [
            'id' => 'self_explanation',
            'name' => 'Self-Explanation Effect',
            'description' => 'Encourage students to explain concepts in their own words',
            'category' => 'prior_knowledge',
            'application_guidance' => 'Include questions that require the student to explain the why and how'
        ],
        [
            'id' => 'imagination',
            'name' => 'Imagination Effect',
            'description' => 'Ask students to imagine or visualize procedures',
            'category' => 'prior_knowledge',
            'application_guidance' => 'Ask the student to mentally visualize processes before executing them'
        ],
        [
            'id' => 'expertise_reversal',
            'name' => 'Expertise Reversal Effect',
            'description' => 'Reduce explicit guidance for students with prior knowledge',
            'category' => 'prior_knowledge',
            'application_guidance' => 'Minimize step-by-step instructions; provide more open-ended problems'
        ],
        [
            'id' => 'guidance_fading',
            'name' => 'Guidance-Fading Effect',
            'description' => 'Gradually reduce the level of guidance provided',
            'category' => 'prior_knowledge',
            'application_guidance' => 'Start with full guidance and progressively reduce support'
        ],
        [
            'id' => 'collective_memory',
            'name' => 'Collective Memory Effect',
            'description' => 'Leverage shared knowledge in group activities',
            'category' => 'prior_knowledge',
            'application_guidance' => 'Design activities that allow students to share prior knowledge'
        ],
        [
            'id' => 'self_management',
            'name' => 'Self-Management Effect',
            'description' => 'Encourage students to manage their own learning',
            'category' => 'prior_knowledge',
            'application_guidance' => 'Give the student autonomy in selecting strategies and learning sequences'
        ],
        [
            'id' => 'human_movement',
            'name' => 'Human Movement Effect',
            'description' => 'Incorporate activities that involve physical movement',
            'category' => 'prior_knowledge',
            'application_guidance' => 'Suggest practical activities requiring movement or physical manipulation'
        ],
        [
            'id' => 'transient_information',
            'name' => 'Transient Information Effect',
            'description' => 'Manage transient information to avoid overload',
            'category' => 'prior_knowledge',
            'application_guidance' => 'Provide permanent references for information that disappears quickly'
        ],
    ];

    public function __construct(AiServiceClient $aiService)
    {
        $this->aiService = $aiService;
    }

    /**
     * Check if user can manage the course
     */
    private function canManageCourse(int $courseId): bool
    {
        $user = Auth::user();
        if (!$user) return false;

        if ($user->role === 'admin') return true;

        if ($user->role === 'instructor') {
            $course = Course::find($courseId);
            return $course && $course->instructor_id === $user->id;
        }

        return false;
    }

    /**
     * Get all available CLT effects
     */
    public function getAvailableEffects()
    {
        // Try to get from Python service, fall back to local definition if it fails
        try {
            $effects = $this->aiService->getCltEffects();
            return response()->json([
                'success' => true,
                'data' => $effects
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => true,
                'data' => [
                    'effects' => self::CLT_EFFECTS,
                    'categories' => [
                        'new_knowledge' => 'Effects for New Knowledge',
                        'prior_knowledge' => 'Effects for Prior Knowledge'
                    ]
                ]
            ]);
        }
    }

    /**
     * Save CLT effect selection for a course
     */
    public function saveSelection(Request $request, int $courseId)
    {
        if (!$this->canManageCourse($courseId)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'selected_effects' => 'required|array|min:1',
            'selected_effects.*' => 'required|string',
            'notes' => 'nullable|string'
        ]);

        $selection = CltEffectsSelection::updateOrCreate(
            ['course_id' => $courseId],
            [
                'selected_effects' => $validated['selected_effects'],
                'notes' => $validated['notes'] ?? null
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Selection saved successfully',
            'data' => $selection
        ]);
    }

    /**
     * Get CLT effect selection for a course
     */
    public function getSelection(int $courseId)
    {
        if (!$this->canManageCourse($courseId)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $selection = CltEffectsSelection::where('course_id', $courseId)->first();

        if (!$selection) {
            return response()->json([
                'success' => true,
                'data' => null,
                'message' => 'No selection saved'
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $selection
        ]);
    }

    /**
     * Get CLT effect recommendations based on group profile
     */
    public function getRecommendations(int $courseId)
    {
        $this->authorize('manage', Course::findOrFail($courseId));

        try {
            $groupProfile = \App\Models\GroupProfile::where('course_id', $courseId)->first();

            if (!$groupProfile) {
                return response()->json([
                    'success' => false,
                    'message' => 'Generate the group profile first'
                ], 400);
            }

            $recommendations = $this->generateRecommendations($groupProfile);

            return response()->json([
                'success' => true,
                'data' => $recommendations
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error generating recommendations: ' . $e->getMessage()
            ], 500);
        }
    }

    private function generateRecommendations($groupProfile): array
    {
        $profileData = $groupProfile->profile_data;
        $knowledgeLevel = $profileData['group_summary']['predominant_knowledge'];

        $recommendations = [];

        if ($knowledgeLevel === 'low') {
            $recommendations = [
                'worked_example',
                'completion_problem',
                'split_attention',
                'redundancy',
                'isolated_elements'
            ];
        } elseif ($knowledgeLevel === 'high') {
            $recommendations = [
                'self_explanation',
                'imagination',
                'expertise_reversal',
                'guidance_fading'
            ];
        } else {
            $recommendations = [
                'worked_example',
                'variability',
                'self_explanation',
                'guidance_fading'
            ];
        }

        return [
            'recommended_effects' => $recommendations,
            'reasoning' => $this->getRecommendationReasoning($knowledgeLevel)
        ];
    }

    private function getRecommendationReasoning(string $knowledgeLevel): string
    {
        $reasons = [
            'low' => 'The group has limited prior knowledge. CLT effects that reduce intrinsic cognitive load and provide structured support are recommended.',
            'medium' => 'The group has moderate prior knowledge. A combination of effects that provide support but also promote active thinking is recommended.',
            'high' => 'The group has good prior knowledge. Effects that promote deep processing and reduce explicit guidance that could be redundant are recommended.'
        ];

        return $reasons[$knowledgeLevel] ?? '';
    }
}
