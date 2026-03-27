<?php

namespace App\Services\AI;

use App\Services\AI\Contracts\AIServiceInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class AzureOpenAIService implements AIServiceInterface
{
    public function chat(array $messages, ?string $model = null, array $options = []): array
    {
        $deployment = $model ?: (string) config('services.azure_openai.chat_deployment');

        $payload = array_merge($options, [
            'messages' => $messages,
        ]);

        return $this->request()->post($this->deploymentPath($deployment, 'chat/completions'), $payload)->throw()->json();
    }

    public function embeddings(string|array $input, ?string $model = null, array $options = []): array
    {
        $deployment = $model ?: (string) config('services.azure_openai.embeddings_deployment');

        $payload = array_merge($options, [
            'input' => $input,
        ]);

        return $this->request()->post($this->deploymentPath($deployment, 'embeddings'), $payload)->throw()->json();
    }

    public function batch(string $model, string $inputFileId, string $outputDirectoryId, string $completionWindow = '24h', array $options = []): array
    {
        $payload = array_merge($options, [
            'input_file_id' => $inputFileId,
            'output_directory_id' => $outputDirectoryId,
            'model' => $model,
            'completion_window' => $completionWindow,
        ]);

        return $this->request()->post($this->deploymentPath($model, 'batch'), $payload)->throw()->json();
    }

    protected function request(): PendingRequest
    {
        $endpoint = rtrim((string) config('services.azure_openai.endpoint'), '/');
        $apiVersion = (string) config('services.azure_openai.api_version');

        return Http::baseUrl($endpoint)
            ->acceptJson()
            ->asJson()
            ->withHeaders([
                'api-key' => (string) config('services.azure_openai.api_key'),
            ])
            ->withQueryParameters([
                'api-version' => $apiVersion,
            ]);
    }

    protected function deploymentPath(string $model, string $operation): string
    {
        return "/openai/deployments/{$model}/{$operation}";
    }
}
