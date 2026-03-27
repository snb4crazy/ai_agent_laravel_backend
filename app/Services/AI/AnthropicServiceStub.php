<?php

namespace App\Services\AI;

use App\Services\AI\Contracts\AIServiceInterface;

class AnthropicServiceStub implements AIServiceInterface
{
    public function chat(array $messages, ?string $model = null, array $options = []): array
    {
        return $this->stubResponse('chat', [
            'model' => $model ?: (string) config('services.anthropic.model', 'claude-3-5-sonnet-latest'),
            'messages' => $messages,
            'options' => $options,
        ]);
    }

    public function embeddings(string|array $input, ?string $model = null, array $options = []): array
    {
        return $this->stubResponse('embeddings', [
            'model' => $model ?: (string) config('services.anthropic.embeddings_model', 'not-supported-yet'),
            'input' => $input,
            'options' => $options,
        ]);
    }

    public function batch(string $model, string $inputFileId, string $outputDirectoryId, string $completionWindow = '24h', array $options = []): array
    {
        return $this->stubResponse('batch', [
            'model' => $model,
            'input_file_id' => $inputFileId,
            'output_directory_id' => $outputDirectoryId,
            'completion_window' => $completionWindow,
            'options' => $options,
        ]);
    }

    /**
     * Returns a predictable payload so integrations can be wired before provider rollout.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function stubResponse(string $operation, array $payload): array
    {
        return [
            'provider' => 'anthropic',
            'stub' => true,
            'status' => 'not_configured',
            'operation' => $operation,
            'payload' => $payload,
            'message' => 'AnthropicServiceStub is active. Replace with a real adapter when provider is enabled.',
        ];
    }
}
