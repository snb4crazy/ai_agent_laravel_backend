<?php

namespace Tests\Unit\Services\AI;

use App\Services\AI\AnthropicServiceStub;
use App\Services\AI\AzureOpenAIService;
use App\Services\AI\Contracts\AIServiceInterface;
use App\Services\AI\OllamaServiceStub;
use App\Services\AI\OpenAIService;
use Tests\TestCase;

class AIServiceBindingTest extends TestCase
{
    public function test_azure_provider_resolves_azure_service(): void
    {
        config(['services.ai.provider' => 'azure']);

        $service = app(AIServiceInterface::class);

        $this->assertInstanceOf(AzureOpenAIService::class, $service);
    }

    public function test_openai_provider_resolves_openai_service(): void
    {
        config(['services.ai.provider' => 'openai']);

        $service = app(AIServiceInterface::class);

        $this->assertInstanceOf(OpenAIService::class, $service);
    }

    public function test_ollama_provider_resolves_ollama_stub_service(): void
    {
        config(['services.ai.provider' => 'ollama']);

        $service = app(AIServiceInterface::class);

        $this->assertInstanceOf(OllamaServiceStub::class, $service);
    }

    public function test_anthropic_provider_resolves_anthropic_stub_service(): void
    {
        config(['services.ai.provider' => 'anthropic']);

        $service = app(AIServiceInterface::class);

        $this->assertInstanceOf(AnthropicServiceStub::class, $service);
    }
}
