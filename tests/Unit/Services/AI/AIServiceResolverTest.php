<?php

namespace Tests\Unit\Services\AI;

use App\Services\AI\AIServiceResolver;
use App\Services\AI\AnthropicServiceStub;
use App\Services\AI\AzureOpenAIService;
use App\Services\AI\OllamaServiceStub;
use App\Services\AI\OpenAIService;
use Tests\TestCase;

class AIServiceResolverTest extends TestCase
{
    public function test_it_resolves_explicit_openai_provider(): void
    {
        $resolver = app(AIServiceResolver::class);

        $resolved = $resolver->resolve('openai');

        $this->assertSame('openai', $resolved['provider']);
        $this->assertInstanceOf(OpenAIService::class, $resolved['service']);
    }

    public function test_it_resolves_explicit_ollama_provider(): void
    {
        $resolver = app(AIServiceResolver::class);

        $resolved = $resolver->resolve('ollama');

        $this->assertSame('ollama', $resolved['provider']);
        $this->assertInstanceOf(OllamaServiceStub::class, $resolved['service']);
    }

    public function test_it_falls_back_to_default_provider_when_unknown_is_passed(): void
    {
        config()->set('services.ai.provider', 'anthropic');

        $resolver = app(AIServiceResolver::class);
        $resolved = $resolver->resolve('some-unknown-provider');

        $this->assertSame('anthropic', $resolved['provider']);
        $this->assertInstanceOf(AnthropicServiceStub::class, $resolved['service']);
    }

    public function test_it_uses_configured_default_provider_when_provider_is_null(): void
    {
        config()->set('services.ai.provider', 'azure');

        $resolver = app(AIServiceResolver::class);
        $resolved = $resolver->resolve();

        $this->assertSame('azure', $resolved['provider']);
        $this->assertInstanceOf(AzureOpenAIService::class, $resolved['service']);
    }
}
