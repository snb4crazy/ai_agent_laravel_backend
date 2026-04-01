<?php

namespace App\Actions;

use App\Actions\Contracts\ActionInterface;
use App\Models\RunLog;
use App\Services\AI\AIServiceResolver;

class AskAiAction implements ActionInterface
{
    public function __construct(
        private readonly AIServiceResolver $resolver,
    ) {}

    public function name(): string
    {
        return 'ask_ai';
    }

    public function handle(array $input): array
    {
        $prompt = trim((string) ($input['prompt'] ?? ''));
        $messages = $this->resolveMessages($input, $prompt);

        if ($messages === []) {
            return [
                'status' => 'failed',
                'error' => 'Provide prompt or messages for ask_ai action.',
            ];
        }

        $providerInput = isset($input['provider']) ? (string) $input['provider'] : null;
        $model = isset($input['model']) ? (string) $input['model'] : null;
        $options = (array) ($input['options'] ?? []);

        $resolved = $this->resolver->resolve($providerInput);
        $provider = $resolved['provider'];
        $service = $resolved['service'];

        $response = $service->chat($messages, $model, $options);
        $answerText = $this->extractAnswerText($response);

        $taskId = isset($input['task_id']) ? (int) $input['task_id'] : null;

        if (is_int($taskId) && $taskId > 0) {
            RunLog::query()->create([
                'task_id' => $taskId,
                'level' => 'info',
                'event_type' => 'task.ai_response_received',
                'message' => 'AI response received from provider '.$provider,
                'context_json' => [
                    'task_public_id' => (string) ($input['task_public_id'] ?? ''),
                    'provider' => $provider,
                    'model' => $model,
                    'prompt_excerpt' => mb_substr($prompt, 0, 200),
                    'response_excerpt' => mb_substr($answerText, 0, 500),
                ],
            ]);
        }

        return [
            'status' => 'ok',
            'provider' => $provider,
            'model' => $model,
            'text' => $answerText,
            'raw' => $response,
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<int, array<string, mixed>>
     */
    private function resolveMessages(array $input, string $prompt): array
    {
        $rawMessages = $input['messages'] ?? null;

        if (is_array($rawMessages) && $rawMessages !== []) {
            return array_values(array_filter($rawMessages, static fn ($item) => is_array($item)));
        }

        if ($prompt === '') {
            return [];
        }

        $systemPrompt = (string) ($input['system_prompt'] ?? 'You are a helpful assistant.');

        return [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $prompt],
        ];
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function extractAnswerText(array $response): string
    {
        $content = data_get($response, 'choices.0.message.content');

        if (is_string($content)) {
            return trim($content);
        }

        if (is_array($content)) {
            $parts = [];

            foreach ($content as $chunk) {
                if (is_array($chunk) && is_string($chunk['text'] ?? null)) {
                    $parts[] = $chunk['text'];
                }
            }

            return trim(implode("\n", $parts));
        }

        return '';
    }
}
