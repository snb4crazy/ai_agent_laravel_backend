# AI Provider Setup Guide (.env Keys + Validation)

Date: 2026-04-05

This guide explains how to:

1. Obtain provider credentials,
2. Put them into `.env`,
3. Apply Laravel config changes,
4. Verify provider routing is working.

Use this together with:

- `docs/ai-services.md`
- `docs/actions-contract.md`
- `docs/operations-runbook.md`

## 1) How provider routing works in this project

There are two routing layers:

- Global default provider: `AI_PROVIDER` in `.env`
- Per-request override (for `ask_ai` action): `input.provider`

Selection order for `ask_ai`:

1. `input.provider`
2. `AI_PROVIDER`

Unknown provider names fallback to `AI_PROVIDER` default.

## 2) Supported provider values

Set `AI_PROVIDER` to one of:

- `azure`
- `openai`
- `ollama`
- `anthropic`

## 3) Copy and prepare `.env`

If not done yet:

```bash
cp .env.example .env
php artisan key:generate
```

## 4) Obtain credentials and fill `.env`

## Azure OpenAI

### Where to obtain keys

1. In Azure Portal, open your Azure OpenAI resource.
2. Open **Keys and Endpoint** and copy:
   - API Key
   - Endpoint URL
3. Open Azure AI Studio / model deployment view and copy deployment names:
   - chat deployment name
   - embeddings deployment name
4. Confirm your API version (or use project default).

### `.env` values

```dotenv
AI_PROVIDER=azure

AZURE_OPENAI_ENDPOINT=https://YOUR_RESOURCE_NAME.openai.azure.com
AZURE_OPENAI_API_KEY=YOUR_AZURE_KEY
AZURE_OPENAI_API_VERSION=2024-05-01-preview
AZURE_OPENAI_CHAT_DEPLOYMENT=gpt-4o-mini
AZURE_OPENAI_EMBEDDINGS_DEPLOYMENT=text-embedding-3-small
```

### Common mistakes

- Endpoint missing `https://`
- Using model name where deployment name is required
- Wrong API version for your deployment
- Key copied from wrong resource/region

## OpenAI

### Where to obtain keys

1. Sign in to OpenAI platform.
2. Open API keys page and create a new secret key.
3. Copy the key once (it may not be shown again).
4. Choose your chat and embedding models.

### `.env` values

```dotenv
AI_PROVIDER=openai

OPENAI_API_KEY=YOUR_OPENAI_KEY
OPENAI_CHAT_MODEL=gpt-4.1-mini
OPENAI_EMBEDDINGS_MODEL=text-embedding-3-small
```

### Common mistakes

- Key has expired/revoked
- Model name unavailable in your account tier
- Hidden whitespace/newline in key value

## Anthropic (current project status)

This repo currently routes `anthropic` to `AnthropicServiceStub`.
That means routing can be tested, but it does not execute a real Anthropic API call yet.

### Where to obtain keys

1. Sign in to Anthropic Console.
2. Create an API key.

### `.env` values

```dotenv
AI_PROVIDER=anthropic

ANTHROPIC_API_KEY=YOUR_ANTHROPIC_KEY
ANTHROPIC_MODEL=claude-3-5-sonnet-latest
ANTHROPIC_EMBEDDINGS_MODEL=not-supported-yet
```

## Ollama local provider (current project status)

This repo currently routes `ollama` to `OllamaServiceStub`.
Routing can be tested now; real Ollama HTTP integration can be added later.

If you still want local Ollama installed now:

1. Install Ollama from official docs for your OS.
2. Start Ollama service.
3. Pull a model.

Example local commands:

```bash
ollama pull llama3.1
ollama list
```

### `.env` values

```dotenv
AI_PROVIDER=ollama

OLLAMA_ENDPOINT=http://localhost:11434
OLLAMA_MODEL=llama3.1
OLLAMA_EMBEDDINGS_MODEL=nomic-embed-text
```

## 5) Apply configuration changes

