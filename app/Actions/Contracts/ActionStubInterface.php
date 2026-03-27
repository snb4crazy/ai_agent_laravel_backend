<?php

namespace App\Actions\Contracts;

interface ActionStubInterface
{
    public function name(): string;

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function handle(array $input): array;
}
