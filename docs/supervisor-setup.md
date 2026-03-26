# Supervisor Setup (QueueEnum Workers)

This guide describes how to run Laravel queue workers with `supervisord` using queue names from `app/Enums/QueueEnum.php`.

## Source of truth

Queue names are defined in code in `app/Enums/QueueEnum.php`. The Redis prefix
(`REDIS_PREFIX` in `.env`) is applied globally across all Redis keys — sessions,
cache, and queues — so queue names themselves stay short:

| Constant               | Queue name    | Redis key (with `REDIS_PREFIX=ai_agent:`) |
|------------------------|---------------|------------------------------------------|
| `QueueEnum::TASK`      | `task`        | `ai_agent:queues:task`                   |
| `QueueEnum::SERVICE`   | `service`     | `ai_agent:queues:service`                |
| `QueueEnum::WEBHOOKS`  | `webhooks`    | `ai_agent:queues:webhooks`               |
| `QueueEnum::MAINTENANCE` | `maintenance` | `ai_agent:queues:maintenance`          |
| `QueueEnum::LOGS`      | `logs`        | `ai_agent:queues:logs`                   |

> **Note:** `queues:` is injected by Laravel's Redis queue driver and cannot be removed.
> The full Redis key is always: `{REDIS_PREFIX}queues:{queue_name}`

If you change a constant in `QueueEnum`, update the Supervisor config `--queue=` values to match.

## Prerequisites

- Application code deployed (example path: `/Users/serhiidymenko/laravel10/ai_agent`)
- PHP + Composer dependencies installed
- Queue connection configured in `.env` (commonly `redis` for production workers)
- `supervisor` installed

## Install and start Supervisor (Homebrew, macOS)

```bash
brew install supervisor
brew services start supervisor
```

Common Homebrew paths:

- Apple Silicon: `/opt/homebrew/etc/supervisor.d/`
- Intel: `/usr/local/etc/supervisor.d/`

## Example Supervisor config

Create file `ai-agent-workers.ini` in your Supervisor include directory.

```ini
[program:ai-agent-task]
process_name=%(program_name)s_%(process_num)02d
command=php /Users/serhiidymenko/laravel10/ai_agent/artisan queue:work redis --queue=task --sleep=1 --tries=3 --timeout=120 --max-time=3600
numprocs=2
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=serhiidymenko
directory=/Users/serhiidymenko/laravel10/ai_agent
stdout_logfile=/Users/serhiidymenko/laravel10/ai_agent/storage/logs/worker-task.log
stderr_logfile=/Users/serhiidymenko/laravel10/ai_agent/storage/logs/worker-task-error.log
stopwaitsecs=3600

[program:ai-agent-service]
process_name=%(program_name)s_%(process_num)02d
command=php /Users/serhiidymenko/laravel10/ai_agent/artisan queue:work redis --queue=service --sleep=1 --tries=3 --timeout=120 --max-time=3600
numprocs=1
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=serhiidymenko
directory=/Users/serhiidymenko/laravel10/ai_agent
stdout_logfile=/Users/serhiidymenko/laravel10/ai_agent/storage/logs/worker-service.log
stderr_logfile=/Users/serhiidymenko/laravel10/ai_agent/storage/logs/worker-service-error.log
stopwaitsecs=3600

[program:ai-agent-webhooks]
process_name=%(program_name)s_%(process_num)02d
command=php /Users/serhiidymenko/laravel10/ai_agent/artisan queue:work redis --queue=webhooks --sleep=1 --tries=3 --timeout=120 --max-time=3600
numprocs=1
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=serhiidymenko
directory=/Users/serhiidymenko/laravel10/ai_agent
stdout_logfile=/Users/serhiidymenko/laravel10/ai_agent/storage/logs/worker-webhooks.log
stderr_logfile=/Users/serhiidymenko/laravel10/ai_agent/storage/logs/worker-webhooks-error.log
stopwaitsecs=3600

[program:ai-agent-maintenance]
process_name=%(program_name)s_%(process_num)02d
command=php /Users/serhiidymenko/laravel10/ai_agent/artisan queue:work redis --queue=maintenance --sleep=1 --tries=1 --timeout=120 --max-time=3600
numprocs=1
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=serhiidymenko
directory=/Users/serhiidymenko/laravel10/ai_agent
stdout_logfile=/Users/serhiidymenko/laravel10/ai_agent/storage/logs/worker-maintenance.log
stderr_logfile=/Users/serhiidymenko/laravel10/ai_agent/storage/logs/worker-maintenance-error.log
stopwaitsecs=3600

[program:ai-agent-logs]
process_name=%(program_name)s_%(process_num)02d
command=php /Users/serhiidymenko/laravel10/ai_agent/artisan queue:work redis --queue=logs --sleep=1 --tries=1 --timeout=120 --max-time=3600
numprocs=1
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=serhiidymenko
directory=/Users/serhiidymenko/laravel10/ai_agent
stdout_logfile=/Users/serhiidymenko/laravel10/ai_agent/storage/logs/worker-logs.log
stderr_logfile=/Users/serhiidymenko/laravel10/ai_agent/storage/logs/worker-logs-error.log
stopwaitsecs=3600
```

## Apply config and check status

```bash
supervisorctl reread
supervisorctl update
supervisorctl status
```

## Deployment command

Run this after each deploy so workers gracefully restart with new code:

```bash
php artisan queue:restart
```

## Minimal setup option

If you only need current flow right now, start with 2 programs:

- `ai-agent-task`
- `ai-agent-service`

Add `webhooks`, `maintenance`, and `logs` workers when you begin dispatching jobs to those queues.

