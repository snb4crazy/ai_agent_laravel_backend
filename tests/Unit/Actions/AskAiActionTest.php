<?php

namespace Tests\Unit\Actions;

use App\Enums\TaskStatus;
use App\Models\RunLog;
use App\Models\Task;
use App\Models\User;
use App\Services\AI\AIServiceResolver;
use App\Services\AI\Contracts\AIServiceInterface;
use App\Services\TaskActionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AskAiActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_ask_ai_action_uses_explicit_provider_from_input(): void
    {
        $this->bindFakeResolver();

        $service = app(TaskActionService::class);
        $result = $service->execute('ask_ai', [
            'prompt' => 'Say hello',
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
        ]);

        $this->assertTrue($result['executed']);
        $this->assertSame('ok', $result['result']['status']);
        $this->assertSame('openai', $result['result']['provider']);
        $this->assertSame('gpt-4o-mini', $result['result']['model']);
        $this->assertSame('Synthetic AI answer', $result['result']['text']);
    }

    public function test_ask_ai_action_falls_back_to_config_default_for_unknown_provider(): void
    {
        config()->set('services.ai.provider', 'azure');
        $this->bindFakeResolver();

        $service = app(TaskActionService::class);
        $result = $service->execute('ask_ai', [
            'prompt' => 'Say hello',
            'provider' => 'provider-that-does-not-exist',
        ]);

        $this->assertTrue($result['executed']);
        $this->assertSame('ok', $result['result']['status']);
        $this->assertSame('azure', $result['result']['provider']);
    }

    public function test_ask_ai_action_logs_provider_and_model_metadata(): void
    {
        $this->bindFakeResolver();

        $task = Task::query()->create([
            'public_id' => (string) Str::uuid(),
            'user_id' => User::factory()->create()->id,
            'type' => 'ask_ai_once',
            'status' => TaskStatus::QUEUED,
            'input_json' => ['prompt' => 'hello'],
        ]);

        $service = app(TaskActionService::class);
        $result = $service->execute('ask_ai', [
            'prompt' => 'Say hello',
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'task_id' => $task->id,
            'task_public_id' => $task->public_id,
        ]);

        $this->assertTrue($result['executed']);
        $this->assertSame('ok', $result['result']['status']);
        $this->assertSame('openai', $result['result']['provider']);
        $this->assertSame('gpt-4o-mini', $result['result']['model']);
        $this->assertSame('Synthetic AI answer', $result['result']['text']);

        $runLog = RunLog::query()
            ->where('task_id', $task->id)
            ->where('event_type', 'task.ai_response_received')
            ->latest('id')
            ->first();

        $this->assertNotNull($runLog);
        $this->assertSame('openai', $runLog->context_json['provider']);
        $this->assertSame('gpt-4o-mini', $runLog->context_json['model']);
        $this->assertSame('Synthetic AI answer', $result['result']['text']);
    }

    public function test_ask_ai_action_fails_when_no_prompt_or_messages_are_given(): void
    {
        $service = app(TaskActionService::class);

        $result = $service->execute('ask_ai', []);

        $this->assertTrue($result['executed']);
        $this->assertSame('failed', $result['result']['status']);
    }

    private function bindFakeResolver(): void
    {
        $this->app->instance(AIServiceResolver::class, new class extends AIServiceResolver
        {
            public function resolve(?string $provider = null): array
            {
                $supported = ['azure', 'openai', 'ollama', 'anthropic'];
                $default = strtolower((string) config('services.ai.provider', 'azure'));
                $candidate = strtolower(trim((string) ($provider ?? $default)));
                $providerName = in_array($candidate, $supported, true) ? $candidate : $default;

                $service = new class implements AIServiceInterface
                {
                    public function chat(array $messages, ?string $model = null, array $options = []): array
                    {
                        return [
                            'choices' => [
                                ['message' => ['content' => 'Synthetic AI answer']],
                            ],
                        ];
                    }

                    public function embeddings(string|array $input, ?string $model = null, array $options = []): array
                    {
                        return ['data' => []];
                    }

                    public function batch(string $model, string $inputFileId, string $outputDirectoryId, string $completionWindow = '24h', array $options = []): array
                    {
                        return ['id' => 'batch_stub'];
                    }
                };

                return [
                    'provider' => $providerName,
                    'service' => $service,
                ];
            }
        });
    }
}
