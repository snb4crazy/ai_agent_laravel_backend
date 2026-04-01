# Actions Catalog (PoC)

This document describes the current real-world proof-of-concept actions used by the queue flow.

## Why these actions

The list is intentionally small but enough to prove the agent pipeline end-to-end:

- fetch data (`scrape_url`)
- understand data (`summarize_text`, `analyze_sentiment`, `classify_intent`)
- generate output (`generate_reply`)
- persist output (`save_result`)

## Available actions

Configured in `config/actions.php`:

- `scrape_url`
- `summarize_text`
- `analyze_sentiment`
- `classify_intent`
- `generate_reply`
- `save_result`

All actions implement `App\Actions\Contracts\ActionStubInterface` (interface name kept for compatibility).

## Action behavior (simple but real)

- `scrape_url`
  - accepts `url`
  - fetches HTML via Laravel HTTP client
  - extracts `title` and plain text `content`
- `summarize_text`
  - accepts `text` (or `previous_output.content`)
  - returns short extractive summary + top keywords
- `analyze_sentiment`
  - accepts `text`
  - returns label (`positive|neutral|negative`) and score
- `classify_intent`
  - accepts `text`
  - returns intent (`refund_request|billing_question|technical_issue|sales_question|general_question`)
- `generate_reply`
  - accepts `text`
  - can use `previous_output` (intent/sentiment) to build a contextual reply
- `save_result`
  - accepts any payload
  - stores JSON under `storage/app/action-results/YYYY/MM/DD/*.json`

## Where actions run

`app/Jobs/ExecuteTaskStepJob.php` runs each step:

1. Load `TaskStep` and mark `executing`
2. Resolve action in `TaskActionService`
3. Save result to `task_steps.output_json`
4. Dispatch next step, then compile final task output

## CLI quick test

```bash
php artisan actions:run analyze_sentiment '{"text":"This is great"}'
php artisan actions:run scrape_url '{"url":"https://example.com"}'
php artisan actions:run summarize_text '{"text":"Long text here..."}'
```

Backward-compatible alias still works:

```bash
php artisan actions:run-stub analyze_sentiment '{"text":"This is great"}'
```

## API payload example

```json
{
  "type": "multi_step_task",
  "input": {
    "prompt": "I was charged twice. Please help with refund."
  },
  "meta": {
    "source": "frontend"
  }
}
```

This type uses default plan:

1. `analyze_sentiment`
2. `generate_reply`
3. `save_result`
