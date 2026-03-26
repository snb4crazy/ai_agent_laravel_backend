<?php

namespace Tests\Feature\Api;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskErrorHandlingTest extends TestCase
{
    use RefreshDatabase;

    private function getToken(User $user): string
    {
        return $this->postJson('/api/v1/auth/token', [
            'email' => $user->email,
            'password' => 'password',
            'device_name' => 'error-test',
        ])->json('access_token');
    }

    public function test_unauthenticated_request_returns_json_error(): void
    {
        $this->postJson('/api/v1/tasks', [
            'type' => 'chat.completion',
            'input' => ['prompt' => 'Hello'],
        ])->assertUnauthorized()
            ->assertJsonStructure(['error' => ['code', 'message']])
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }

    public function test_validation_error_returns_structured_json(): void
    {
        $token = $this->getToken(User::factory()->create());

        $this->withToken($token)
            ->postJson('/api/v1/tasks', [
                'type' => '',
                'input' => 'not-an-array',
            ])
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['code', 'message', 'errors']])
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_fetching_nonexistent_task_returns_task_not_found(): void
    {
        $token = $this->getToken(User::factory()->create());

        $this->withToken($token)
            ->getJson('/api/v1/tasks/00000000-0000-0000-0000-000000000000')
            ->assertNotFound()
            ->assertJsonPath('error.code', 'TASK_NOT_FOUND')
            ->assertJsonPath('error.message', 'Task not found.');
    }

    public function test_fetching_another_users_task_returns_task_not_found(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $task = Task::query()->create([
            'public_id' => '11111111-1111-1111-1111-111111111111',
            'user_id' => $owner->id,
            'type' => 'chat.completion',
            'status' => 'queued',
        ]);

        $token = $this->getToken($other);

        $this->withToken($token)
            ->getJson('/api/v1/tasks/'.$task->public_id)
            ->assertNotFound()
            ->assertJsonPath('error.code', 'TASK_NOT_FOUND');
    }

    public function test_unknown_api_route_returns_not_found_json(): void
    {
        $this->getJson('/api/v1/does-not-exist')
            ->assertNotFound()
            ->assertJsonStructure(['error' => ['code', 'message']]);
    }
}
