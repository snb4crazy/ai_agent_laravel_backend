## Azure OpenAI integration with Laravel 

Azure OpenAI integration with Laravel is still pretty straightforward—you’re basically making an HTTP call to a REST endpoint using your key. The main differences vs “regular” OpenAI are: custom endpoint URL, deployment name instead of model, and API version.

Here’s a clean refresher.

### 🔧 1. What you need (you already have it)
Endpoint (e.g. https://your-resource.openai.azure.com)
Deployment name (⚠️ not model name like gpt-4, but your custom deployment)
API key
API version (e.g. 2024-02-15-preview or newer)

In .env:

AZURE_OPENAI_ENDPOINT=https://your-resource.openai.azure.com
AZURE_OPENAI_KEY=your_key_here
AZURE_OPENAI_DEPLOYMENT=gpt-4o   # or whatever you named it
AZURE_OPENAI_API_VERSION=2024-02-15-preview

```php
use Illuminate\Support\Facades\Http;

$response = Http::withHeaders([
    'api-key' => config('services.azure_openai.key'),
    'Content-Type' => 'application/json',
])->post(
    config('services.azure_openai.endpoint') .
    '/openai/deployments/' . config('services.azure_openai.deployment') . '/chat/completions?api-version=' . config('services.azure_openai.api_version'),
    [
        'messages' => [
            ['role' => 'system', 'content' => 'You are a helpful assistant.'],
            ['role' => 'user', 'content' => 'Write a short Laravel tip.'],
        ],
        'max_tokens' => 200,
        'temperature' => 0.7,
    ]
);

$data = $response->json();

$text = $data['choices'][0]['message']['content'] ?? null;

```
config/services.php:
```php
'azure_openai' => [
    'endpoint' => env('AZURE_OPENAI_ENDPOINT'),
    'key' => env('AZURE_OPENAI_KEY'),
    'deployment' => env('AZURE_OPENAI_DEPLOYMENT'),
    'api_version' => env('AZURE_OPENAI_API_VERSION'),
],

```

Quick test route in Laravel
```php
Route::get('/test-ai', function () {
    $response = Http::withHeaders([
        'api-key' => config('services.azure_openai.key'),
    ])->post(
        config('services.azure_openai.endpoint') .
        '/openai/deployments/' . config('services.azure_openai.deployment') . '/chat/completions?api-version=' . config('services.azure_openai.api_version'),
        [
            'messages' => [
                ['role' => 'user', 'content' => 'Say hello from Azure AI'],
            ],
        ]
    );

    return $response->json();
});
```

Optional improvements (worth doing)
```php
Http::timeout(10)->retry(3, 100);
```
