<?php

namespace App\Services;

use App\Models\Task;

/**
 * Resolves which action steps a task should be broken into.
 *
 * The stub implementation looks for an explicit `steps` key inside
 * `task.input_json`, then falls back to a default plan keyed by task type.
 * Replace the stub body with a real AI call when the planning prompt is ready.
 */
class TaskPlannerService
{
    /**
     * Return an ordered list of step definitions for the given task.
     *
     * Each entry has:
     *   - action_name  (string)  – matches a key in config/actions.php
     *   - sequence_order (int)   – 1-based, controls execution order
     *   - input_json   (array)   – forwarded to the action's handle() method
     *
     * @param  Task  $task
     * @return array<int, array{action_name: string, sequence_order: int, input_json: array<string, mixed>}>
     */
    public function plan(Task $task): array
    {
        $input = (array) ($task->input_json ?? []);

        // If the caller already knows the steps, honour them directly.
        if (! empty($input['steps']) && is_array($input['steps'])) {
            return $this->normalise($input['steps'], $input);
        }

        return $this->defaultPlan($task->type, $input);
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    /**
     * Build a default multi-step plan based on task type.
     *
     * @param  array<string, mixed>  $input
     * @return array<int, array{action_name: string, sequence_order: int, input_json: array<string, mixed>}>
     */
    private function defaultPlan(string $type, array $input): array
    {
        return match ($type) {
            'multi_step_task' => [
                [
                    'action_name'    => 'analyze_sentiment',
                    'sequence_order' => 1,
                    'input_json'     => ['text' => $input['prompt'] ?? ''],
                ],
                [
                    'action_name'    => 'generate_reply',
                    'sequence_order' => 2,
                    'input_json'     => ['text' => $input['prompt'] ?? ''],
                ],
                [
                    'action_name'    => 'save_result',
                    'sequence_order' => 3,
                    'input_json'     => ['prompt' => $input['prompt'] ?? ''],
                ],
            ],
            'scrape_and_summarize' => [
                [
                    'action_name'    => 'scrape_url',
                    'sequence_order' => 1,
                    'input_json'     => ['url' => $input['url'] ?? ''],
                ],
                [
                    'action_name'    => 'summarize_text',
                    'sequence_order' => 2,
                    'input_json'     => ['text' => ''],   // populated from previous step at runtime
                ],
            ],
            'classify_and_reply' => [
                [
                    'action_name'    => 'classify_intent',
                    'sequence_order' => 1,
                    'input_json'     => ['text' => $input['prompt'] ?? ''],
                ],
                [
                    'action_name'    => 'generate_reply',
                    'sequence_order' => 2,
                    'input_json'     => ['text' => $input['prompt'] ?? ''],
                ],
            ],
            // Unknown type: single no-op step so the task can still transition.
            default => [
                [
                    'action_name'    => 'save_result',
                    'sequence_order' => 1,
                    'input_json'     => $input,
                ],
            ],
        };
    }

    /**
     * Normalise a caller-supplied steps array, ensuring each entry has the
     * required keys and that sequence_order values are contiguous.
     *
     * @param  array<int, mixed>     $steps
     * @param  array<string, mixed>  $taskInput
     * @return array<int, array{action_name: string, sequence_order: int, input_json: array<string, mixed>}>
     */
    private function normalise(array $steps, array $taskInput): array
    {
        $normalised = [];

        foreach (array_values($steps) as $i => $step) {
            $step = is_array($step) ? $step : [];

            $normalised[] = [
                'action_name'    => (string) ($step['action_name'] ?? 'save_result'),
                'sequence_order' => (int) ($step['sequence_order'] ?? ($i + 1)),
                'input_json'     => (array) ($step['input_json'] ?? $taskInput),
            ];
        }

        // Sort by sequence_order so callers don't have to.
        usort($normalised, static fn ($a, $b) => $a['sequence_order'] <=> $b['sequence_order']);

        return $normalised;
    }
}

