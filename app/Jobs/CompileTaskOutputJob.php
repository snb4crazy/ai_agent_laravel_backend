<?php

namespace App\Jobs;

use App\Enums\TaskStatus;
use App\Enums\TaskStepStatus;
use App\Models\Task;
use App\Models\TaskStep;
use App\Traits\LogsTaskActivity;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Job 3 of the multi-step agent flow.
 *
 * Aggregates the output of all completed TaskStep records into
 * task.output_json, then marks the task as completed.
 *
 * This job is dispatched by ExecuteTaskStepJob after every step has a
 * terminal status (completed / failed).
 */
class CompileTaskOutputJob implements ShouldQueue
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

    public function handle(): void
    {
        $task = null;

        try {
            /** @var Task $task */
            $task = Task::query()->findOrFail($this->taskId);

            // Safety guard – don't overwrite an already-completed/failed task.
            if (in_array($task->status, [TaskStatus::COMPLETED, TaskStatus::FAILED], true)) {
                return;
            }

            $this->log($task, 'info', 'task.compile_started', 'Compiling step outputs into final task result');

            // -----------------------------------------------------------------
            // Aggregate all step outputs into a structured output payload.
            // -----------------------------------------------------------------
            $steps = $task->steps()->orderBy('sequence_order')->get();

            $aggregated = [];
            $stepSummaries = [];

            /** @var TaskStep $step */
            foreach ($steps as $step) {
                $key = $step->action_name.'_'.$step->sequence_order;
                $aggregated[$key] = $step->output_json;

                $stepSummaries[] = [
                    'action_name' => $step->action_name,
                    'sequence_order' => $step->sequence_order,
                    'status' => $step->status,
                    'output' => $step->output_json,
                ];
            }

            // The last completed step's output becomes the primary result.
            /** @var TaskStep|null $lastCompleted */
            $lastCompleted = $steps
                ->where('status', TaskStepStatus::COMPLETED)
                ->sortByDesc('sequence_order')
                ->first();

            $outputJson = [
                'steps' => $stepSummaries,
                'step_outputs' => $aggregated,
                'primary_result' => $lastCompleted?->output_json,
            ];

            // -----------------------------------------------------------------
            // Persist and mark the task complete.
            // -----------------------------------------------------------------
            $task->fill([
                'status' => TaskStatus::COMPLETED,
                'output_json' => $outputJson,
                'finished_at' => now(),
            ])->save();

            $this->log($task, 'info', 'task.completed', 'Task compiled and marked as completed', [
                'step_count' => $steps->count(),
            ]);

        } catch (\Throwable $e) {
            if ($task !== null) {
                try {
                    $task->fill([
                        'status' => TaskStatus::FAILED,
                        'error_message' => 'Compile failed: '.$e->getMessage(),
                        'finished_at' => now(),
                    ])->save();

                    $this->log($task, 'error', 'task.compile_failed', 'Compile job failed: '.$e->getMessage(), [
                        'exception' => $e::class,
                    ]);
                } catch (\Throwable) {
                    // Best-effort.
                }
            }

            Log::error('CompileTaskOutputJob failed', [
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
