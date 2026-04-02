<?php

namespace Tests\Feature\Api;

use App\Enums\QueueEnum;
use App\Enums\TaskStatus;
use App\Jobs\LogTaskRequestJob;
use App\Jobs\PlanTaskStepsJob;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TaskDispatchFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_must_authenticate_before_dispatching_task(): void
    {
        $response = $this->postJson('/api/v1/tasks', [
            'type' => 'chat.completion',
            'input' => ['prompt' => 'Hello'],
        ]);

        $response->assertUnauthorized();
    }

    public function test_authenticated_user_can_dispatch_task_to_queue(): void
    {
        Queue::fake();

        $user = User::factory()->create();

        $tokenResponse = $this->postJson('/api/v1/auth/token', [
            'email' => $user->email,
            'password' => 'password',
            'device_name' => 'phpunit',
        ]);

        $tokenResponse
            ->assertOk()
            ->assertJsonStructure(['token_type', 'access_token']);

        $token = $tokenResponse->json('access_token');

        $payload = [
            'type' => 'chat.completion',
            'input' => ['prompt' => 'Test payload'],
            'meta' => ['source' => 'phpunit'],
        ];

        $response = $this
            ->withToken($token)
            ->postJson('/api/v1/tasks', $payload);

        $response
            ->assertAccepted()
            ->assertJsonStructure(['status', 'dispatch_id', 'task_public_id'])
            ->assertJson(['status' => 'queued']);

        $taskPublicId = $response->json('task_public_id');
        $task = Task::query()->where('public_id', $taskPublicId)->firstOrFail();

        $this->assertSame($taskPublicId, $response->json('dispatch_id'));
        $this->assertSame($user->id, $task->user_id);
        $this->assertSame($payload['type'], $task->type);

        Queue::assertPushed(LogTaskRequestJob::class, function (LogTaskRequestJob $job) use ($task): bool {
            return $job->taskId === $task->id
                && $job->queue === QueueEnum::TASK;
        });
    }

    public function test_dispatch_validation_errors_are_returned(): void
    {
        $user = User::factory()->create();

        $token = $this->postJson('/api/v1/auth/token', [
            'email' => $user->email,
            'password' => 'password',
        ])->json('access_token');

        $response = $this
            ->withToken($token)
            ->postJson('/api/v1/tasks', [
                'type' => '',
                'input' => 'invalid',
            ]);

        $response->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonStructure(['error' => ['code', 'message', 'errors' => ['type', 'input']]]);
    }

    public function test_authenticated_dispatch_persists_task_and_log_in_database(): void
    {
        $user = User::factory()->create();

        $token = $this->postJson('/api/v1/auth/token', [
            'email' => $user->email,
            'password' => 'password',
            'device_name' => 'phpunit-db-test',
        ])->json('access_token');

        $payload = [
            'type' => 'chat.completion',
            'input' => ['prompt' => 'Persist me'],
            'meta' => ['source' => 'phpunit-db'],
        ];

        $response = $this
            ->withToken($token)
            ->postJson('/api/v1/tasks', $payload)
            ->assertAccepted();

        $taskPublicId = $response->json('task_public_id');

        $this->assertDatabaseHas('tasks', [
            'public_id' => $taskPublicId,
            'user_id' => $user->id,
            'type' => 'chat.completion',
            'status' => TaskStatus::COMPLETED,
        ]);

        $taskId = (int) $this->app['db']
            ->table('tasks')
            ->where('public_id', $taskPublicId)
            ->value('id');

        $this->assertDatabaseHas('run_logs', [
            'task_id' => $taskId,
            'event_type' => 'task.accepted',
            'message' => 'Task request accepted and persisted',
        ]);

        $this->assertDatabaseHas('run_logs', [
            'task_id' => $taskId,
            'event_type' => 'task.job_received',
            'message' => 'Queue job received persisted task payload',
        ]);
    }

    public function test_authenticated_user_can_fetch_current_task_status(): void
    {
        $user = User::factory()->create();

        $token = $this->postJson('/api/v1/auth/token', [
            'email' => $user->email,
            'password' => 'password',
            'device_name' => 'status-check',
        ])->json('access_token');

        $task = Task::query()->create([
            'public_id' => '11111111-1111-1111-1111-111111111111',
            'user_id' => $user->id,
            'type' => 'chat.completion',
            'status' => 'queued',
            'input_json' => ['prompt' => 'Status'],
            'meta_json' => ['source' => 'test'],
        ]);

        $response = $this
            ->withToken($token)
            ->getJson('/api/v1/tasks/'.$task->public_id);

        $response
            ->assertOk()
            ->assertJson([
                'data' => [
                    'public_id' => $task->public_id,
                    'type' => 'chat.completion',
                    'status' => 'queued',
                    'input' => ['prompt' => 'Status'],
                    'meta' => ['source' => 'test'],
                ],
            ]);
    }

    public function test_authenticated_user_can_fetch_task_logs(): void
    {
        $user = User::factory()->create();

        $token = $this->postJson('/api/v1/auth/token', [
            'email' => $user->email,
            'password' => 'password',
            'device_name' => 'logs-check',
        ])->json('access_token');

        $taskPublicId = $this
            ->withToken($token)
            ->postJson('/api/v1/tasks', [
                'type' => 'chat.completion',
                'input' => ['prompt' => 'Need logs'],
                'meta' => ['source' => 'logs-test'],
            ])
            ->assertAccepted()
            ->json('task_public_id');

        $response = $this
            ->withToken($token)
            ->getJson('/api/v1/tasks/'.$taskPublicId.'/logs');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('data.0.event_type', 'task.accepted');
        $response->assertJsonPath('data.1.event_type', 'task.job_received');
    }

    public function test_action_type_executes_stub_and_persists_action_result(): void
    {
        $user = User::factory()->create();

        $token = $this->postJson('/api/v1/auth/token', [
            'email' => $user->email,
            'password' => 'password',
            'device_name' => 'actions-check',
        ])->json('access_token');

        $taskPublicId = $this
            ->withToken($token)
            ->postJson('/api/v1/tasks', [
                'type' => 'analyze_sentiment',
                'input' => ['text' => 'This is great'],
            ])
            ->assertAccepted()
            ->json('task_public_id');

        $task = Task::query()->where('public_id', $taskPublicId)->firstOrFail();

        $this->assertSame(TaskStatus::COMPLETED, $task->status);
        $this->assertSame('analyze_sentiment', $task->meta_json['action_name'] ?? null);
        $this->assertSame('positive', $task->meta_json['action_result']['label'] ?? null);

        $this->assertDatabaseHas('run_logs', [
            'task_id' => $task->id,
            'event_type' => 'task.action_executed',
            'message' => 'Action stub executed for task payload',
        ]);
    }

    public function test_user_cannot_fetch_another_users_task_or_logs(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $otherUserToken = $this->postJson('/api/v1/auth/token', [
            'email' => $otherUser->email,
            'password' => 'password',
            'device_name' => 'other-user',
        ])->json('access_token');

        $task = Task::query()->create([
            'public_id' => '22222222-2222-2222-2222-222222222222',
            'user_id' => $owner->id,
            'type' => 'chat.completion',
            'status' => 'queued',
        ]);

        $this->app['db']->table('run_logs')->insert([
            'task_id' => $task->id,
            'level' => 'info',
            'event_type' => 'task.accepted',
            'message' => 'Hidden log',
            'context_json' => json_encode(['task_public_id' => $task->public_id]),
            'created_at' => now(),
        ]);

        $this
            ->withToken($otherUserToken)
            ->getJson('/api/v1/tasks/'.$task->public_id)
            ->assertNotFound();

        $this
            ->withToken($otherUserToken)
            ->getJson('/api/v1/tasks/'.$task->public_id.'/logs')
            ->assertNotFound();
    }

    // -------------------------------------------------------------------------
    // Multi-step dispatch tests
    // -------------------------------------------------------------------------

    public function test_multi_step_task_dispatches_plan_job_on_service_queue(): void
    {
        Queue::fake();

        $user = User::factory()->create();

        $token = $this->postJson('/api/v1/auth/token', [
            'email' => $user->email,
            'password' => 'password',
            'device_name' => 'phpunit-multi',
        ])->json('access_token');

        $response = $this
            ->withToken($token)
            ->postJson('/api/v1/tasks', [
                'type' => 'multi_step_task',
                'input' => ['prompt' => 'Analyse this text'],
            ]);

        $response->assertAccepted()->assertJson(['status' => TaskStatus::PENDING_PLANNING]);

        $taskPublicId = $response->json('task_public_id');
        $task = Task::query()->where('public_id', $taskPublicId)->firstOrFail();

        $this->assertSame(TaskStatus::PENDING_PLANNING, $task->status);

        Queue::assertPushed(PlanTaskStepsJob::class, function (PlanTaskStepsJob $job) use ($task): bool {
            return $job->taskId === $task->id
                && $job->queue === QueueEnum::SERVICE;
        });

        Queue::assertNotPushed(LogTaskRequestJob::class);
    }

    /**
     * @dataProvider multiStepTypeProvider
     */
    #[DataProvider('multiStepTypeProvider')]
    public function test_all_multi_step_types_dispatch_plan_job(string $type, array $input): void
    {
        Queue::fake();

        $user = User::factory()->create();

        $token = $this->postJson('/api/v1/auth/token', [
            'email' => $user->email,
            'password' => 'password',
            'device_name' => 'phpunit-types',
        ])->json('access_token');

        $response = $this
            ->withToken($token)
            ->postJson('/api/v1/tasks', ['type' => $type, 'input' => $input]);

        $response->assertAccepted()->assertJson(['status' => TaskStatus::PENDING_PLANNING]);

        Queue::assertPushed(PlanTaskStepsJob::class);
        Queue::assertNotPushed(LogTaskRequestJob::class);
    }

    /** @return array<string, array{0: string, 1: array<string, mixed>}> */
    public static function multiStepTypeProvider(): array
    {
        return [
            'multi_step_task' => ['multi_step_task',       ['prompt' => 'Hello']],
            'scrape_and_summarize' => ['scrape_and_summarize',  ['url' => 'https://example.com']],
            'classify_and_reply' => ['classify_and_reply',    ['prompt' => 'Classify me']],
            'ask_ai_once' => ['ask_ai_once',           ['prompt' => 'Say hi']],
        ];
    }

    public function test_authenticated_user_can_dispatch_predefined_pipeline_with_skips(): void
    {
        Queue::fake();

        $user = User::factory()->create();

        $token = $this->postJson('/api/v1/auth/token', [
            'email' => $user->email,
            'password' => 'password',
            'device_name' => 'pipeline-all-actions',
        ])->json('access_token');

        $response = $this
            ->withToken($token)
            ->postJson('/api/v1/tasks/run-pipeline', [
                'pipeline' => 'all_actions',
                'input' => ['prompt' => 'Hello from pipeline'],
                'input_by_action' => [
                    'scrape_url' => ['url' => 'https://example.com'],
                    'analyze_sentiment' => ['text' => 'This is great'],
                ],
                'skip_actions' => ['save_result'],
            ]);

        $response->assertAccepted()
            ->assertJsonPath('status', TaskStatus::PENDING_PLANNING)
            ->assertJsonPath('pipeline.name', 'all_actions')
            ->assertJsonPath('pipeline.skipped_actions.0', 'save_result');

        $task = Task::query()->where('public_id', $response->json('task_public_id'))->firstOrFail();

        $steps = (array) data_get($task->input_json, 'steps', []);
        $this->assertNotEmpty($steps);
        $this->assertFalse(collect($steps)->contains(fn (array $step): bool => ($step['action_name'] ?? '') === 'save_result'));

        Queue::assertPushed(PlanTaskStepsJob::class, function (PlanTaskStepsJob $job) use ($task): bool {
            return $job->taskId === $task->id && $job->queue === QueueEnum::SERVICE;
        });
    }

    public function test_pipeline_endpoint_requires_at_least_one_action_not_skipped(): void
    {
        $user = User::factory()->create();

        $token = $this->postJson('/api/v1/auth/token', [
            'email' => $user->email,
            'password' => 'password',
            'device_name' => 'pipeline-validation',
        ])->json('access_token');

        $allActions = array_keys((array) config('actions.actions', []));

        $this
            ->withToken($token)
            ->postJson('/api/v1/tasks/run-pipeline', [
                'skip_actions' => $allActions,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonStructure(['error' => ['errors' => ['skip_actions']]]);
    }

    public function test_pipeline_endpoint_rejects_unknown_pipeline_name(): void
    {
        $user = User::factory()->create();

        $token = $this->postJson('/api/v1/auth/token', [
            'email' => $user->email,
            'password' => 'password',
            'device_name' => 'pipeline-name-validation',
        ])->json('access_token');

        $this
            ->withToken($token)
            ->postJson('/api/v1/tasks/run-pipeline', [
                'pipeline' => 'does_not_exist',
                'input' => ['prompt' => 'hello'],
            ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonStructure(['error' => ['errors' => ['pipeline']]]);
    }

    public function test_pipeline_endpoint_can_use_text_only_pipeline_definition(): void
    {
        Queue::fake();

        $user = User::factory()->create();

        $token = $this->postJson('/api/v1/auth/token', [
            'email' => $user->email,
            'password' => 'password',
            'device_name' => 'pipeline-text-only',
        ])->json('access_token');

        $response = $this
            ->withToken($token)
            ->postJson('/api/v1/tasks/run-pipeline', [
                'pipeline' => 'text_only',
                'input' => ['prompt' => 'Help me with billing'],
                'skip_actions' => ['save_result'],
            ]);

        $response->assertAccepted()
            ->assertJsonPath('pipeline.name', 'text_only')
            ->assertJsonPath('pipeline.steps_count', 3);

        $task = Task::query()->where('public_id', $response->json('task_public_id'))->firstOrFail();
        $steps = (array) data_get($task->input_json, 'steps', []);

        $this->assertSame(['analyze_sentiment', 'classify_intent', 'generate_reply'], array_column($steps, 'action_name'));
    }

    public function test_authenticated_user_can_dispatch_single_action_pipeline(): void
    {
        Queue::fake();

        $user = User::factory()->create();

        $token = $this->postJson('/api/v1/auth/token', [
            'email' => $user->email,
            'password' => 'password',
            'device_name' => 'single-action-pipeline',
        ])->json('access_token');

        $response = $this
            ->withToken($token)
            ->postJson('/api/v1/tasks/run-action', [
                'action' => 'analyze_sentiment',
                'input' => ['text' => 'This is great'],
            ]);

        $response->assertAccepted()
            ->assertJsonPath('status', TaskStatus::PENDING_PLANNING)
            ->assertJsonPath('action', 'analyze_sentiment');

        $task = Task::query()->where('public_id', $response->json('task_public_id'))->firstOrFail();
        $steps = (array) data_get($task->input_json, 'steps', []);

        $this->assertCount(1, $steps);
        $this->assertSame('analyze_sentiment', $steps[0]['action_name']);

        Queue::assertPushed(PlanTaskStepsJob::class, function (PlanTaskStepsJob $job) use ($task): bool {
            return $job->taskId === $task->id && $job->queue === QueueEnum::SERVICE;
        });
    }

    public function test_non_multi_step_type_still_dispatches_log_job(): void
    {
        Queue::fake();

        $user = User::factory()->create();

        $token = $this->postJson('/api/v1/auth/token', [
            'email' => $user->email,
            'password' => 'password',
            'device_name' => 'phpunit-legacy',
        ])->json('access_token');

        $this
            ->withToken($token)
            ->postJson('/api/v1/tasks', [
                'type' => 'chat.completion',
                'input' => ['prompt' => 'Hello'],
            ])
            ->assertAccepted()
            ->assertJson(['status' => TaskStatus::QUEUED]);

        Queue::assertPushed(LogTaskRequestJob::class);
        Queue::assertNotPushed(PlanTaskStepsJob::class);
    }

    public function test_multi_step_pipeline_completes_and_steps_appear_in_status_response(): void
    {
        // Queue is sync in testing — the full pipeline runs inline.
        $user = User::factory()->create();

        $token = $this->postJson('/api/v1/auth/token', [
            'email' => $user->email,
            'password' => 'password',
            'device_name' => 'phpunit-pipeline',
        ])->json('access_token');

        $taskPublicId = $this
            ->withToken($token)
            ->postJson('/api/v1/tasks', [
                'type' => 'multi_step_task',
                'input' => ['prompt' => 'This is great'],
            ])
            ->assertAccepted()
            ->json('task_public_id');

        // With sync queue the entire pipeline has already run by now.
        $task = Task::query()->where('public_id', $taskPublicId)->firstOrFail();
        $this->assertSame(TaskStatus::COMPLETED, $task->status);
        $this->assertNotNull($task->output_json);

        $response = $this
            ->withToken($token)
            ->getJson('/api/v1/tasks/'.$taskPublicId)
            ->assertOk();

        $steps = $response->json('data.steps');
        $this->assertCount(3, $steps);
        $this->assertSame('analyze_sentiment', $steps[0]['action_name']);
        $this->assertSame('generate_reply', $steps[1]['action_name']);
        $this->assertSame('save_result', $steps[2]['action_name']);

        foreach ($steps as $step) {
            $this->assertSame('completed', $step['status']);
            $this->assertNotNull($step['output']);
        }
    }
}
