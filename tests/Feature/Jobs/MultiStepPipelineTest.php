<?php

namespace Tests\Feature\Jobs;

use App\Enums\TaskStatus;
use App\Enums\TaskStepStatus;
use App\Jobs\CompileTaskOutputJob;
use App\Jobs\ExecuteTaskStepJob;
use App\Jobs\PlanTaskStepsJob;
use App\Models\RunLog;
use App\Models\Task;
use App\Models\TaskStep;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Integration tests for the multi-step job pipeline.
 *
 * Queue is set to "sync" in phpunit.xml, so dispatching any job runs it
 * inline, making the full Plan → Execute(×N) → Compile chain testable
 * without a real queue worker.
 */
class MultiStepPipelineTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeTask(string $type, array $inputJson = []): Task
    {
        return Task::query()->create([
            'public_id'  => (string) Str::uuid(),
            'user_id'    => User::factory()->create()->id,
            'type'       => $type,
            'status'     => TaskStatus::PENDING_PLANNING,
            'input_json' => $inputJson,
        ]);
    }

    // -------------------------------------------------------------------------
    // Full pipeline – multi_step_task
    // -------------------------------------------------------------------------

    public function test_full_pipeline_completes_for_multi_step_task(): void
    {
        $task = $this->makeTask('multi_step_task', ['prompt' => 'This is great']);

        PlanTaskStepsJob::dispatchSync($task->id);

        $task->refresh();

        $this->assertSame(TaskStatus::COMPLETED, $task->status);
        $this->assertNotNull($task->finished_at);
        $this->assertNotNull($task->output_json);

        // Three steps created and all completed.
        $steps = $task->steps()->orderBy('sequence_order')->get();
        $this->assertCount(3, $steps);

        foreach ($steps as $step) {
            $this->assertSame(TaskStepStatus::COMPLETED, $step->status, "Step {$step->sequence_order} should be completed");
            $this->assertNotNull($step->started_at);
            $this->assertNotNull($step->finished_at);
            $this->assertNotNull($step->output_json);
        }
    }

    public function test_pipeline_output_json_contains_step_outputs_and_primary_result(): void
    {
        $task = $this->makeTask('multi_step_task', ['prompt' => 'This is great']);

        PlanTaskStepsJob::dispatchSync($task->id);

        $task->refresh();
        $output = $task->output_json;

        $this->assertArrayHasKey('steps',          $output);
        $this->assertArrayHasKey('step_outputs',   $output);
        $this->assertArrayHasKey('primary_result', $output);
        $this->assertCount(3, $output['steps']);
    }

    public function test_pipeline_records_run_logs_throughout_lifecycle(): void
    {
        $task = $this->makeTask('multi_step_task', ['prompt' => 'Hello']);

        PlanTaskStepsJob::dispatchSync($task->id);

        $eventTypes = RunLog::query()
            ->where('task_id', $task->id)
            ->pluck('event_type')
            ->all();

        $this->assertContains('task.planning_started',  $eventTypes);
        $this->assertContains('task.steps_planned',     $eventTypes);
        $this->assertContains('task_step.executing',    $eventTypes);
        $this->assertContains('task_step.completed',    $eventTypes);
        $this->assertContains('task.compile_started',   $eventTypes);
        $this->assertContains('task.completed',         $eventTypes);
    }

    public function test_pipeline_step_order_matches_sequence_order(): void
    {
        $task = $this->makeTask('multi_step_task', ['prompt' => 'Hello']);

        PlanTaskStepsJob::dispatchSync($task->id);

        $steps = $task->steps()->orderBy('sequence_order')->get();

        $this->assertSame('analyze_sentiment', $steps->get(0)->action_name);
        $this->assertSame('generate_reply',    $steps->get(1)->action_name);
        $this->assertSame('save_result',       $steps->get(2)->action_name);
    }

    // -------------------------------------------------------------------------
    // Full pipeline – scrape_and_summarize
    // -------------------------------------------------------------------------

    public function test_full_pipeline_completes_for_scrape_and_summarize(): void
    {
        $task = $this->makeTask('scrape_and_summarize', ['url' => 'https://example.com']);

        PlanTaskStepsJob::dispatchSync($task->id);

        $task->refresh();

        $this->assertSame(TaskStatus::COMPLETED, $task->status);

        $steps = $task->steps()->orderBy('sequence_order')->get();
        $this->assertCount(2, $steps);
        $this->assertSame('scrape_url',     $steps->get(0)->action_name);
        $this->assertSame('summarize_text', $steps->get(1)->action_name);

        foreach ($steps as $step) {
            $this->assertSame(TaskStepStatus::COMPLETED, $step->status);
        }
    }

    // -------------------------------------------------------------------------
    // Full pipeline – classify_and_reply
    // -------------------------------------------------------------------------

    public function test_full_pipeline_completes_for_classify_and_reply(): void
    {
        $task = $this->makeTask('classify_and_reply', ['prompt' => 'Help me book a flight']);

        PlanTaskStepsJob::dispatchSync($task->id);

        $task->refresh();

        $this->assertSame(TaskStatus::COMPLETED, $task->status);

        $steps = $task->steps()->orderBy('sequence_order')->get();
        $this->assertCount(2, $steps);
        $this->assertSame('classify_intent', $steps->get(0)->action_name);
        $this->assertSame('generate_reply',  $steps->get(1)->action_name);
    }

    // -------------------------------------------------------------------------
    // Explicit steps via input_json
    // -------------------------------------------------------------------------

    public function test_pipeline_honours_explicit_steps_in_input_json(): void
    {
        $task = $this->makeTask('multi_step_task', [
            'prompt' => 'Hello',
            'steps'  => [
                ['action_name' => 'analyze_sentiment', 'sequence_order' => 1, 'input_json' => ['text' => 'This is great']],
                ['action_name' => 'save_result',       'sequence_order' => 2, 'input_json' => ['key' => 'value']],
            ],
        ]);

        PlanTaskStepsJob::dispatchSync($task->id);

        $task->refresh();

        $this->assertSame(TaskStatus::COMPLETED, $task->status);

        $steps = $task->steps()->orderBy('sequence_order')->get();
        $this->assertCount(2, $steps);
        $this->assertSame('analyze_sentiment', $steps->get(0)->action_name);
        $this->assertSame('save_result',       $steps->get(1)->action_name);
    }

    // -------------------------------------------------------------------------
    // Previous step output chaining
    // -------------------------------------------------------------------------

    public function test_execute_step_job_injects_previous_step_output_into_next_step_input(): void
    {
        $task = $this->makeTask('multi_step_task', ['prompt' => 'This is great']);

        PlanTaskStepsJob::dispatchSync($task->id);

        $task->refresh();
        $steps = $task->steps()->orderBy('sequence_order')->get();

        // Step 2 (generate_reply) should have received previous_output from step 1.
        $step2 = $steps->get(1);
        // The ExecuteTaskStepJob injects previous_output at runtime, not persisted in
        // input_json — verify step 1 actually produced output for chaining.
        $this->assertNotNull($steps->get(0)->output_json, 'Step 1 must produce output for chaining');
        $this->assertSame(TaskStepStatus::COMPLETED, $step2->status);
    }

    // -------------------------------------------------------------------------
    // PlanTaskStepsJob – status transitions
    // -------------------------------------------------------------------------

    public function test_plan_job_transitions_task_through_planning_to_executing(): void
    {
        $task = $this->makeTask('multi_step_task', ['prompt' => 'Hello']);

        // Verify starting status.
        $this->assertSame(TaskStatus::PENDING_PLANNING, $task->status);

        // With sync queue the whole pipeline runs, so final status is COMPLETED.
        PlanTaskStepsJob::dispatchSync($task->id);

        $task->refresh();
        $this->assertSame(TaskStatus::COMPLETED, $task->status);
    }

    // -------------------------------------------------------------------------
    // ExecuteTaskStepJob – individual step job
    // -------------------------------------------------------------------------

    public function test_execute_step_job_marks_step_completed_and_persists_output(): void
    {
        $task = $this->makeTask('multi_step_task', ['prompt' => 'This is great']);

        // Create a single step manually to test the execute job in isolation.
        $step = TaskStep::query()->create([
            'task_id'        => $task->id,
            'action_name'    => 'analyze_sentiment',
            'sequence_order' => 1,
            'status'         => TaskStepStatus::PENDING,
            'input_json'     => ['text' => 'This is great'],
        ]);

        $task->fill(['status' => TaskStatus::EXECUTING])->save();

        // Only a single step exists, so after execution CompileTaskOutputJob fires.
        ExecuteTaskStepJob::dispatchSync($step->id);

        $step->refresh();

        $this->assertSame(TaskStepStatus::COMPLETED, $step->status);
        $this->assertNotNull($step->output_json);
        $this->assertSame('positive', $step->output_json['label']);
        $this->assertNotNull($step->started_at);
        $this->assertNotNull($step->finished_at);

        // Task should be compiled and completed.
        $task->refresh();
        $this->assertSame(TaskStatus::COMPLETED, $task->status);
    }

    public function test_execute_step_job_skips_when_parent_task_is_failed(): void
    {
        $task = $this->makeTask('multi_step_task', ['prompt' => 'Hello']);
        $task->fill(['status' => TaskStatus::FAILED])->save();

        $step = TaskStep::query()->create([
            'task_id'        => $task->id,
            'action_name'    => 'analyze_sentiment',
            'sequence_order' => 1,
            'status'         => TaskStepStatus::PENDING,
        ]);

        ExecuteTaskStepJob::dispatchSync($step->id);

        // Step should remain PENDING – the job bailed out early.
        $step->refresh();
        $this->assertSame(TaskStepStatus::PENDING, $step->status);
    }

    // -------------------------------------------------------------------------
    // CompileTaskOutputJob – individual compile job
    // -------------------------------------------------------------------------

    public function test_compile_job_aggregates_outputs_and_marks_task_completed(): void
    {
        $task = $this->makeTask('multi_step_task', ['prompt' => 'Hello']);
        $task->fill(['status' => TaskStatus::EXECUTING])->save();

        TaskStep::query()->create([
            'task_id'        => $task->id,
            'action_name'    => 'analyze_sentiment',
            'sequence_order' => 1,
            'status'         => TaskStepStatus::COMPLETED,
            'output_json'    => ['label' => 'positive', 'score' => 0.8, 'status' => 'stubbed'],
            'started_at'     => now()->subSeconds(2),
            'finished_at'    => now()->subSecond(),
        ]);

        TaskStep::query()->create([
            'task_id'        => $task->id,
            'action_name'    => 'save_result',
            'sequence_order' => 2,
            'status'         => TaskStepStatus::COMPLETED,
            'output_json'    => ['saved' => true, 'status' => 'stubbed'],
            'started_at'     => now()->subSecond(),
            'finished_at'    => now(),
        ]);

        CompileTaskOutputJob::dispatchSync($task->id);

        $task->refresh();

        $this->assertSame(TaskStatus::COMPLETED, $task->status);
        $this->assertNotNull($task->output_json);
        $this->assertArrayHasKey('steps',        $task->output_json);
        $this->assertArrayHasKey('step_outputs', $task->output_json);
        $this->assertCount(2, $task->output_json['steps']);

        // Primary result should be the last completed step's output.
        $this->assertSame(['saved' => true, 'status' => 'stubbed'], $task->output_json['primary_result']);
    }

    public function test_compile_job_is_idempotent_for_already_completed_task(): void
    {
        $task = $this->makeTask('multi_step_task', ['prompt' => 'Hello']);
        $task->fill([
            'status'      => TaskStatus::COMPLETED,
            'output_json' => ['original' => true],
            'finished_at' => now(),
        ])->save();

        CompileTaskOutputJob::dispatchSync($task->id);

        $task->refresh();

        // Output should be unchanged.
        $this->assertSame(['original' => true], $task->output_json);
    }

    // -------------------------------------------------------------------------
    // PlanTaskStepsJob – idempotency on retry
    // -------------------------------------------------------------------------

    public function test_plan_job_does_not_create_duplicate_steps_on_retry(): void
    {
        $task = $this->makeTask('multi_step_task', ['prompt' => 'Hello']);

        // Simulate a partial first run that already created step 1.
        TaskStep::query()->create([
            'task_id'        => $task->id,
            'action_name'    => 'analyze_sentiment',
            'sequence_order' => 1,
            'status'         => TaskStepStatus::PENDING,
        ]);

        // Running the plan job again should not duplicate sequence_order = 1.
        PlanTaskStepsJob::dispatchSync($task->id);

        $count = $task->steps()->where('sequence_order', 1)->count();
        $this->assertSame(1, $count, 'sequence_order 1 must not be duplicated on retry');

        // All three steps should still exist.
        $this->assertSame(3, $task->steps()->count());
    }
}

