# Testing Quickstart

Date: 2026-04-05

This document is the fastest way to manually test the current AI Control Plane flow.

For deeper details, also see:

- `docs/api-endpoints.md`
- `docs/pipeline-api-testing.md`
- `docs/operations-runbook.md`
- `docs/actions-contract.md`

## Before you start

Make sure:

- app is running,
- queue workers are running,
- you have a user created with `php artisan user:create`.

Example worker setup:

```bash
php artisan queue:work redis --queue=service
php artisan queue:work redis --queue=task
```

## Suggested base URL

If you use Laravel built-in server:

```bash
BASE_URL="http://localhost:8000"
```

If you use your local Apache host instead, replace with your real app URL.

## 1) Get auth token

```bash
curl -X POST "${BASE_URL}/api/v1/auth/token" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@example.com",
    "password": "secret",
    "device_name": "cli-test"
  }'
```

Copy `access_token` from the response.

```bash
TOKEN="YOUR_ACCESS_TOKEN"
```

## 2) Run one action

```bash
curl -X POST "${BASE_URL}/api/v1/tasks/run-action" \
  -H "Authorization: Bearer ${TOKEN}" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "analyze_sentiment",
    "input": {
      "text": "This is great"
    },
    "meta": {
      "source": "manual-test"
    }
  }'
```

Expected:

- `202 Accepted`
- `task_public_id`
- `links.status`
- `links.logs`

## 3) Run a named pipeline

```bash
curl -X POST "${BASE_URL}/api/v1/tasks/run-pipeline" \
  -H "Authorization: Bearer ${TOKEN}" \
  -H "Content-Type: application/json" \
  -d '{
    "pipeline": "all_actions",
    "input": {
      "prompt": "Please process this request",
      "text": "This is great",
      "url": "https://example.com"
    },
    "input_by_action": {
      "scrape_url": {"url": "https://example.com"},
      "analyze_sentiment": {"text": "This is great"},
      "ask_ai": {"prompt": "Say hello from pipeline", "provider": "openai"}
    },
    "skip_actions": ["save_result"],
    "meta": {
      "source": "manual-test"
    }
  }'
```

## 4) Poll task status

```bash
TASK_ID="YOUR_TASK_PUBLIC_ID"

curl -X GET "${BASE_URL}/api/v1/tasks/${TASK_ID}" \
  -H "Authorization: Bearer ${TOKEN}"
```

Check:

- `data.status`
- `data.steps[*].status`
- `data.steps[*].output`
- `data.output`

## 5) Poll task logs

```bash
curl -X GET "${BASE_URL}/api/v1/tasks/${TASK_ID}/logs" \
  -H "Authorization: Bearer ${TOKEN}"
```

Check for lifecycle events such as:

- `task.accepted`
- `task.planning_started`
- `task.steps_planned`
- `task_step.executing`
- `task_step.completed`
- `task.completed`

## Good manual test cases

### Single-action success

- `analyze_sentiment`
- `classify_intent`
- `ask_ai`

### Pipeline success

- `text_only`
- `all_actions` with `save_result` skipped

### Error handling

- missing token -> `401`
- invalid payload -> `422`
- unknown task id -> `404`
- invalid `ask_ai` input (no prompt/messages) -> failed action output

## If something gets stuck

Use this order:

1. `GET /api/v1/tasks/{taskPublicId}`
2. `GET /api/v1/tasks/{taskPublicId}/logs`
3. `tail -f storage/logs/laravel.log`
4. verify queue workers are still running

For deeper ops/debugging, use `docs/operations-runbook.md`.
