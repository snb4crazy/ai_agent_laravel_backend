<?php

namespace App\Jobs;

use App\Enums\QueueEnum;
use App\Enums\TaskStatus;
use App\Enums\TaskStepStatus;
use App\Models\Task;
use App\Models\TaskStep;
use App\Services\TaskPlannerService;
use App\Traits\LogsTaskActivity;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Job 1 of the multi-step agent flow.
 *
 * Reads the task, calls TaskPlannerService to decide which action steps are
 * needed, persists them as TaskStep records, and dispatches
 * ExecuteTaskStepJob for the first pending step.
 */
class PlanTaskStepsJob implements ShouldQueue
{
    use LogsTaskActivity;
    use Queueable;

    /**
     * Maximum number of attempts before the job is marked failed.
     */
    public int $tries = 3;

    /**
     * Seconds to wait before retrying after a failure.
     *
     * @var array<int, int>
     */
    public array $backoff = [10, 30, 60];

    public function __construct(
        public readonly int $taskId,
    ) {}

    // -------------------------------------------------------------------------

    public function handle(TaskPlannerService $planner): void
    {
        $task = null;

        try {
            /** @var Task $task */
            $task = Task::query()->findOrFail($this->taskId);

            // Mark task as actively planning.
            $task->fill([
                'status' => TaskStatus::PLANNING,
                'started_at' => $task->started_at ?? now(),
            ])->save();

            $this->log($task, 'info', 'task.planning_started', 'Planning job started – resolving steps');

            // -----------------------------------------------------------------
            // Ask the planner for the step definitions.
            // -----------------------------------------------------------------
            $stepDefs = $planner->plan($task);

            if (empty($stepDefs)) {
                throw new \RuntimeException('Planner returned no steps for task '.$task->public_id);
            }

            // -----------------------------------------------------------------
            // Persist TaskStep records (skip duplicates on retry).
            // -----------------------------------------------------------------
            $existingOrders = $task->steps()
                ->pluck('sequence_order')
                ->all();

            foreach ($stepDefs as $def) {
                if (in_array($def['sequence_order'], $existingOrders, true)) {
                    continue; // idempotent retry guard
                }

                TaskStep::query()->create([
                    'task_id' => $task->id,
                    'action_name' => $def['action_name'],
                    'sequence_order' => $def['sequence_order'],
                    'input_json' => $def['input_json'] ?? null,
                    'status' => TaskStepStatus::PENDING,
                ]);
            }

            $task->refresh();

            $this->log($task, 'info', 'task.steps_planned', 'Steps created, dispatching execution', [
                'step_count' => $task->steps->count(),
                'steps' => $task->steps->map(fn (TaskStep $s) => [
                    'action_name' => $s->action_name,
                    'sequence_order' => $s->sequence_order,
                ])->all(),
            ]);

            // -----------------------------------------------------------------
            // Transition task to executing and dispatch the first step.
            // -----------------------------------------------------------------
            $task->fill(['status' => TaskStatus::EXECUTING])->save();

            /** @var TaskStep|null $firstStep */
            $firstStep = $task->steps
                ->where('status', TaskStepStatus::PENDING)
                ->sortBy('sequence_order')
                ->first();

            if ($firstStep !== null) {
                ExecuteTaskStepJob::dispatch($firstStep->id)->onQueue(QueueEnum::TASK);
            } else {
                // All steps were already done (unlikely on first run, but safe).
                CompileTaskOutputJob::dispatch($task->id)->onQueue(QueueEnum::TASK);
            }

        } catch (\Throwable $e) {
            if ($task !== null) {
                try {
                    $task->fill([
                        'status' => TaskStatus::FAILED,
                        'error_message' => $e->getMessage(),
                        'finished_at' => now(),
                    ])->save();

                    $this->log($task, 'error', 'task.planning_failed', 'Planning job failed: '.$e->getMessage(), [
                        'exception' => $e::class,
                    ]);
                } catch (\Throwable) {
                    // Best-effort status update; original exception is rethrown.
                }
            }

            Log::error('PlanTaskStepsJob failed', [
                'task_id' => $this->taskId,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------
    // log() is provided by LogsTaskActivity trait.
}
