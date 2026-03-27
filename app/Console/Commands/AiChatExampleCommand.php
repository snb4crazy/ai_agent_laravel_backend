<?php

namespace App\Console\Commands;

use App\Services\AI\Contracts\AIServiceInterface;
use Illuminate\Console\Command;

class AiChatExampleCommand extends Command
{
    protected $signature = 'ai:chat-example {prompt=Say hello from AI Control Plane} {--model=}';

    protected $description = 'Send a simple chat request through the configured AI service adapter.';

    public function handle(AIServiceInterface $aiService): int
    {
        $prompt = (string) $this->argument('prompt');
        $model = $this->option('model');

        $this->info('Provider: '.config('services.ai.provider'));

        $response = $aiService->chat([
            ['role' => 'user', 'content' => $prompt],
        ], $model ?: null);

        $text = data_get($response, 'choices.0.message.content')
            ?? data_get($response, 'choices.0.text')
            ?? '[No text content found in response]';

        $this->line('---');
        $this->line((string) $text);

        return self::SUCCESS;
    }
}
