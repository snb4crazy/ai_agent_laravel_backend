# Actions Contract (PoC)

Date: 2026-04-05

This document defines the current action input/output contract used by the multi-step pipeline.

## Purpose

- Keep action behavior predictable for API and frontend integration.
- Document exactly what each action expects and returns today.
- Make Deliverable 3 check-off explicit.

## Where actions are configured and executed

- Registry: `config/actions.php`
- Resolver/executor: `app/Services/TaskActionService.php`
- Runtime enrichment + orchestration: `app/Jobs/ExecuteTaskStepJob.php`
- Final aggregation: `app/Jobs/CompileTaskOutputJob.php`

## How action execution works

1. A task step contains `action_name` and `input_json`.
2. `ExecuteTaskStepJob` builds effective input.
3. `TaskActionService` resolves `action_name` from `config/actions.php`.
4. Action class `handle(array $input): array` runs synchronously.
5. Returned array is stored in `task_steps.output_json`.
6. When all steps finish, `CompileTaskOutputJob` compiles final `tasks.output_json`.

## Common step input context fields

`ExecuteTaskStepJob` injects these fields into each action input:

- `task_id` (int)
- `task_step_id` (int)
- `task_public_id` (string UUID)
- `previous_output` (array, optional; output of previous completed step)

This means actions can be chained without direct DB reads.

## Action catalog and contracts

## `scrape_url`

Class: `app/Actions/ScrapeUrlAction.php`

Input (example):

```json
{
  "url": "https://example.com"
}
```

Success output (example):

```json
{
  "url": "https://example.com",
  "title": "Example Domain",
  "content": "Example Domain This domain is for use in illustrative examples...",
  "content_length": 198,
  "http_status": 200,
  "status": "ok"
}
```

Failure output (example):

```json
{
  "url": "http://localhost:8080",
  "status": "failed",
  "error": "Only public http/https URLs are allowed."
}
```

Notes:

- Allows only public `http/https` URLs.
- Blocks localhost/private/reserved IP targets.
- Truncates returned `content` to 5000 chars.

## `summarize_text`

Class: `app/Actions/SummarizeTextAction.php`

Input (example):

```json
{
  "text": "Long text to summarize..."
}
```

Alternative chained input:

```json
{
  "previous_output": {
    "content": "Scraped page text..."
  }
}
```

Success output (example):

```json
{
  "summary": "Sentence one. Sentence two.",
  "keywords": ["agent", "pipeline", "status"],
  "original_length": 923,
  "status": "ok"
}
```

Failure output (example):

```json
{
  "summary": "",
  "keywords": [],
  "status": "failed",
  "error": "No text provided for summarization."
}
```

## `classify_intent`

Class: `app/Actions/ClassifyIntentAction.php`

Input (example):

```json
{
  "text": "I was charged twice on my invoice"
}
```

Output (example):

```json
{
  "intent": "billing_question",
  "matched_keyword": "invoice",
  "confidence": 0.85,
  "status": "ok"
}
```

Fallback output (example):

```json
{
  "intent": "general_question",
  "matched_keyword": null,
  "confidence": 0.5,
  "status": "ok"
}
```

## `analyze_sentiment`

Class: `app/Actions/AnalyzeSentimentAction.php`

Input (example):

```json
{
  "text": "Great support, but I still have one issue"
}
```

Output (example):

```json
{
  "label": "positive",
  "score": 0.333,
  "positive_hits": 2,
  "negative_hits": 1,
  "status": "ok"
}
```

## `generate_reply`

Class: `app/Actions/GenerateReplyAction.php`

Input (example):

```json
{
  "text": "I need help with billing",
  "intent": "billing_question",
  "sentiment": "neutral"
}
```

Chained usage (intent/sentiment from previous step):

```json
{
  "text": "I need help with billing",
  "previous_output": {
    "intent": "billing_question",
    "label": "negative"
  }
}
```

Output (example):

```json
{
  "reply": "I am sorry you are facing this. Please share your invoice number so we can verify billing details.",
  "intent_used": "billing_question",
  "sentiment_used": "negative",
  "source_excerpt": "I need help with billing",
  "status": "ok"
}
```

## `ask_ai`

Class: `app/Actions/AskAiAction.php`

Input by prompt (example):

```json
{
  "provider": "openai",
  "model": "gpt-4o-mini",
  "prompt": "Summarize this in one sentence",
  "system_prompt": "You are a helpful assistant.",
  "options": {
    "temperature": 0.2
  }
}
```

Input by messages (example):

```json
{
  "provider": "azure",
  "messages": [
    {"role": "system", "content": "You are concise."},
    {"role": "user", "content": "Hello"}
  ]
}
```

Success output (example):

```json
{
  "status": "ok",
  "provider": "openai",
  "model": "gpt-4o-mini",
  "text": "Short summary response...",
  "raw": {
    "choices": [
      {
        "message": {
          "content": "Short summary response..."
        }
      }
    ]
  }
}
```

Failure output (example):

```json
{
  "status": "failed",
  "error": "Provide prompt or messages for ask_ai action."
}
```

Notes:

- Uses `AIServiceResolver` to resolve provider (input override or default config).
- Writes a `run_logs` record `task.ai_response_received` when `task_id` is present in action input.

## `save_result`

Class: `app/Actions/SaveResultAction.php`

Input (example):

```json
{
  "data": {
    "any": "payload"
  }
}
```

Output (example):

```json
{
  "saved": true,
  "reference": "action-6a6f3f6f-25e2-4cd5-8f6a-24d0f6c7a7d4",
  "storage_disk": "local",
  "path": "action-results/2026/04/05/action-6a6f3f6f-25e2-4cd5-8f6a-24d0f6c7a7d4.json",
  "status": "ok"
}
```

## Fallback behavior

If a step references unknown `action_name`:

- `TaskActionService` returns `executed=false`.
- `ExecuteTaskStepJob` marks step as completed with:

```json
{
  "status": "no_action_found"
}
```

Use request validation and controlled pipeline definitions to avoid this case in normal API usage.

## Where to read outputs

- Step-level output: `GET /api/v1/tasks/{taskPublicId}` -> `data.steps[*].output`
- Final compiled output: `GET /api/v1/tasks/{taskPublicId}` -> `data.output`
- Execution logs: `GET /api/v1/tasks/{taskPublicId}/logs`

## API request examples for action endpoints

Run named pipeline:

```json
POST /api/v1/tasks/run-pipeline
{
  "pipeline": "all_actions",
  "input": {"prompt": "Hello"},
  "input_by_action": {
    "scrape_url": {"url": "https://example.com"},
    "ask_ai": {"provider": "openai", "model": "gpt-4o-mini"}
  },
  "skip_actions": ["save_result"]
}
```

Run one action:

```json
POST /api/v1/tasks/run-action
{
  "action": "analyze_sentiment",
  "input": {"text": "This is great"}
}
```

Both endpoints return `task_public_id` and polling links (`links.status`, `links.logs`).

