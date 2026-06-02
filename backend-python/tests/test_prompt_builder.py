import pytest
from app.services.prompt_builder import PromptBuilder
from app.models.prompts import MaterialGenerationRequest, MaterialType, ProfileType, LearningObjective

def test_build_system_prompt():
    builder = PromptBuilder()
    prompt = builder.build_system_prompt()

    assert "instructional designer" in prompt.lower()
    assert "clt" in prompt.lower() or "cognitive load" in prompt.lower()
    assert "arcs" in prompt.lower()

def test_build_complete_prompt():
    builder = PromptBuilder()

    request = MaterialGenerationRequest(
        course_id=1,
        profile_type=ProfileType.GROUP,
        profile_data={
            "group_summary": {
                "predominant_knowledge": "low",
                "predominant_motivation": "medium",
                "predominant_strategies": "medium",
                "group_characteristics": "Heterogeneous group"
            },
            "knowledge_averages": {
                "overall": {"level": "low", "average": 45}
            },
            "teaching_recommendations": ["Use worked examples"]
        },
        learning_objectives=[
            LearningObjective(id=1, description="Understand variables")
        ],
        selected_clt_effects=["worked_example", "split_attention"],
        material_type=MaterialType.EXAMPLE,
        topic="Variables in Python"
    )

    system_prompt, user_prompt = builder.build_complete_prompt(request)

    assert len(system_prompt) > 0
    assert len(user_prompt) > 0
    assert "Variables in Python" in user_prompt
    assert "worked example" in user_prompt.lower()
