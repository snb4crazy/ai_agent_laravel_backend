<?php

namespace App\Actions\Contracts;

interface ActionInterface
{
    /**
     * Machine-readable action identifier (matches config/actions.php key).
     */
    public function name(): string;

    /**
     * Execute the action with the given input and return the result.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function handle(array $input): array;
}
