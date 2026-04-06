# Operations Runbook

Date: 2026-04-05

This document is the practical day-to-day runbook for operating the AI Control Plane as a solo developer.

## Goal

Be able to:

- start the app locally,
- run queue workers reliably,
- verify queue consumption,
- trace a task from API request to final output,
- recover from common failures quickly.

## Source of truth

- Queue names: `app/Enums/QueueEnum.php`
- Queue config: `config/queue.php`
- Horizon config: `config/horizon.php`
- Supervisor guide: `docs/supervisor-setup.md`
- API flow: `docs/api-endpoints.md`
- Pipeline testing: `docs/pipeline-api-testing.md`

## Queue names

Current queues defined in code:

- `task`
- `service`
- `webhooks`
- `maintenance`
- `logs`

For the current MVP flow, the important ones are:

- `service` — planner/orchestration jobs
- `task` — step execution + final compile jobs

## Minimum local setup

### 1) Start the app

```bash
php artisan serve
```

If you use Apache or Valet instead of `php artisan serve`, keep using your existing local URL.

### 2) Start queue workers

Minimal current setup:

```bash
php artisan queue:work redis --queue=service
php artisan queue:work redis --queue=task
```

Or one combined worker:

```bash
php artisan queue:work redis --queue=service,task
```

Notes:

- `config/queue.php` defaults to `database`, but your current worker docs and queue segmentation assume `redis`.
- For local pipeline testing, make sure `QUEUE_CONNECTION=redis` if you want real async behavior.
- In automated tests, queue often runs in sync, so behavior may differ from local Redis workers.

## Optional local Horizon setup

If you want Horizon locally:

```bash
php artisan horizon
```

Current Horizon supervisors are configured for:

- `task-supervisor`
- `service-supervisor`

Useful local Horizon URLs/settings depend on your app routing and environment.

## Minimum production/server setup

Recommended starting point for one developer:

- 1 worker for `service`
- 1-2 workers for `task`
- Supervisor or Horizon to keep workers alive

If using Supervisor, see:

- `docs/supervisor-setup.md`

## Deploy / restart commands

After deployment, restart queue workers gracefully:

```bash
php artisan queue:restart
```

If using Horizon:

```bash
php artisan horizon:terminate
```

Useful cache refresh commands after config/env changes:

```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
```

If you cache config in production:

```bash
php artisan config:cache
php artisan route:cache
```

## Health checks

## 1) Verify workers are running

For Supervisor:

```bash
supervisorctl status
```

For Horizon:

```bash
php artisan horizon:status
```

## 2) Verify queue connection and app basics

```bash
php artisan about
php artisan queue:monitor redis:task,redis:service
```

## 3) Verify logs are being written

Laravel app log:

```bash
tail -f storage/logs/laravel.log
```

Supervisor worker logs may also exist if configured, for example:

```bash
tail -f storage/logs/worker-task.log
tail -f storage/logs/worker-service.log
```

## Quick task tracing flow

When debugging one task, use this order:

1. API request creates task
2. Save returned `task_public_id`
3. Check task status endpoint
4. Check task logs endpoint
5. Check Laravel / worker logs
6. Check Redis worker/Horizon state

### Example trace

#### 1) Create task

```bash
curl -X POST "http://localhost:8000/api/v1/tasks/run-action" \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "analyze_sentiment",
    "input": {"text": "This is great"}
  }'
```

#### 2) Poll task status

```bash
curl -X GET "http://localhost:8000/api/v1/tasks/TASK_PUBLIC_ID" \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN"
```

#### 3) Poll task logs

```bash
curl -X GET "http://localhost:8000/api/v1/tasks/TASK_PUBLIC_ID/logs" \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN"
```

## Expected task lifecycle

Current intended lifecycle for pipeline-based tasks:

- `pending_planning`
- `planning`
- `executing`
- `completed`
- `failed`

Legacy/simple path may still use `queued` differently, so prefer pipeline-based flows when validating current orchestration.

## Stuck task debugging checklist

If a task stays in `pending_planning`:

- check `service` worker is running
- check Redis connection/env values
- check `storage/logs/laravel.log`
- check if planner job was dispatched but never consumed

If a task stays in `executing`:

- check `task` worker is running
- inspect `data.steps` via task status endpoint
- inspect `run_logs` via logs endpoint
- check whether one action step failed silently or did not dispatch next step

If a task becomes `failed`:

- inspect `error_message` on the task
- inspect latest `task_step.failed`, `task.planning_failed`, or `task.compile_failed` log events
- inspect Laravel and worker logs for stack traces

## Failed jobs

List failed jobs:

```bash
php artisan queue:failed
```

Retry one failed job:

```bash
php artisan queue:retry FAILED_JOB_UUID
```

Retry all failed jobs:

```bash
php artisan queue:retry all
```

Delete one failed job:

```bash
php artisan queue:forget FAILED_JOB_UUID
```

Flush all failed jobs:

```bash
php artisan queue:flush
```

## Redis / key naming notes

Laravel’s Redis queue driver stores jobs under keys like:

```text
{REDIS_PREFIX}queues:{queue_name}
```

Example with `REDIS_PREFIX=ai_agent:`:

```text
ai_agent:queues:task
ai_agent:queues:service
```

This is normal; `queues:` is added by Laravel.

## Important file/log locations

Application log:

```text
storage/logs/laravel.log
```

Common worker logs when using Supervisor examples from this repo:

```text
storage/logs/worker-task.log
storage/logs/worker-task-error.log
storage/logs/worker-service.log
storage/logs/worker-service-error.log
```

## Recommended .env baseline for queue operations

Example minimum queue-related settings:

```dotenv
QUEUE_CONNECTION=redis
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
REDIS_PREFIX=ai_agent:
LOG_CHANNEL=stack
LOG_LEVEL=debug
AI_PROVIDER=azure
```

Adjust Redis credentials and provider values for your environment.

## Solo developer operating model

For current MVP, keep it simple:

- Use `service` + `task` queues only.
- Use API endpoints as the primary test surface.
- Use task status/logs as first-line observability.
- Use Supervisor or Horizon, but not both unless you need it.
- Prefer one documented path for testing and deployment.

