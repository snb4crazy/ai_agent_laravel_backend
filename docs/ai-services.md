# AI Services (OpenAI + Azure OpenAI)

This project now uses one app-level contract:

- `App\Services\AI\Contracts\AIServiceInterface`

And two implementations:

- `App\Services\AI\AzureOpenAIService`
- `App\Services\AI\OpenAIService`

Additional future-ready stubs:

- `App\Services\AI\OllamaServiceStub` (local LLM placeholder)
- `App\Services\AI\AnthropicServiceStub` (external provider placeholder)

The active adapter is selected by:

- `AI_PROVIDER=azure` -> `AzureOpenAIService`
- `AI_PROVIDER=openai` -> `OpenAIService`
- `AI_PROVIDER=ollama` -> `OllamaServiceStub`
- `AI_PROVIDER=anthropic` -> `AnthropicServiceStub`

Binding is configured in `app/Providers/AppServiceProvider.php`.

## Why 2 services?

Recommended approach is **one interface + two adapters**:

- keeps app code provider-agnostic
- allows switching provider with config only
- keeps Azure-specific endpoint logic isolated from standard OpenAI API calls

If you currently only use Azure, keep `AI_PROVIDER=azure` and ignore OpenAI keys.
Stub providers return a deterministic `stub=true` payload and do not call real APIs.

## Environment variables

```dotenv
AI_PROVIDER=azure

OPENAI_API_KEY=
OPENAI_CHAT_MODEL=gpt-4.1-mini
OPENAI_EMBEDDINGS_MODEL=text-embedding-3-small

AZURE_OPENAI_ENDPOINT=https://your-resource.openai.azure.com
AZURE_OPENAI_API_KEY=
AZURE_OPENAI_API_VERSION=2024-05-01-preview
AZURE_OPENAI_CHAT_DEPLOYMENT=gpt-4o-mini
AZURE_OPENAI_EMBEDDINGS_DEPLOYMENT=text-embedding-3-small
```

## Quick test command

Use this command to test the configured provider:

```bash
php artisan ai:chat-example "Hello from AI Control Plane"
```

Optional model/deployment override:

```bash
php artisan ai:chat-example "Hello" --model=gpt-4o-mini
```

## Azure batch request mapping

Your previous request style:

```php
POST /openai/deployments/{model}/batch?api-version=2024-05-01-preview
```

is now wrapped in:

- `AzureOpenAIService::batch($model, $inputFileId, $outputDirectoryId, $completionWindow = '24h', $options = [])`

