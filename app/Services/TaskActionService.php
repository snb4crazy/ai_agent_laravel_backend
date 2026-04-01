<?php

namespace App\Services;

use App\Actions\Contracts\ActionInterface;

class TaskActionService
{
    /**
     * Execute a named action and return its result.
     *
     * Actions are simple atomic PHP classes (App\Actions\*) that implement
     * ActionInterface. They are called synchronously from within
     * ExecuteTaskStepJob — the queue boundary is the job, not the action.
     *
     * @param  array<string, mixed>  $input
     * @return array{executed: bool, action: string, result: array<string, mixed>|null}
     */
    public function execute(string $action, array $input): array
    {
        $map = (array) config('actions.actions', []);
        $class = $map[$action] ?? null;

        if (! is_string($class) || ! class_exists($class)) {
            return [
                'executed' => false,
                'action' => $action,
                'result' => null,
            ];
        }

        /** @var ActionInterface $instance */
        $instance = app($class);

        return [
            'executed' => true,
            'action' => $action,
            'result' => $instance->handle($input),
        ];
    }
}
