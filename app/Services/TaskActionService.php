<?php

namespace App\Services;

use App\Actions\Contracts\ActionStubInterface;

class TaskActionService
{
    /**
     * @param  array<string, mixed>  $input
     * @return array{executed: bool, action: string, result: array<string, mixed>|null}
     */
    public function execute(string $action, array $input): array
    {
        $map = (array) config('actions.stubs', []);
        $class = $map[$action] ?? null;

        if (! is_string($class) || ! class_exists($class)) {
            return [
                'executed' => false,
                'action' => $action,
                'result' => null,
            ];
        }

        /** @var ActionStubInterface $stub */
        $stub = app($class);

        return [
            'executed' => true,
            'action' => $action,
            'result' => $stub->handle($input),
        ];
    }
}
