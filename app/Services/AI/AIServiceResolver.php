<?php

namespace App\Services\AI;

use App\Services\AI\Contracts\AIServiceInterface;

class AIServiceResolver
{
    /**
     * Resolve provider name and service implementation.
     *
     * @return array{provider: string, service: AIServiceInterface}
     */
    public function resolve(?string $provider = null): array
    {
        $resolvedProvider = $this->normalizeProvider($provider);

        $service = match ($resolvedProvider) {
            'openai' => app(OpenAIService::class),
            'ollama' => app(OllamaServiceStub::class),
            'anthropic' => app(AnthropicServiceStub::class),
            default => app(AzureOpenAIService::class),
        };

        return [
            'provider' => $resolvedProvider,
            'service' => $service,
        ];
    }

    private function normalizeProvider(?string $provider): string
    {
        $candidate = strtolower(trim((string) ($provider ?: config('services.ai.provider', 'azure'))));

        if (in_array($candidate, ['azure', 'openai', 'ollama', 'anthropic'], true)) {
            return $candidate;
        }

        return strtolower((string) config('services.ai.provider', 'azure'));
    }
}
