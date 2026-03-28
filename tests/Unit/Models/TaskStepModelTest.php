<?php

namespace Tests\Unit\Models;

use App\Enums\TaskStatus;
use App\Enums\TaskStepStatus;
use App\Models\Task;
use App\Models\TaskStep;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TaskStepModelTest extends TestCase
{
    use RefreshDatabase;

    private function makeTask(string $status = TaskStatus::PENDING_PLANNING): Task
    {
        return Task::query()->create([
            'public_id' => (string) Str::uuid(),
            'user_id' => User::factory()->create()->id,
            'type' => 'multi_step_task',
            'status' => $status,
            'input_json' => ['prompt' => 'Test input'],
        ]);
    }

    public function test_task_can_have_steps(): void
    {
        $task = $this->makeTask();

        TaskStep::query()->create([
            'task_id' => $task->id,
            'action_name' => 'analyze_sentiment',
            'sequence_order' => 1,
            'status' => TaskStepStatus::PENDING,
            'input_json' => ['text' => 'Hello world'],
        ]);

        TaskStep::query()->create([
            'task_id' => $task->id,
            'action_name' => 'generate_reply',
            'sequence_order' => 2,
            'status' => TaskStepStatus::PENDING,
            'input_json' => ['text' => 'Hello world'],
        ]);

        $this->assertCount(2, $task->steps);
        $this->assertSame('analyze_sentiment', $task->steps->first()->action_name);
        $this->assertSame('generate_reply', $task->steps->last()->action_name);
    }

    public function test_steps_are_ordered_by_sequence(): void
    {
        $task = $this->makeTask();

        TaskStep::query()->create([
            'task_id' => $task->id,
            'action_name' => 'save_result',
            'sequence_order' => 3,
            'status' => TaskStepStatus::PENDING,
        ]);

        TaskStep::query()->create([
            'task_id' => $task->id,
            'action_name' => 'analyze_sentiment',
            'sequence_order' => 1,
            'status' => TaskStepStatus::PENDING,
        ]);

        $task->refresh();
        $this->assertSame('analyze_sentiment', $task->steps->first()->action_name);
        $this->assertSame('save_result', $task->steps->last()->action_name);
    }

    public function test_step_output_is_persisted_as_array(): void
    {
        $task = $this->makeTask();

        $step = TaskStep::query()->create([
            'task_id' => $task->id,
            'action_name' => 'analyze_sentiment',
            'sequence_order' => 1,
            'status' => TaskStepStatus::EXECUTING,
            'input_json' => ['text' => 'This is great'],
            'output_json' => ['label' => 'positive', 'score' => 0.8, 'status' => 'stubbed'],
        ]);

        $step->fill([
            'status' => TaskStepStatus::COMPLETED,
            'finished_at' => now(),
        ])->save();

        $this->assertSame(TaskStepStatus::COMPLETED, $step->fresh()->status);
        $this->assertSame('positive', $step->fresh()->output_json['label']);
    }

    public function test_task_output_json_is_persisted(): void
    {
        $task = $this->makeTask();

        $task->fill([
            'status' => TaskStatus::COMPLETED,
            'output_json' => ['final_reply' => 'Hello from AI'],
            'finished_at' => now(),
        ])->save();

        $fresh = $task->fresh();
        $this->assertSame(TaskStatus::COMPLETED, $fresh->status);
        $this->assertSame('Hello from AI', $fresh->output_json['final_reply']);
    }

    public function test_step_belongs_to_task(): void
    {
        $task = $this->makeTask();

        $step = TaskStep::query()->create([
            'task_id' => $task->id,
            'action_name' => 'scrape_url',
            'sequence_order' => 1,
            'status' => TaskStepStatus::PENDING,
        ]);

        $this->assertSame($task->id, $step->task->id);
    }
}
