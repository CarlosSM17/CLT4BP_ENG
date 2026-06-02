# app/services/prompt_builder.py
import json
import os
from typing import Dict, List, Union
from app.models.prompts import (
    MaterialGenerationRequest, MaterialType, ProfileType, CltEffect
)
from app.models.profiles import StudentProfile, GroupProfile

class PromptBuilder:
    """
    Builds structured prompts for the Claude API
    """

    # CLT effects definition
    CLT_EFFECTS = {
        # Effects for New Knowledge
        "goal_free": CltEffect(
            id="goal_free",
            name="Goal-Free Effect",
            description="Remove specific goals to reduce cognitive load and allow exploration",
            category="new_knowledge",
            application_guidance="Present problems without specific objectives, allowing the student to explore different solutions"
        ),
        "worked_example": CltEffect(
            id="worked_example",
            name="Worked Example Effect",
            description="Show fully solved examples with step-by-step explanations",
            category="new_knowledge",
            application_guidance="Include fully worked examples before similar tasks"
        ),
        "completion_problem": CltEffect(
            id="completion_problem",
            name="Completion Problem Effect",
            description="Provide partially solved problems to complete",
            category="new_knowledge",
            application_guidance="Give partially started problems that the student must complete"
        ),
        "split_attention": CltEffect(
            id="split_attention",
            name="Split Attention Effect",
            description="Spatially integrate related information to avoid attention splitting",
            category="new_knowledge",
            application_guidance="Keep explanatory text close to related diagrams/code"
        ),
        "modality": CltEffect(
            id="modality",
            name="Modality Effect",
            description="Use a combination of presentation modes (visual + auditory)",
            category="new_knowledge",
            application_guidance="Combine textual explanations with verbal descriptions when possible"
        ),
        "redundancy": CltEffect(
            id="redundancy",
            name="Redundancy Effect",
            description="Avoid redundant information that adds no value",
            category="new_knowledge",
            application_guidance="Eliminate duplicate information; present each concept once clearly"
        ),
        "variability": CltEffect(
            id="variability",
            name="Variability Effect",
            description="Use multiple varied examples of the same concept",
            category="new_knowledge",
            application_guidance="Provide several examples showing different applications of the same concept"
        ),
        "isolated_elements": CltEffect(
            id="isolated_elements",
            name="Isolated Elements Effect",
            description="Present complex elements in isolation first",
            category="new_knowledge",
            application_guidance="Introduce complex concepts element by element before combining them"
        ),
        "element_interactivity": CltEffect(
            id="element_interactivity",
            name="Element Interactivity Effect",
            description="Manage interactivity between information elements",
            category="new_knowledge",
            application_guidance="Structure content to minimize elements that must be processed simultaneously"
        ),

        # Effects for Prior Knowledge
        "self_explanation": CltEffect(
            id="self_explanation",
            name="Self-Explanation Effect",
            description="Encourage students to explain concepts in their own words",
            category="prior_knowledge",
            application_guidance="Include questions that require the student to explain the 'why' and 'how'"
        ),
        "imagination": CltEffect(
            id="imagination",
            name="Imagination Effect",
            description="Ask students to imagine or visualize procedures",
            category="prior_knowledge",
            application_guidance="Ask the student to mentally visualize processes before executing them"
        ),
        "expertise_reversal": CltEffect(
            id="expertise_reversal",
            name="Expertise Reversal Effect",
            description="Reduce explicit guidance for students with prior knowledge",
            category="prior_knowledge",
            application_guidance="Minimize step-by-step instructions; provide more open-ended problems"
        ),
        "guidance_fading": CltEffect(
            id="guidance_fading",
            name="Guidance-Fading Effect",
            description="Gradually reduce the level of guidance provided",
            category="prior_knowledge",
            application_guidance="Start with full guidance and progressively reduce support"
        ),
        "collective_memory": CltEffect(
            id="collective_memory",
            name="Collective Memory Effect",
            description="Leverage shared knowledge in group activities",
            category="prior_knowledge",
            application_guidance="Design activities that allow students to share prior knowledge"
        ),
        "self_management": CltEffect(
            id="self_management",
            name="Self-Management Effect",
            description="Encourage students to manage their own learning",
            category="prior_knowledge",
            application_guidance="Give the student autonomy in selecting strategies and learning sequences"
        ),
        "human_movement": CltEffect(
            id="human_movement",
            name="Human Movement Effect",
            description="Incorporate activities that involve physical movement",
            category="prior_knowledge",
            application_guidance="Suggest practical activities requiring movement or physical manipulation"
        ),
        "transient_information": CltEffect(
            id="transient_information",
            name="Transient Information Effect",
            description="Manage transient information to avoid overload",
            category="prior_knowledge",
            application_guidance="Provide permanent references for information that disappears quickly"
        )
    }

    def __init__(self):
        pass

    def build_system_prompt(self) -> str:
        """
        Builds the base system prompt for Claude
        """
        return """You are an expert instructional designer specializing in:
- Cognitive Load Theory (CLT)
- 4C/ID Model (Four-Component Instructional Design)
- ARCS Motivation Model (Attention, Relevance, Confidence, Satisfaction)
- Differentiated learning strategies
- Programming and software development

Your task is to generate high-quality instructional material, personalized according to
the student or group profile, applying the requested pedagogical principles.

IMPORTANT:
- Generate content in clear and professional English
- Strictly follow instructions about CLT effects
- Apply the ARCS model throughout all material
- Adapt the content to the student's prior knowledge level
- Use concrete and relevant examples in programming
- Provide content in the requested structured JSON format"""

    def build_profile_section(
        self,
        profile_type: ProfileType,
        profile_data: Union[Dict, StudentProfile, GroupProfile]
    ) -> str:
        """
        Builds the profile section of the prompt
        """
        if profile_type == ProfileType.INDIVIDUAL:
            return self._build_individual_profile_section(profile_data)
        else:
            return self._build_group_profile_section(profile_data)

    def _build_individual_profile_section(self, profile: Union[Dict, StudentProfile]) -> str:
        """
        Individual student profile
        """
        if isinstance(profile, dict):
            summary = profile.get('profile_summary', {})
            knowledge = profile.get('knowledge_assessment', {})
        else:
            summary = profile.profile_summary.dict()
            knowledge = profile.knowledge_assessment.dict()

        # Knowledge assessment scores
        recall_initial = knowledge.get('recall', {}).get('percentage', 0)
        comprehension_initial = knowledge.get('comprehension', {}).get('percentage', 0)
        recall_final = knowledge.get('recall_final', {}).get('percentage')
        comprehension_final = knowledge.get('comprehension_final', {}).get('percentage')

        section = f"""
## STUDENT PROFILE

### General Summary
- **Motivation Level**: {summary.get('overall_motivation', 'N/A')}
- **Learning Strategies Level**: {summary.get('overall_strategies', 'N/A')}
- **Prior Knowledge Level**: {summary.get('prior_knowledge', 'N/A')}

### Knowledge Assessment Scores
- **Initial Recall**: {recall_initial}% correct
- **Initial Comprehension**: {comprehension_initial}% correct
"""
        if recall_final is not None:
            section += f"- **Final Recall**: {recall_final}% correct\n"
        if comprehension_final is not None:
            section += f"- **Final Comprehension**: {comprehension_final}% correct\n"

        section += f"""- **Overall Knowledge Level**: {knowledge.get('overall_level', 'N/A')}
"""

        # Add MSLQ scores by dimension if available
        mslq_scores = profile.get('mslq_scores', {}) if isinstance(profile, dict) else {}
        if mslq_scores:
            MSLQ_LABELS = {
                'intrinsic_goal_orientation': 'Intrinsic Goal Orientation',
                'extrinsic_goal_orientation': 'Extrinsic Goal Orientation',
                'task_value': 'Task Value',
                'control_beliefs': 'Control Beliefs',
                'self_efficacy': 'Self-Efficacy',
                'test_anxiety': 'Test Anxiety',
                'rehearsal': 'Rehearsal',
                'elaboration': 'Elaboration',
                'organization': 'Organization',
                'critical_thinking': 'Critical Thinking',
                'metacognitive_self_regulation': 'Metacognitive Self-Regulation',
                'time_study_environment': 'Time and Study Environment Management',
                'effort_regulation': 'Effort Regulation',
                'peer_learning': 'Peer Learning',
                'help_seeking': 'Help Seeking',
            }
            section += "\n### MSLQ Scales (Motivation and Learning Strategies)\n"
            section += "*(scale 1-7: low <3.5, medium 3.5-5.4, high ≥5.5)*\n"
            for dim, data in mslq_scores.items():
                label = MSLQ_LABELS.get(dim, dim)
                avg = data.get('average', 0)
                level = data.get('level', 'N/A')
                section += f"- **{label}**: {avg:.2f} ({level})\n"

        section += f"""
### Identified Strengths
{self._format_list(summary.get('key_strengths', []))}

### Areas Requiring Support
{self._format_list(summary.get('areas_for_support', []))}

### Instructional Recommendations
This student will benefit from:
"""

        # Add specific recommendations based on profile
        if isinstance(profile, dict):
            recommendations = profile.get('recommendations', [])
        else:
            recommendations = profile.recommendations

        for rec in recommendations:
            section += f"- {rec}\n"

        return section

    def _build_group_profile_section(self, profile: Union[Dict, GroupProfile]) -> str:
        """
        Group profile for the course
        """
        if isinstance(profile, dict):
            summary = profile.get('group_summary', {})
            knowledge = profile.get('knowledge_averages', {})
            student_count = profile.get('student_count', 0)
        else:
            summary = profile.group_summary
            knowledge = profile.knowledge_averages
            student_count = profile.student_count

        section = f"""
## GROUP PROFILE

### General Information
- **Number of Students**: {student_count}
- **Group Characteristics**: {summary.get('group_characteristics', 'N/A')}

### Predominant Levels
- **Motivation**: {summary.get('predominant_motivation', 'N/A')}
- **Learning Strategies**: {summary.get('predominant_strategies', 'N/A')}
- **Prior Knowledge**: {summary.get('predominant_knowledge', 'N/A')}

### Prior Knowledge Averages
- **Average Recall**: {knowledge.get('recall', {}).get('average', 0)}%
- **Average Comprehension**: {knowledge.get('comprehension', {}).get('average', 0)}%
- **Overall Group Level**: {knowledge.get('overall', {}).get('level', 'N/A')}

### Recommendations for Group Teaching
"""

        if isinstance(profile, dict):
            recommendations = profile.get('teaching_recommendations', [])
        else:
            recommendations = profile.teaching_recommendations

        for rec in recommendations:
            section += f"- {rec}\n"

        return section

    def build_learning_objectives_section(self, objectives: List[Dict]) -> str:
        """
        Builds the learning objectives section
        """
        section = """
## COURSE LEARNING OBJECTIVES

The instructional material must be aligned with the following objectives:

"""
        for i, obj in enumerate(objectives, 1):
            section += f"{i}. {obj.get('description', '')}\n"
            if obj.get('bloom_level'):
                section += f"   (Bloom's Level: {obj['bloom_level']})\n"

        return section

    def build_clt_effects_section(self, selected_effects: List[str]) -> str:
        """
        Builds the CLT effects section to apply
        """
        section = """
## COGNITIVE LOAD THEORY EFFECTS TO APPLY

You must apply the following CLT effects in the material you generate:

"""
        for effect_id in selected_effects:
            effect = self.CLT_EFFECTS.get(effect_id)
            if effect:
                section += f"""
### {effect.name}
**Description**: {effect.description}
**How to apply it**: {effect.application_guidance}

"""

        return section

    def build_arcs_section(self) -> str:
        """
        Builds the ARCS model section
        """
        return """
## ARCS MODEL APPLICATION

You must integrate the four components of the ARCS model in the material:

### A - Attention
- Capture the student's attention from the start
- Use surprising or intriguing elements
- Vary the format and presentation style

### R - Relevance
- Connect the content with the student's prior experiences
- Show practical, real-world applications
- Explain the "why" and "what for" of the topic

### C - Confidence
- Structure content with increasing difficulty
- Provide clear expectations of what will be achieved
- Offer opportunities for success

### S - Satisfaction
- Provide positive and constructive feedback
- Show progress and achievements reached
- Connect learning with future goals
"""

    def build_differentiated_strategies_section(self, profile_type: ProfileType) -> str:
        """
        Builds the differentiated strategies section
        """
        if profile_type == ProfileType.GROUP:
            return ""  # Individual strategies not applied in group material

        return """
## DIFFERENTIATED LEARNING STRATEGIES

Based on the student's profile, incorporate specific strategies:

### If the student has weaknesses in cognitive strategies:
- Explicitly model rehearsal and elaboration techniques
- Provide graphic organizers and concept maps
- Include effective note-taking guides

### If the student has weaknesses in self-regulation:
- Include step-by-step checklists
- Provide comprehension monitoring strategies
- Suggest self-assessment techniques

### If the student has high test anxiety:
- Use encouraging and positive language
- Provide practice in low-stakes environments
- Include anxiety management strategies
"""

    def build_material_type_instructions(
        self,
        material_type: MaterialType,
        topic: str
    ) -> str:
        """
        Builds specific instructions based on material type
        """
        instructions = {
            MaterialType.LEARNING_TASKS: f"""
## MATERIAL TYPE: LEARNING TASKS

Generate a sequence of learning tasks about: **{topic}**

### Required Characteristics:
1. **Increasing Difficulty**: Design 3-5 tasks that progressively increase in complexity
2. **Authentic Scenarios**: Use realistic programming situations
3. **WHY and WHAT FOR explanation**: Clearly explain the relevance of each task
4. **4C/ID Elements**:
   - Meaningful and complete tasks
   - Presentation in authentic context
   - Gradually decreasing support

### JSON Output Format:
```json
{{
  "tasks": [
    {{
      "task_number": 1,
      "title": "Task title",
      "difficulty_level": "basic|intermediate|advanced",
      "description": "Complete description of the problem",
      "context": "Real-world scenario",
      "why_relevant": "Why this task is important",
      "expected_outcome": "What will be achieved",
      "estimated_time": "time in minutes",
      "support_level": "high|medium|low",
      "arcs_integration": {{
        "attention": "How it captures attention",
        "relevance": "Why it is relevant",
        "confidence": "How it builds confidence",
        "satisfaction": "How it generates satisfaction"
      }}
    }}
  ]
}}
```
""",
            MaterialType.SUPPORT_INFO: f"""
## MATERIAL TYPE: SUPPORT INFORMATION

Generate theoretical support information about: **{topic}**

### Required Characteristics:
1. **Fundamental Theory**: Basic concepts and theoretical foundations
2. **Clear Explanations**: Accessible language adapted to the student's level
3. **Illustrative Examples**: Concrete examples of each concept
4. **Logical Organization**: Structure that facilitates understanding

### JSON Output Format:
```json
{{
  "sections": [
    {{
      "title": "Section title",
      "order": 1,
      "content": "Complete explanatory content",
      "key_concepts": ["concept1", "concept2"],
      "examples": [
        {{
          "description": "Example description",
          "code": "code if applicable"
        }}
      ],
      "arcs_integration": {{
        "attention": "Element that captures attention",
        "relevance": "Connection to real-world applications",
        "confidence": "How it helps feel capable",
        "satisfaction": "What achievement it represents"
      }}
    }}
  ],
  "summary": "General content summary",
  "key_takeaways": ["key point 1", "key point 2"]
}}
```
""",
            MaterialType.PROCEDURAL_INFO: f"""
## MATERIAL TYPE: PROCEDURAL INFORMATION

Generate procedural information (examples, guides, models) about: **{topic}**

### Required Characteristics:
1. **Isomorphic Examples**: Examples with similar structure but different context
2. **Guiding Questions**: Questions that guide thinking
3. **Models and Maps**: Visual representations of knowledge
4. **Step-by-Step Procedures**: When appropriate

### JSON Output Format:
```json
{{
  "worked_examples": [
    {{
      "title": "Example title",
      "problem": "Problem description",
      "solution_steps": [
        {{
          "step_number": 1,
          "description": "What is done",
          "explanation": "Why it is done",
          "code": "code if applicable"
        }}
      ],
      "key_insights": ["insight 1", "insight 2"]
    }}
  ],
  "guiding_questions": [
    "Question promoting reflection?",
    "Question connecting concepts?"
  ],
  "conceptual_models": [
    {{
      "title": "Model title",
      "description": "Description of the mental model",
      "visual_representation": "Description of how to visualize it"
    }}
  ]
}}
```
""",
            MaterialType.VERBAL_PROTOCOLS: f"""
## MATERIAL TYPE: VERBAL PROTOCOLS

Generate a verbal protocol (think-aloud) about: **{topic}**

### Required Characteristics:
1. **Expert Perspective**: From the viewpoint of an experienced programmer
2. **Focus on HOW and WHY**: Explaining the thought process
3. **Naturalness**: As if thinking out loud
4. **Process Transparency**: Also show doubts and decisions

### JSON Output Format:
```json
{{
  "protocol_title": "Protocol title",
  "scenario": "Scenario/problem description",
  "think_aloud_transcript": "Complete think-aloud transcript, including:\\n- Initial problem analysis\\n- Considerations and alternatives\\n- Decisions made and why\\n- Implementation process\\n- Reflections during the process\\n- Solution validation",
  "key_thinking_patterns": [
    "Thinking pattern 1",
    "Thinking pattern 2"
  ],
  "teaching_points": [
    "Teaching point 1",
    "Teaching point 2"
  ]
}}
```
""",
            MaterialType.EXAMPLE: f"""
## MATERIAL TYPE: REAL EXAMPLE

Generate a real and complete example about: **{topic}**

### Required Characteristics:
1. **Completeness**: Functional and complete example
2. **Professional Quality**: Code following best practices
3. **Explanatory Comments**: Well-commented code
4. **Practical Application**: Real-world problem

### JSON Output Format:
```json
{{
  "example_title": "Example title",
  "description": "Description of the problem it solves",
  "use_case": "Real-world use case",
  "code": "Complete code with comments",
  "explanation": "Detailed explanation of how it works",
  "key_concepts_demonstrated": ["concept 1", "concept 2"],
  "possible_variations": [
    "Example variation 1",
    "Example variation 2"
  ],
  "common_mistakes": [
    "Common mistake 1 and how to avoid it",
    "Common mistake 2 and how to avoid it"
  ]
}}
```
"""
        }

        return instructions.get(material_type, "")

    def build_output_format_section(self) -> str:
        """
        Builds the output format section
        """
        return """
## OUTPUT FORMAT

IMPORTANT:
- Return ONLY the requested JSON, without additional text before or after
- Do NOT include markdown code blocks (```)
- The JSON must be valid and parseable
- Use English for all content
- Be specific and detailed in explanations
"""

    def build_complete_prompt(self, request: MaterialGenerationRequest) -> tuple[str, str]:
        """
        Builds the complete prompt for Claude API
        Returns: (system_prompt, user_prompt)
        """
        system_prompt = self.build_system_prompt()

        user_prompt = f"""
{self.build_profile_section(request.profile_type, request.profile_data)}

{self.build_learning_objectives_section([obj.dict() for obj in request.learning_objectives])}

{self.build_clt_effects_section(request.selected_clt_effects)}

{self.build_arcs_section()}

{self.build_differentiated_strategies_section(request.profile_type)}

{self.build_material_type_instructions(request.material_type, request.topic)}

{self.build_output_format_section()}
"""

        if request.additional_context:
            user_prompt += f"""
## ADDITIONAL CONTEXT

{request.additional_context}
"""
        type_m = request.material_type
        output_folder = "C:/Projects/CLT4BP/docs/"
        prefix = "backup_"
        file_name = "material.json"
        if not os.path.exists(output_folder):
            os.makedirs(output_folder)
        full_path = os.path.join(output_folder, f"{prefix}{file_name}")

        with open(full_path, 'w', encoding='utf-8') as f:
            json.dump(user_prompt, f, indent=4, ensure_ascii=False)
        return system_prompt, user_prompt

    def _format_list(self, items: List[str]) -> str:
        """
        Formats a list of items
        """
        if not items:
            return "- None identified\n"
        return "\n".join([f"- {item}" for item in items]) + "\n"
