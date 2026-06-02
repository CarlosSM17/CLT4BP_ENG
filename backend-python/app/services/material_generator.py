# app/services/material_generator.py
import json
import logging
from typing import Dict
from app.services.claude_service import ClaudeService
from app.services.prompt_builder import PromptBuilder
from app.models.prompts import MaterialGenerationRequest, GeneratedMaterial

logger = logging.getLogger(__name__)

class MaterialGeneratorService:
    def __init__(self):
        self.claude_service = ClaudeService()
        self.prompt_builder = PromptBuilder()
    
    async def generate_material(
        self,
        request: MaterialGenerationRequest
    ) -> GeneratedMaterial:
        """
        Generate instructional material using Claude API
        """
        try:
            logger.info(f"Generating material type {request.material_type} for course {request.course_id}")
            
            # Build prompts
            system_prompt, user_prompt = self.prompt_builder.build_complete_prompt(request)
            
            logger.debug(f"System prompt length: {len(system_prompt)} chars")
            logger.debug(f"User prompt length: {len(user_prompt)} chars")
        
            # Call Claude API
            response = await self.claude_service.generate_content(
                prompt=user_prompt,
                system_prompt=system_prompt
            )
            
            if not response.get("success"):
                raise Exception(f"Generation error: {response.get('error')}")
            
            # Parse JSON response
            content_text = response["content"]
            content_json = self._parse_json_response(content_text)
            
            # Create generated material object
            material = GeneratedMaterial(
                material_type=request.material_type,
                content=content_json,
                metadata={
                    "course_id": request.course_id,
                    "profile_type": request.profile_type,
                    "student_id": request.student_id,
                    "topic": request.topic,
                    "clt_effects_applied": request.selected_clt_effects,
                    "generated_at": None  # Will be added in Laravel
                },
                token_usage=response.get("usage")
            )
            
            logger.info(f"Material generated successfully. Tokens: {response.get('usage', {}).get('total_tokens')}")
            
            return material
            
        except json.JSONDecodeError as e:
            logger.error(f"Error parsing JSON from Claude: {e}")
            logger.error(f"Response received: {content_text[:500]}...")
            raise Exception(f"Claude response is not valid JSON: {str(e)}")
            
        except Exception as e:
            logger.error(f"Error generating material: {e}")
            raise
    
    def _parse_json_response(self, response_text: str) -> Dict:
        """
        Parse the Claude response, cleaning markdown if necessary
        """
        # Clean markdown code blocks if they exist
        cleaned = response_text.strip()
        
        if cleaned.startswith("```json"):
            cleaned = cleaned[7:]  # Remove ```json
        elif cleaned.startswith("```"):
            cleaned = cleaned[3:]  # Remove ```
        
        if cleaned.endswith("```"):
            cleaned = cleaned[:-3]
        
        cleaned = cleaned.strip()
        
        # Parse JSON
        try:
            return json.loads(cleaned)
        except json.JSONDecodeError:
            # Try to find JSON within the text
            start = cleaned.find('{')
            end = cleaned.rfind('}') + 1
            if start != -1 and end > start:
                return json.loads(cleaned[start:end])
            raise
    
    async def validate_generated_content(self, material: GeneratedMaterial) -> Dict:
        """
        Validate that the generated content has the expected structure
        """
        validation_result = {
            "valid": True,
            "errors": [],
            "warnings": []
        }
        
        content = material.content
        material_type = material.material_type
        
        # Validations by material type
        if material_type == "learning_tasks":
            if "tasks" not in content:
                validation_result["valid"] = False
                validation_result["errors"].append("Missing 'tasks' field")
            elif not isinstance(content["tasks"], list) or len(content["tasks"]) == 0:
                validation_result["valid"] = False
                validation_result["errors"].append("'tasks' must be a non-empty list")
        
        elif material_type == "support_info":
            if "sections" not in content:
                validation_result["valid"] = False
                validation_result["errors"].append("Missing 'sections' field")
        
        elif material_type == "procedural_info":
            if "worked_examples" not in content:
                validation_result["warnings"].append("Including 'worked_examples' is recommended")
        
        elif material_type == "verbal_protocols":
            if "think_aloud_transcript" not in content:
                validation_result["valid"] = False
                validation_result["errors"].append("Missing 'think_aloud_transcript' field")
        
        elif material_type == "example":
            if "code" not in content:
                validation_result["warnings"].append("Including a 'code' field is recommended")
        
        return validation_result