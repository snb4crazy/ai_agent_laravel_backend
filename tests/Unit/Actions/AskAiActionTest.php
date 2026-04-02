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

    public function test_ask_ai_action_calls_service_and_logs_response(): void
    {
        $this->app->instance(AIServiceResolver::class, new class extends AIServiceResolver
        {
            public function resolve(?string $provider = null): array
            {
                $providerName = $provider ?: 'openai';

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
            'task_id' => $task->id,
            'task_public_id' => $task->public_id,
        ]);

        $this->assertTrue($result['executed']);
        $this->assertSame('ok', $result['result']['status']);
        $this->assertSame('Synthetic AI answer', $result['result']['text']);

        $runLog = RunLog::query()
            ->where('task_id', $task->id)
            ->where('event_type', 'task.ai_response_received')
            ->latest('id')
            ->first();

        $this->assertNotNull($runLog);
        $this->assertSame('openai', $runLog->context_json['provider']);
        $this->assertSame('Synthetic AI answer', $result['result']['text']);
    }

    public function test_ask_ai_action_fails_when_no_prompt_or_messages_are_given(): void
    {
        $service = app(TaskActionService::class);

        $result = $service->execute('ask_ai', []);

        $this->assertTrue($result['executed']);
        $this->assertSame('failed', $result['result']['status']);
    }
}
