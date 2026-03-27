<?php

namespace App\Services\AI;

use App\Services\AI\Contracts\AIServiceInterface;
use Illuminate\Support\Facades\Http;
use OpenAI\Laravel\Facades\OpenAI;

class OpenAIService implements AIServiceInterface
{
    public function chat(array $messages, ?string $model = null, array $options = []): array
    {
        $response = OpenAI::chat()->create(array_merge($options, [
            'model' => $model ?: (string) config('services.openai.chat_model'),
            'messages' => $messages,
        ]));

        return $response->toArray();
    }

    public function embeddings(string|array $input, ?string $model = null, array $options = []): array
    {
        $response = OpenAI::embeddings()->create(array_merge($options, [
            'model' => $model ?: (string) config('services.openai.embeddings_model'),
            'input' => $input,
        ]));

        return $response->toArray();
    }

    public function batch(string $model, string $inputFileId, string $outputDirectoryId, string $completionWindow = '24h', array $options = []): array
    {
        $payload = array_merge($options, [
            'input_file_id' => $inputFileId,
            'output_directory_id' => $outputDirectoryId,
            'model' => $model,
            'completion_window' => $completionWindow,
        ]);

        return Http::baseUrl('https://api.openai.com/v1')
            ->acceptJson()
            ->asJson()
            ->withToken((string) config('services.openai.api_key'))
            ->post('/batches', $payload)
            ->throw()
            ->json();
    }
}
