# AI Control Plane Deliverables

Date: 2026-04-03

## Why this document exists

This project has grown quickly. This file defines what to ship next so scope stays focused.

## Product goal (single sentence)

Ship a reliable Laravel AI control plane that accepts authenticated tasks, executes predefined action pipelines through queues, and exposes status/log APIs so a frontend can submit work and poll outcomes safely.

## Current state (verified in code)

Implemented now:

- Auth token issuance: `POST /api/v1/auth/token`
- Protected task endpoints in `routes/api.php`:
  - `POST /api/v1/tasks`
  - `POST /api/v1/tasks/run-pipeline`
  - `POST /api/v1/tasks/run-action`
  - `GET /api/v1/tasks/{taskPublicId}`
  - `GET /api/v1/tasks/{taskPublicId}/logs`
- Queue-based orchestration jobs:
  - `PlanTaskStepsJob`
  - `ExecuteTaskStepJob`
  - `CompileTaskOutputJob`
  - `LogTaskRequestJob` (legacy/simple path)
- Action registry + named pipelines:
  - `config/actions.php`
  - `config/pipelines.php`
- Core persistence: tasks, task steps, run logs, and related AI control-plane tables
- CLI user provisioning: `php artisan user:create`
- CI basics: Pint + tests in GitHub Actions

## What to deliver (MVP)

### Deliverable 1: Stable API contract for frontend

Scope:

- Keep `auth/token`, `tasks`, `task status`, `task logs` as the primary integration contract.
- Keep response shape stable for `task_public_id`, `status`, errors.

Done when:

- Frontend can always do 2-step flow: login -> create task.
- Frontend can poll task status/logs without knowing queue/job internals.
- Error format is consistent for validation/auth/domain failures.

### Deliverable 2: One canonical execution path

Scope:

- Make multi-step pipeline path the default for new work.
- Keep legacy single-job route only if needed for backward compatibility.

Done when:

- Task lifecycle is clear and observable: `queued -> planning -> executing -> completed|failed`.
- Every state transition is logged in `run_logs`.
- Failures produce actionable `error_message` for UI and debugging.

### Deliverable 3: Action proof-of-concept pipeline

Scope:

- Keep a small, real action set (scrape/summarize/classify/ask_ai/save_result).
- Ensure each action is atomic and callable through pipeline definitions.

Done when:

- `POST /api/v1/tasks/run-pipeline` executes a named pipeline end-to-end.
- `POST /api/v1/tasks/run-action` executes one action end-to-end.
- Output of each step is visible via task status/logs.

### Deliverable 4: Provider routing for AI calls

Scope:

- Keep `AIServiceResolver` selecting provider by request override or config default.
- Keep OpenAI/Azure working path; stubs for other providers are acceptable.

Done when:

- `ask_ai` action can call selected provider and log provider/model/result metadata.
- Unknown provider gracefully falls back to configured default.
- Unit tests cover resolver behavior.

### Deliverable 5: Operational readiness for single developer

Scope:

- Keep queue naming + worker segregation simple (`task`, `service`, etc.).
- Keep supervisor/horizon docs current.

Done when:

- You can run workers locally and in server with predictable queue names.
- You can trace one task from API request to final output in logs.
- README + docs are aligned with actual routes and behavior.

## Not in MVP (defer intentionally)

Do later, not now:

- Full RBAC/permissions matrix
- Outbox publisher microservice split
- Advanced cost dashboard/alerts
- Complex AI planning autonomy beyond predefined pipelines
- Full public API portal generation and advanced governance

## Suggested release targets

### v0.3 (next practical release)

- Stable auth + task APIs
- Canonical multi-step execution path
- End-to-end pipeline tests passing
- Docs aligned (`README`, `docs/api-endpoints.md`, this file)

### v0.4

- Provider metadata + usage tracking polish
- Better retry/idempotency handling
- Minimal operator dashboard data endpoints

## Weekly focus checklist

Use this every week to avoid scope drift:

- [ ] Does this task improve API reliability, execution reliability, or observability?
- [ ] Does this task move us toward a demoable end-to-end user flow?
- [ ] If not, is it explicitly scheduled for post-MVP?
- [ ] Are docs and tests updated for behavior changes?

## Demo flow to validate delivery

1. Create user: `php artisan user:create`
2. Get token: `POST /api/v1/auth/token`
3. Create task: `POST /api/v1/tasks` or `POST /api/v1/tasks/run-pipeline`
4. Poll status: `GET /api/v1/tasks/{taskPublicId}`
5. Inspect logs: `GET /api/v1/tasks/{taskPublicId}/logs`

If this flow is clean and predictable, the product is delivering the right thing.

