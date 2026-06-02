# app/services/claude_service.py
import anthropic
import logging
import time
from typing import Dict, Optional
from app.config import get_settings

settings = get_settings()
logger = logging.getLogger(__name__)

class ClaudeService:
    def __init__(self):
        self.client = anthropic.Anthropic(api_key=settings.anthropic_api_key)
        self.model = settings.claude_model
        self.max_tokens = settings.max_tokens
        self.temperature = settings.temperature
    
    async def generate_content(
        self,
        prompt: str,
        system_prompt: Optional[str] = None,
        max_retries: int = None
    ) -> Dict:
        """
        Generate content using Claude API with automatic retries
        """
        max_retries = max_retries or settings.max_retries
        retry_delay = settings.retry_delay
        
        for attempt in range(max_retries):
            try:
                logger.info(f"Calling Claude API (attempt {attempt + 1}/{max_retries})")
                
                message_params = {
                    "model": self.model,
                    "max_tokens": self.max_tokens,
                    "temperature": self.temperature,
                    "messages": [
                        {
                            "role": "user",
                            "content": prompt
                        }
                    ]
                }
                
                if system_prompt:
                    message_params["system"] = system_prompt
                
                response = self.client.messages.create(**message_params)
                
                # Extract content from the response
                content = self._extract_content(response)
                
                # Token usage information
                usage = {
                    "input_tokens": response.usage.input_tokens,
                    "output_tokens": response.usage.output_tokens,
                    "total_tokens": response.usage.input_tokens + response.usage.output_tokens
                }
                
                logger.info(f"Generation successful. Tokens used: {usage['total_tokens']}")
                
                return {
                    "success": True,
                    "content": content,
                    "usage": usage,
                    "model": response.model,
                    "stop_reason": response.stop_reason
                }
                
            except anthropic.RateLimitError as e:
                logger.warning(f"Rate limit reached: {e}")
                if attempt < max_retries - 1:
                    wait_time = retry_delay * (2 ** attempt)  # Exponential backoff
                    logger.info(f"Waiting {wait_time} seconds before retrying...")
                    time.sleep(wait_time)
                else:
                    logger.error("Maximum retries reached due to rate limit")
                    raise
                    
            except anthropic.APIError as e:
                logger.error(f"Claude API error: {e}")
                if attempt < max_retries - 1:
                    time.sleep(retry_delay)
                else:
                    raise
                    
            except Exception as e:
                logger.error(f"Unexpected error calling Claude API: {e}")
                raise
        
        return {
            "success": False,
            "error": "Maximum retries reached"
        }
    
    def _extract_content(self, response) -> str:
        """
        Extract text content from the Claude response
        """
        if not response.content:
            return ""

        # Claude may return multiple content blocks
        text_content = []
        for block in response.content:
            if hasattr(block, 'text'):
                text_content.append(block.text)
        
        return "\n".join(text_content)
    
    async def validate_api_connection(self) -> bool:
        """
        Validate that the connection to Claude API works correctly
        """
        try:
            test_prompt = "Reply with 'OK' if you receive this message."
            result = await self.generate_content(test_prompt)
            return result.get("success", False)
        except Exception as e:
            logger.error(f"Error validating connection with Claude API: {e}")
            return False