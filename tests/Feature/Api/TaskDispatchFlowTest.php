<?php

namespace Tests\Feature\Api;

use App\Enums\QueueEnum;
use App\Jobs\LogTaskRequestJob;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
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
            'status' => 'queued',
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
}
