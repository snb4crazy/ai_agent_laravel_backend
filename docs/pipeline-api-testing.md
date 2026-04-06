# Pipeline API Testing Guide

This guide shows how to test the new predefined action pipeline endpoints.

## Endpoints

- `POST /api/v1/tasks/run-pipeline`
  - Creates a task from a named pipeline in `config/pipelines.php`.
  - Supports `skip_actions`.
- `POST /api/v1/tasks/run-action`
  - Creates a task with exactly one action from request payload.
- `GET /api/v1/tasks/{task_public_id}`
  - Check status and step outputs.
- `GET /api/v1/tasks/{task_public_id}/logs`
  - Check lifecycle and action logs.

## 1) Get auth token

```bash
curl -X POST http://local.aiagent.com/api/v1/auth/token \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@example.com",
    "password": "secret",
    "device_name": "cli-test"
  }'
```

Save `access_token` from response.

## 2) Run full predefined pipeline

```bash
curl -X POST http://local.aiagent.com/api/v1/tasks/run-pipeline \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "pipeline": "all_actions",
    "input": {
      "prompt": "Please process this text",
      "text": "This is great",
      "url": "https://example.com"
    },
    "input_by_action": {
      "scrape_url": { "url": "https://example.com" },
      "analyze_sentiment": { "text": "This is great" },
      "ask_ai": { "prompt": "Say hello from pipeline", "provider": "openai" }
    },
    "skip_actions": ["save_result"],
    "meta": { "source": "manual-api-test" }
  }'
```

Expected: `202 Accepted` with `task_public_id`, status `pending_planning`.

## 2a) Run text-only named pipeline

```bash
curl -X POST http://local.aiagent.com/api/v1/tasks/run-pipeline \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "pipeline": "text_only",
    "input": {"prompt": "Need help with billing"},
    "skip_actions": ["save_result"]
  }'
```

Available named pipelines are configured in `config/pipelines.php`.

## 3) Run one action from request input

```bash
curl -X POST http://local.aiagent.com/api/v1/tasks/run-action \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "analyze_sentiment",
    "input": { "text": "This is great" },
    "meta": { "source": "manual-api-test" }
  }'
```

Expected: `202 Accepted` with `task_public_id`, status `pending_planning`.

## 4) Check status and logs

```bash
curl -X GET http://local.aiagent.com/api/v1/tasks/TASK_PUBLIC_ID \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN"

curl -X GET http://local.aiagent.com/api/v1/tasks/TASK_PUBLIC_ID/logs \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN"
```

## Queue reminder

These endpoints enqueue planner/execution jobs. Make sure worker is running:

```bash
php artisan queue:work redis --queue=service,task
```

For deeper operational setup and debugging, see `docs/operations-runbook.md`.
