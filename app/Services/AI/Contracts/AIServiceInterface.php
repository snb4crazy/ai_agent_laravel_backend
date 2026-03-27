<?php

namespace App\Services\AI\Contracts;

interface AIServiceInterface
{
    /**
     * Send a chat completion request.
     *
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function chat(array $messages, ?string $model = null, array $options = []): array;

    /**
     * Send an embeddings request.
     *
     * @param  string|array<int, string>  $input
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function embeddings(string|array $input, ?string $model = null, array $options = []): array;

    /**
     * Send a batch request.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function batch(string $model, string $inputFileId, string $outputDirectoryId, string $completionWindow = '24h', array $options = []): array;
}
