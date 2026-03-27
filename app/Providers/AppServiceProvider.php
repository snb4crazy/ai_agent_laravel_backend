<?php

namespace App\Providers;

use App\Services\AI\AnthropicServiceStub;
use App\Services\AI\AzureOpenAIService;
use App\Services\AI\Contracts\AIServiceInterface;
use App\Services\AI\OllamaServiceStub;
use App\Services\AI\OpenAIService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(AIServiceInterface::class, function (): AIServiceInterface {
            return match (config('services.ai.provider', 'azure')) {
                'openai' => $this->app->make(OpenAIService::class),
                'ollama' => $this->app->make(OllamaServiceStub::class),
                'anthropic' => $this->app->make(AnthropicServiceStub::class),
                default => $this->app->make(AzureOpenAIService::class),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
            return config('app.frontend_url')."/password-reset/$token?email={$notifiable->getEmailForPasswordReset()}";
        });
    }
}
