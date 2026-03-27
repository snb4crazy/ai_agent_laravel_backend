# Action Stubs (Predefined Actions)

This document describes predefined action stubs and how they are used in the queue flow.

## Available actions

Configured in `config/actions.php`:

- `scrape_url`
- `analyze_sentiment`
- `generate_reply`
- `save_result`
- `summarize_text`
- `classify_intent`

All stubs implement `App\Actions\Contracts\ActionStubInterface`.

## Where actions run

Queue job integration is in `app/Jobs/LogTaskRequestJob.php`:

1. Task is loaded and moved to `processing`.
2. `TaskActionService` tries to execute action by `task.type`.
3. If action exists:
   - `meta_json.action_name` is saved
   - `meta_json.action_result` is saved
   - run log `task.action_executed` is inserted
4. Job finishes as `completed`.

If action is unknown, the service returns `executed=false` and the job continues normally.

## API payload example (action task)

Send a task with action name in `type` and action input in `input`:

```json
{
  "type": "analyze_sentiment",
  "input": {
    "text": "This is great"
  },
  "meta": {
    "source": "frontend"
  }
}
```

After queue execution, `meta_json` includes action output:

```json
{
  "source": "frontend",
  "action_name": "analyze_sentiment",
  "action_result": {
    "label": "positive",
    "score": 0.8,
    "status": "stubbed"
  }
}
```

## Calling stubs directly from CLI

A helper command is available:

```bash
php artisan actions:run-stub analyze_sentiment '{"text":"This is great"}'
php artisan actions:run-stub scrape_url '{"url":"https://example.com"}'
```

## Using action output as AI request data

Current implementation stores action result in task metadata, so a future processing job can build AI messages from:

- original input (`tasks.input_json`)
- action output (`tasks.meta_json.action_result`)

Example pseudo-flow in a future AI job:

```php
$actionResult = $task->meta_json['action_result'] ?? [];

$messages = [
    ['role' => 'system', 'content' => 'You are a helpful assistant.'],
    ['role' => 'user', 'content' => json_encode($actionResult)],
];

$response = $aiService->chat($messages);
```