After editing `.env`, clear cached config:

```bash
php artisan config:clear
php artisan cache:clear
```

If you use cached config in your environment:

```bash
php artisan config:cache
```

## 6) Validate provider wiring quickly

## A) Validate global provider

```bash
php artisan ai:chat-example "Hello from provider setup"
```

Expected:

- command prints current provider from `services.ai.provider`
- response text is returned (real provider or deterministic stub, depending on provider)

Optional model override:

```bash
php artisan ai:chat-example "Hello" --model=gpt-4o-mini
```

## B) Validate action-level provider override

Run `ask_ai` through pipeline endpoint with explicit provider:

```bash
curl -X POST "http://localhost:8000/api/v1/tasks/run-action" \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "ask_ai",
    "input": {
      "prompt": "Give one sentence about Laravel queues",
      "provider": "openai",
      "model": "gpt-4.1-mini"
    }
  }'
```

Then check task logs:

```bash
curl -X GET "http://localhost:8000/api/v1/tasks/TASK_PUBLIC_ID/logs" \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN"
```

Look for `task.ai_response_received` with metadata:

- `provider`
- `model`
- `prompt_excerpt`
- `response_excerpt`

## 7) Example `.env` templates

## Azure-only template

```dotenv
AI_PROVIDER=azure
AZURE_OPENAI_ENDPOINT=https://YOUR_RESOURCE_NAME.openai.azure.com
AZURE_OPENAI_API_KEY=YOUR_AZURE_KEY
AZURE_OPENAI_API_VERSION=2024-05-01-preview
AZURE_OPENAI_CHAT_DEPLOYMENT=gpt-4o-mini
AZURE_OPENAI_EMBEDDINGS_DEPLOYMENT=text-embedding-3-small
```

## OpenAI-only template

```dotenv
AI_PROVIDER=openai
OPENAI_API_KEY=YOUR_OPENAI_KEY
OPENAI_CHAT_MODEL=gpt-4.1-mini
OPENAI_EMBEDDINGS_MODEL=text-embedding-3-small
```

## Hybrid template (default Azure + per-request OpenAI override)

```dotenv
AI_PROVIDER=azure

AZURE_OPENAI_ENDPOINT=https://YOUR_RESOURCE_NAME.openai.azure.com
AZURE_OPENAI_API_KEY=YOUR_AZURE_KEY
AZURE_OPENAI_API_VERSION=2024-05-01-preview
AZURE_OPENAI_CHAT_DEPLOYMENT=gpt-4o-mini
AZURE_OPENAI_EMBEDDINGS_DEPLOYMENT=text-embedding-3-small

OPENAI_API_KEY=YOUR_OPENAI_KEY
OPENAI_CHAT_MODEL=gpt-4.1-mini
OPENAI_EMBEDDINGS_MODEL=text-embedding-3-small
```

## 8) Security best practices

- Never commit real keys to git.
- Keep secrets only in `.env` or secret manager.
- Rotate keys if exposed.
- Use separate keys per environment (local/stage/prod).
- Limit key permissions in provider consoles if possible.

## 9) Troubleshooting

## Symptom: `ask_ai` returns fallback provider unexpectedly

Checks:

- `input.provider` spelling
- `AI_PROVIDER` value in `.env`
- run `php artisan config:clear`

## Symptom: Azure request fails with auth/deployment errors

Checks:

- endpoint format
- key validity
- deployment names exist
- API version compatibility

## Symptom: OpenAI request fails with model/access error

Checks:

- key validity
- model availability for your account
- remove hidden spaces/newlines from `.env` key

## Symptom: no AI logs for task

Checks:

- ensure action is `ask_ai`
- ensure task includes `task_id` context (happens automatically in pipeline job)
- check worker is running (`service` + `task` queues)
- check `storage/logs/laravel.log`

## 10) Related commands

```bash
php artisan ai:chat-example "Hello"
php artisan queue:work redis --queue=service
php artisan queue:work redis --queue=task
php artisan queue:restart
```

