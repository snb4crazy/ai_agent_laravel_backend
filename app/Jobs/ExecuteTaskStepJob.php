<?php

namespace App\Jobs;

use App\Enums\QueueEnum;
use App\Enums\TaskStatus;
use App\Enums\TaskStepStatus;
use App\Models\Task;
use App\Models\TaskStep;
use App\Services\TaskActionService;
use App\Traits\LogsTaskActivity;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Job 2 of the multi-step agent flow.
 *
 * Executes a single TaskStep via TaskActionService, persists the output, then
 * either dispatches the next pending step or CompileTaskOutputJob when all
 * steps are finished.
 */
class ExecuteTaskStepJob implements ShouldQueue
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
        public readonly int $taskStepId,
    ) {}

    // -------------------------------------------------------------------------

    public function handle(TaskActionService $actionService): void
    {
        /** @var TaskStep $step */
        $step = TaskStep::query()->findOrFail($this->taskStepId);
        $task = $step->task;

        // Guard: if the parent task already failed, skip silently.
        if ($task->status === TaskStatus::FAILED) {
            return;
        }

        try {
            // -----------------------------------------------------------------
            // Mark step as executing.
            // -----------------------------------------------------------------
            $step->fill([
                'status' => TaskStepStatus::EXECUTING,
                'started_at' => now(),
            ])->save();

            $this->log($task, 'info', 'task_step.executing', 'Executing step '.$step->sequence_order, [
                'action_name' => $step->action_name,
                'sequence_order' => $step->sequence_order,
            ]);

            // -----------------------------------------------------------------
            // Build step input, optionally enriched with previous step output.
            // -----------------------------------------------------------------
            $input = $this->buildStepInput($step);

            // -----------------------------------------------------------------
            // Execute the action.
            // -----------------------------------------------------------------
            $result = $actionService->execute($step->action_name, $input);

            // -----------------------------------------------------------------
            // Persist the step result.
            // -----------------------------------------------------------------
            $step->fill([
                'status' => TaskStepStatus::COMPLETED,
                'output_json' => $result['executed'] ? $result['result'] : ['status' => 'no_action_found'],
                'finished_at' => now(),
            ])->save();

            $this->log($task, 'info', 'task_step.completed', 'Step '.$step->sequence_order.' completed', [
                'action_name' => $step->action_name,
                'sequence_order' => $step->sequence_order,
                'executed' => $result['executed'],
                'result' => $result['result'],
            ]);

            // -----------------------------------------------------------------
            // Dispatch next step or compile job.
            // -----------------------------------------------------------------
            $task->refresh();
            $this->dispatchNext($task, $step);

        } catch (\Throwable $e) {
            // Mark the step as failed.
            try {
                $step->fill([
                    'status' => TaskStepStatus::FAILED,
                    'error_message' => $e->getMessage(),
                    'finished_at' => now(),
                ])->save();
            } catch (\Throwable) {
                // Best-effort.
            }

            // Mark the parent task as failed.
            try {
                $task->fill([
                    'status' => TaskStatus::FAILED,
                    'error_message' => 'Step '.$step->sequence_order.' ('.$step->action_name.') failed: '.$e->getMessage(),
                    'finished_at' => now(),
                ])->save();

                $this->log($task, 'error', 'task_step.failed', 'Step '.$step->sequence_order.' failed: '.$e->getMessage(), [
                    'action_name' => $step->action_name,
                    'sequence_order' => $step->sequence_order,
                    'exception' => $e::class,
                ]);
            } catch (\Throwable) {
                // Best-effort.
            }

            Log::error('ExecuteTaskStepJob failed', [
                'task_step_id' => $this->taskStepId,
                'task_id' => $task->id ?? null,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Build the input array for this step.
     *
     * If the step has no explicit `input_json`, we forward the task's
     * `input_json`. We also inject the output of the immediately preceding
     * completed step under the `previous_output` key so actions can chain.
     *
     * @return array<string, mixed>
     */
    private function buildStepInput(TaskStep $step): array
    {
        $input = (array) ($step->input_json ?? $step->task->input_json ?? []);

        /** @var TaskStep|null $previousStep */
        $previousStep = $step->task->steps()
            ->where('sequence_order', '<', $step->sequence_order)
            ->where('status', TaskStepStatus::COMPLETED)
            ->orderByDesc('sequence_order')
            ->first();

        if ($previousStep !== null && ! empty($previousStep->output_json)) {
            $input['previous_output'] = $previousStep->output_json;
        }

        return $input;
    }

    /**
     * After a successful step, dispatch the next pending step or the compile job.
     */
    private function dispatchNext(Task $task, TaskStep $completedStep): void
    {
        /** @var TaskStep|null $nextStep */
        $nextStep = $task->steps()
            ->where('status', TaskStepStatus::PENDING)
            ->where('sequence_order', '>', $completedStep->sequence_order)
            ->orderBy('sequence_order')
            ->first();

        if ($nextStep !== null) {
            ExecuteTaskStepJob::dispatch($nextStep->id)->onQueue(QueueEnum::TASK);

            $this->log($task, 'info', 'task_step.next_dispatched', 'Dispatched next step '.$nextStep->sequence_order, [
                'next_step_id' => $nextStep->id,
                'action_name' => $nextStep->action_name,
                'sequence_order' => $nextStep->sequence_order,
            ]);

            return;
        }

        // No more pending steps – check for failures before compiling.
        $hasFailures = $task->steps()
            ->where('status', TaskStepStatus::FAILED)
            ->exists();

        if ($hasFailures) {
            $this->log($task, 'warning', 'task.steps_finished_with_failures', 'All steps processed but some failed; skipping compile');

            return;
        }

        CompileTaskOutputJob::dispatch($task->id)->onQueue(QueueEnum::TASK);

        $this->log($task, 'info', 'task.compile_dispatched', 'All steps done – dispatching compile job');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------
    // log() is provided by LogsTaskActivity trait.
    // buildStepInput() and dispatchNext() are defined above.
}
