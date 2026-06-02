<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class AiServiceClient
{
    private string $baseUrl;
    private ?string $apiToken;
    private int $timeout;

    public function __construct()
    {
        $this->baseUrl = config('services.ai_service.url', 'http://localhost:8001');
        $this->apiToken = config('services.ai_service.token') ?? '';
        $this->timeout = config('services.ai_service.timeout', 120);
    }

    /**
     * Generate instructional material
     *
     * @param array $requestData
     * @return array
     * @throws Exception
     */
    public function generateMaterial(array $requestData): array
    {
        try {
            Log::info('Calling AI service to generate material', [
                'material_type' => $requestData['material_type']
            ]);

            /** @var Response $response */
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'X-Api-Token' => $this->apiToken,
                    'Accept' => 'application/json',
                ])
                ->post("{$this->baseUrl}/api/v1/materials/generate", $requestData);

            if (!$response->successful()) {
                throw new Exception(
                    "AI service error: " . $response->body()
                );
            }

            $data = $response->json();

            Log::info('Material generated successfully', [
                'tokens_used' => $data['token_usage']['total_tokens'] ?? 0
            ]);

            return $data;

        } catch (Exception $e) {
            Log::error('Error generating material with AI', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Validate connection with the AI service
     *
     * @return bool
     */
    public function validateConnection(): bool
    {
        try {
            /** @var Response $response */
            $response = Http::timeout(10)
                ->withHeaders(['X-Api-Token' => $this->apiToken])
                ->post("{$this->baseUrl}/api/v1/materials/validate");

            return $response->successful() && $response->json()['success'];
        } catch (Exception $e) {
            Log::error('Error validating connection with AI service', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get list of available CLT effects
     *
     * @return array
     * @throws Exception
     */
    public function getCltEffects(): array
    {
        try {
            /** @var Response $response */
            $response = Http::timeout(10)
                ->withHeaders(['X-Api-Token' => $this->apiToken])
                ->get("{$this->baseUrl}/api/v1/materials/clt-effects");

            if (!$response->successful()) {
                throw new Exception("Error getting CLT effects");
            }

            return $response->json();
        } catch (Exception $e) {
            Log::error('Error getting CLT effects', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
