<h1 align="center">AI Control Plane (Laravel Backend)</h1>

<p align="center">Backend for orchestrating AI tasks, queue execution, auth, and observability.</p>

<p align="center">
<a href="https://github.com/snb4crazy/ai_agent_laravel_backend/actions/workflows/ci.yml"><img src="https://github.com/snb4crazy/ai_agent_laravel_backend/actions/workflows/ci.yml/badge.svg" alt="CI Status"></a>
<a href="https://github.com/snb4crazy/ai_agent_laravel_backend/actions/workflows/release.yml"><img src="https://github.com/snb4crazy/ai_agent_laravel_backend/actions/workflows/release.yml/badge.svg" alt="Release Status"></a>
<a href="https://github.com/snb4crazy/ai_agent_laravel_backend/blob/master/LICENSE"><img src="https://img.shields.io/github/license/snb4crazy/ai_agent_laravel_backend" alt="License"></a>
</p>

## Project Automation

- GitHub Actions workflows: [`docs/github-actions.md`](docs/github-actions.md)
- CI workflow: `.github/workflows/ci.yml`
- Release workflow: `.github/workflows/release.yml`

## What this project is

This repository is a backend-first control plane for AI workloads.

Current scope:

- API authentication with Sanctum tokens
- Task intake endpoints for frontend clients
- Queue-backed task processing flow
- Task status and logs API for polling
- MySQL persistence for tasks, runs, logs, usage, artifacts, outbox
- CLI user provisioning (`user:create`)

It currently logs and persists task payloads; AI execution logic can be expanded later behind the same task lifecycle.

## Architecture snapshot

- Frontend calls API endpoints on this backend
- Backend validates input and persists task immediately
- Backend dispatches queue job (`ai-agent:task` by default)
- Queue worker appends processing logs
- Frontend polls task status and logs by `task_public_id`

See API contract details in [`docs/api-endpoints.md`](docs/api-endpoints.md).

## Stack

- PHP / Laravel 13
- MySQL (app data)
- Redis/Horizon-ready queue setup
- Sanctum auth tokens
- PHPUnit + Pint

## Quick start

### 1) Install dependencies

```bash
composer install
```

### 2) Configure environment

```bash
cp .env.example .env
php artisan key:generate
```

Set DB credentials in `.env` (example):

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ai_agent
DB_USERNAME=ai_agent_user
DB_PASSWORD=your_password
```

### 3) Run migrations

```bash
php artisan migrate
```

### 4) Create first user from CLI

Public `/register` is intentionally disabled.

```bash
php artisan user:create
```

The command prompts for:

- Name
- Email
- Password
- Confirm password

Users created by this command are marked email-verified.

### 5) Run app and worker

```bash
php artisan serve
php artisan queue:work redis --queue=task,service
```

For dedicated worker pools, run separate processes:

```bash
php artisan queue:work redis --queue=task
php artisan queue:work redis --queue=service
```

Queue names are code-defined in `app/Enums/QueueEnum.php` (example: `QueueEnum::TASK`).
Extend that class when you add new worker groups.
`REDIS_PREFIX` in `.env` namespaces all Redis keys globally — queue names themselves stay short (`task`, `service`, etc.).
Queue names are code-defined in `app/Enums/QueueEnum.php` (example: `QueueEnum::TASK`).
Extend that class when you add new worker groups.

## Core API flow

1. Get token: `POST /api/v1/auth/token`
2. Create task: `POST /api/v1/tasks`
3. Poll status: `GET /api/v1/tasks/{task_public_id}`
4. Get logs: `GET /api/v1/tasks/{task_public_id}/logs`

Full request/response examples: [`docs/api-endpoints.md`](docs/api-endpoints.md)

## Database domains

Key tables include:

- `tasks`
- `agent_runs`
- `run_logs`
- `prompt_templates`
- `prompt_versions`
- `run_usage`
- `run_artifacts`
- `outbox_events`

## Quality and release

- Local style check: `vendor/bin/pint --test`
- Local tests: `php artisan test`
- CI runs on pushes/PRs via `.github/workflows/ci.yml`
- Release workflow runs on tags `v*` via `.github/workflows/release.yml`

## Project docs

- **Product focus**: [`docs/use-cases.md`](docs/use-cases.md) - Real-world use cases to guide implementation
- **Work backlog**: [`docs/backlog.md`](docs/backlog.md) - Sprint-ready tickets for Jira/GitHub
- **Deliverables**: [`docs/deliverables.md`](docs/deliverables.md) - MVP scope and "done" criteria
- API docs: [`docs/api-endpoints.md`](docs/api-endpoints.md)
- CI/CD docs: [`docs/github-actions.md`](docs/github-actions.md)
- Operations runbook: [`docs/operations-runbook.md`](docs/operations-runbook.md)
- Supervisor setup: [`docs/supervisor-setup.md`](docs/supervisor-setup.md)
- AI adapters: [`docs/ai-services.md`](docs/ai-services.md)
- AI provider setup (.env + keys): [`docs/ai-provider-setup.md`](docs/ai-provider-setup.md)
- AI request flow: [`docs/ai-request-flow.md`](docs/ai-request-flow.md)
- Action stubs: [`docs/action-stubs.md`](docs/action-stubs.md)
- Actions contract: [`docs/actions-contract.md`](docs/actions-contract.md)
- Multi-step flow: [`docs/multi-step-flow.md`](docs/multi-step-flow.md)
- Testing quickstart: [`docs/testing.md`](docs/testing.md)
- Implementation log: [`docs/actions-log.md`](docs/actions-log.md)
- Roadmap: [`docs/roadmap.md`](docs/roadmap.md)

## Minimal Laravel note

This project is built on Laravel. Framework docs: <https://laravel.com/docs>

## Contributing

PRs are welcome. For larger changes, open an issue first with scope and rationale.

## Security

If you find a security issue, please avoid opening a public issue with exploit details.

## License

This project is licensed under the MIT license. See [`LICENSE`](LICENSE).
