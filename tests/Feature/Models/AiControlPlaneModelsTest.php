<?php

namespace Tests\Feature\Models;

use App\Models\AgentRun;
use App\Models\OutboxEvent;
use App\Models\PromptTemplate;
use App\Models\PromptVersion;
use App\Models\RunArtifact;
use App\Models\RunLog;
use App\Models\RunUsage;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class AiControlPlaneModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_task_relationships_link_user_runs_and_logs(): void
    {
        $user = User::factory()->create();

        $task = Task::create([
            'public_id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'type' => 'chat.completion',
            'input_json' => ['prompt' => 'hello'],
            'meta_json' => ['source' => 'test'],
        ]);

        $run = AgentRun::create([
            'public_id' => (string) Str::uuid(),
            'task_id' => $task->id,
            'run_number' => 1,
            'status' => 'pending',
        ]);

        RunLog::create([
            'task_id' => $task->id,
            'agent_run_id' => $run->id,
            'event_type' => 'task.created',
            'message' => 'Task created in test',
        ]);

        $this->assertTrue($task->user->is($user));
        $this->assertCount(1, $task->runs);
        $this->assertCount(1, $task->logs);
        $this->assertIsArray($task->input_json);
        $this->assertIsArray($task->meta_json);
    }

    public function test_agent_run_relationships_include_retries_usage_and_artifacts(): void
    {
        $task = Task::create([
            'public_id' => (string) Str::uuid(),
            'type' => 'chat.completion',
        ]);

        $firstRun = AgentRun::create([
            'public_id' => (string) Str::uuid(),
            'task_id' => $task->id,
            'run_number' => 1,
            'status' => 'errored',
        ]);

        $retryRun = AgentRun::create([
            'public_id' => (string) Str::uuid(),
            'task_id' => $task->id,
            'retry_of_run_id' => $firstRun->id,
            'run_number' => 2,
            'status' => 'completed',
            'request_payload' => ['messages' => [['role' => 'user', 'content' => 'hello']]],
            'response_payload' => ['choices' => [['message' => ['content' => 'hi']]]],
            'started_at' => now(),
            'finished_at' => now()->addSecond(),
        ]);

        RunUsage::create([
            'agent_run_id' => $retryRun->id,
            'input_tokens' => 10,
            'output_tokens' => 20,
            'total_tokens' => 30,
            'estimated_cost_usd' => 0.001234,
        ]);

        RunArtifact::create([
            'agent_run_id' => $retryRun->id,
            'type' => 'text',
            'name' => 'final_answer',
            'content_json' => ['text' => 'hi'],
        ]);

        $this->assertTrue($retryRun->retryOfRun->is($firstRun));
        $this->assertCount(1, $firstRun->retries);
        $this->assertNotNull($retryRun->usage);
        $this->assertCount(1, $retryRun->artifacts);
        $this->assertIsArray($retryRun->request_payload);
        $this->assertIsArray($retryRun->response_payload);
        $this->assertInstanceOf(Carbon::class, $retryRun->started_at);
    }

    public function test_prompt_template_and_version_relationships_work(): void
    {
        $user = User::factory()->create();

        $template = PromptTemplate::create([
            'public_id' => (string) Str::uuid(),
            'key' => 'agent.default',
            'name' => 'Default Agent Prompt',
            'created_by_user_id' => $user->id,
        ]);

        $version = PromptVersion::create([
            'prompt_template_id' => $template->id,
            'version' => 1,
            'content' => 'You are an assistant.',
            'variables_schema' => ['language' => 'string'],
            'created_by_user_id' => $user->id,
        ]);

        $this->assertTrue($template->createdByUser->is($user));
        $this->assertCount(1, $template->versions);
        $this->assertTrue($version->template->is($template));
        $this->assertTrue($version->createdByUser->is($user));
        $this->assertIsArray($version->variables_schema);
    }

    public function test_outbox_event_casts_payload_and_timestamps(): void
    {
        $event = OutboxEvent::create([
            'public_id' => (string) Str::uuid(),
            'event_name' => 'task.created',
            'aggregate_type' => 'task',
            'aggregate_id' => 42,
            'payload_json' => ['task_public_id' => (string) Str::uuid()],
            'available_at' => now(),
        ]);

        $this->assertIsArray($event->payload_json);
        $this->assertInstanceOf(Carbon::class, $event->available_at);
    }
}

