<?php

namespace Tests\Feature\Database;

use App\Models\AgentRun;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AiControlPlaneSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_control_plane_tables_are_created(): void
    {
        $tables = [
            'tasks',
            'prompt_templates',
            'prompt_versions',
            'agent_runs',
            'run_logs',
            'run_usage',
            'run_artifacts',
            'outbox_events',
        ];

        foreach ($tables as $table) {
            $this->assertTrue(
                $this->app['db']->getSchemaBuilder()->hasTable($table),
                sprintf('Expected table %s to exist.', $table)
            );
        }
    }

    public function test_tasks_idempotency_key_is_unique_when_present(): void
    {
        $user = User::factory()->create();

        Task::create([
            'public_id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'type' => 'chat.completion',
            'idempotency_key' => 'same-key',
        ]);

        $this->expectException(QueryException::class);

        Task::create([
            'public_id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'type' => 'chat.completion',
            'idempotency_key' => 'same-key',
        ]);
    }

    public function test_agent_run_is_deleted_when_parent_task_is_deleted(): void
    {
        $task = Task::create([
            'public_id' => (string) Str::uuid(),
            'type' => 'chat.completion',
        ]);

        $run = AgentRun::create([
            'public_id' => (string) Str::uuid(),
            'task_id' => $task->id,
            'run_number' => 1,
            'status' => 'pending',
        ]);

        $task->delete();

        $this->assertDatabaseMissing('agent_runs', ['id' => $run->id]);
    }

    public function test_run_usage_allows_only_one_row_per_agent_run(): void
    {
        $task = Task::create([
            'public_id' => (string) Str::uuid(),
            'type' => 'chat.completion',
        ]);

        $run = AgentRun::create([
            'public_id' => (string) Str::uuid(),
            'task_id' => $task->id,
            'run_number' => 1,
            'status' => 'completed',
        ]);

        $this->app['db']->table('run_usage')->insert([
            'agent_run_id' => $run->id,
            'input_tokens' => 10,
            'output_tokens' => 20,
            'total_tokens' => 30,
            'estimated_cost_usd' => 0.001,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        $this->app['db']->table('run_usage')->insert([
            'agent_run_id' => $run->id,
            'input_tokens' => 11,
            'output_tokens' => 21,
            'total_tokens' => 32,
            'estimated_cost_usd' => 0.002,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

