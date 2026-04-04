<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\QueueEnum;
use App\Enums\TaskStatus;
use App\Exceptions\TaskException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RunActionsPipelineRequest;
use App\Http\Requests\Api\V1\RunSingleActionRequest;
use App\Http\Requests\Api\V1\StoreTaskDispatchRequest;
use App\Jobs\LogTaskRequestJob;
use App\Jobs\PlanTaskStepsJob;
use App\Models\RunLog;
use App\Models\Task;
use App\Models\TaskStep;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TaskController extends Controller
{
    /**
     * Validate incoming payload, persist a task, and enqueue follow-up work.
     */
    public function store(StoreTaskDispatchRequest $request): JsonResponse
    {
        $validated = $request->validated();

        /** Multi-step task types that use the plan → execute → compile pipeline. */
        $multiStepTypes = ['multi_step_task', 'scrape_and_summarize', 'classify_and_reply', 'ask_ai_once'];
        $isMultiStep = in_array($validated['type'], $multiStepTypes, true);

        try {
            $task = Task::query()->create([
                'public_id' => (string) Str::uuid(),
                'user_id' => $request->user()?->id,
                'type' => $validated['type'],
                'status' => $isMultiStep ? TaskStatus::PENDING_PLANNING : TaskStatus::QUEUED,
                'input_json' => $validated['input'],
                'meta_json' => $validated['meta'] ?? null,
            ]);

            RunLog::query()->create([
                'task_id' => $task->id,
                'level' => 'info',
                'event_type' => 'task.accepted',
                'message' => 'Task request accepted and persisted',
                'context_json' => [
                    'task_public_id' => $task->public_id,
                    'user_id' => $request->user()?->id,
                    'input' => $validated,
                ],
            ]);

            Log::info('Task dispatch request accepted', [
                'task_public_id' => $task->public_id,
                'user_id' => $request->user()?->id,
                'input' => $validated,
            ]);

            if ($isMultiStep) {
                PlanTaskStepsJob::dispatch($task->id)->onQueue(QueueEnum::SERVICE);
            } else {
                LogTaskRequestJob::dispatch($task->id)->onQueue(QueueEnum::TASK);
            }
        } catch (\Throwable $e) {
            throw TaskException::dispatchFailed($e);
        }

        return response()->json([
            'status' => $task->status,
            'task_public_id' => $task->public_id,
            'dispatch_id' => $task->public_id,
            'links' => $this->taskLinks($task),
        ], 202);
    }

    /**
     * Build and run a predefined pipeline of all configured actions.
     */
    public function runPipeline(RunActionsPipelineRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $pipelineName = (string) ($validated['pipeline'] ?? config('pipelines.default', 'all_actions'));
        $pipelineDefinitions = (array) config('pipelines.definitions', []);
        $pipelineActions = array_values(array_filter((array) data_get($pipelineDefinitions, $pipelineName.'.actions', []), 'is_string'));
        $skipActions = array_values(array_filter((array) ($validated['skip_actions'] ?? []), 'is_string'));

        $baseInput = (array) ($validated['input'] ?? []);
        $inputByAction = (array) ($validated['input_by_action'] ?? []);

        $steps = [];
        foreach ($pipelineActions as $actionName) {
            if (in_array($actionName, $skipActions, true)) {
                continue;
            }

            $perActionInput = isset($inputByAction[$actionName]) && is_array($inputByAction[$actionName])
                ? $inputByAction[$actionName]
                : [];

            $steps[] = [
                'action_name' => $actionName,
                'sequence_order' => count($steps) + 1,
                'input_json' => array_merge($baseInput, $perActionInput),
            ];
        }

        if ($steps === []) {
            throw ValidationException::withMessages([
                'skip_actions' => ['All actions were skipped. Keep at least one action enabled.'],
            ]);
        }

        $taskInput = array_merge($baseInput, ['steps' => $steps]);
        $taskMeta = array_merge((array) ($validated['meta'] ?? []), [
            'pipeline_name' => $pipelineName,
            'skipped_actions' => $skipActions,
        ]);

        $task = $this->createPipelineTask($request, 'pipeline_'.$pipelineName, $taskInput, $taskMeta);

        return response()->json([
            'status' => $task->status,
            'task_public_id' => $task->public_id,
            'dispatch_id' => $task->public_id,
            'links' => $this->taskLinks($task),
            'pipeline' => [
                'name' => $pipelineName,
                'steps_count' => count($steps),
                'skipped_actions' => $skipActions,
            ],
        ], 202);
    }

    /**
     * Run a single action provided by request payload via the standard pipeline.
     */
    public function runAction(RunSingleActionRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $action = (string) $validated['action'];
        $input = (array) ($validated['input'] ?? []);

        $task = $this->createPipelineTask(
            $request,
            'pipeline_single_action',
            ['steps' => [[
                'action_name' => $action,
                'sequence_order' => 1,
                'input_json' => $input,
            ]]],
            array_merge((array) ($validated['meta'] ?? []), ['requested_action' => $action]),
        );

        return response()->json([
            'status' => $task->status,
            'task_public_id' => $task->public_id,
            'dispatch_id' => $task->public_id,
            'links' => $this->taskLinks($task),
            'action' => $action,
        ], 202);
    }

    /**
     * Return the current state of a task owned by the authenticated user.
     */
    public function show(Request $request, string $taskPublicId): JsonResponse
    {
        $task = $this->findUserTaskOrFail($request, $taskPublicId);

        return response()->json([
            'data' => [
                'public_id' => $task->public_id,
                'type' => $task->type,
                'status' => $task->status,
                'priority' => $task->priority,
                'input' => $task->input_json,
                'output' => $task->output_json,
                'meta' => $task->meta_json,
                'error_message' => $task->error_message,
                'created_at' => optional($task->created_at)?->toIso8601String(),
                'updated_at' => optional($task->updated_at)?->toIso8601String(),
                'started_at' => optional($task->started_at)?->toIso8601String(),
                'finished_at' => optional($task->finished_at)?->toIso8601String(),
                'steps' => $task->steps->map(fn (TaskStep $step): array => [
                    'action_name' => $step->action_name,
                    'sequence_order' => $step->sequence_order,
                    'status' => $step->status,
                    'input' => $step->input_json,
                    'output' => $step->output_json,
                    'error_message' => $step->error_message,
                    'started_at' => optional($step->started_at)?->toIso8601String(),
                    'finished_at' => optional($step->finished_at)?->toIso8601String(),
                ])->all(),
            ],
        ]);
    }

    /**
     * Return task logs for a task owned by the authenticated user.
     */
    public function logs(Request $request, string $taskPublicId): JsonResponse
    {
        $task = $this->findUserTaskOrFail($request, $taskPublicId);
        $logs = $task->logs()->orderBy('created_at')->get();

        return response()->json([
            'data' => $logs->map(fn (RunLog $log): array => [
                'id' => $log->id,
                'level' => $log->level,
                'event_type' => $log->event_type,
                'message' => $log->message,
                'context' => $log->context_json,
                'created_at' => optional($log->created_at)?->toIso8601String(),
            ])->all(),
        ]);
    }

    protected function findUserTaskOrFail(Request $request, string $taskPublicId): Task
    {
        $task = Task::query()
            ->where('public_id', $taskPublicId)
            ->where('user_id', $request->user()?->id)
            ->first();

        if ($task === null) {
            throw TaskException::notFound();
        }

        return $task;
    }

    /**
     * Persist a task that should go through PlanTaskStepsJob.
     *
     * @param  array<string, mixed>  $taskInput
     * @param  array<string, mixed>|null  $taskMeta
     */
    protected function createPipelineTask(Request $request, string $type, array $taskInput, ?array $taskMeta = null): Task
    {
        try {
            $task = Task::query()->create([
                'public_id' => (string) Str::uuid(),
                'user_id' => $request->user()?->id,
                'type' => $type,
                'status' => TaskStatus::PENDING_PLANNING,
                'input_json' => $taskInput,
                'meta_json' => $taskMeta,
            ]);

            RunLog::query()->create([
                'task_id' => $task->id,
                'level' => 'info',
                'event_type' => 'task.accepted',
                'message' => 'Task request accepted and persisted',
                'context_json' => [
                    'task_public_id' => $task->public_id,
                    'user_id' => $request->user()?->id,
                    'type' => $type,
                ],
            ]);

            PlanTaskStepsJob::dispatch($task->id)->onQueue(QueueEnum::SERVICE);

            return $task;
        } catch (\Throwable $e) {
            throw TaskException::dispatchFailed($e);
        }
    }

    /**
     * Provide canonical polling URLs so clients do not depend on queue internals.
     *
     * @return array{status: string, logs: string}
     */
    protected function taskLinks(Task $task): array
    {
        return [
            'status' => route('api.v1.tasks.show', ['taskPublicId' => $task->public_id]),
            'logs' => route('api.v1.tasks.logs', ['taskPublicId' => $task->public_id]),
        ];
    }
}
