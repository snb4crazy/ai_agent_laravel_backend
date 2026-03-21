# AI Control Plane Roadmap

Date: 2026-03-20

## Phase 1 - Foundation (current)

- [x] Laravel project bootstrapped
- [x] MySQL configured
- [x] Core control-plane schema and models created
- [x] Baseline schema/model tests added

## Phase 2 - API + Auth

- [ ] Install/confirm Breeze API auth flow
- [ ] Configure Sanctum token abilities (admin/operator/developer)
- [ ] Add API v1 routes for tasks and runs:
  - `POST /api/v1/tasks`
  - `GET /api/v1/tasks/{public_id}`
  - `GET /api/v1/tasks/{public_id}/runs`
- [ ] Add request validation + API resources

## Phase 3 - Queue orchestration

- [ ] Add `ProcessAgentRunJob` and retry policy with backoff
- [ ] Enforce idempotency key handling for task creation
- [ ] Configure queue separation (`high`, `default`, `ai`, `low`)
- [ ] Add Horizon dashboard and alert thresholds

## Phase 4 - Azure OpenAI integration

- [ ] Introduce `AIClientInterface` contract
- [ ] Implement `AzureOpenAIClient` adapter with `chat()` and `embeddings()`
- [ ] Store provider metadata in `agent_runs`
- [ ] Capture usage metrics in `run_usage`

## Phase 5 - Observability + governance

- [ ] Structured logging with correlation IDs (`request_id`, `task_public_id`, `run_public_id`)
- [ ] Add activity/audit events for key transitions
- [ ] Add scheduled jobs for stale runs cleanup and daily cost summaries
- [ ] Add API docs via Scribe

## Phase 6 - Hardening

- [ ] Add policy/authorization coverage
- [ ] Add feature tests for full task lifecycle (queued -> running -> succeeded/failed)
- [ ] Add rate limiting and abuse controls
- [ ] Add backup/restore and migration rollback drills

## Phase 7 - Microservice extraction readiness

- [ ] Introduce outbox publisher worker from `outbox_events`
- [ ] Move AI execution behind internal API contract
- [ ] Keep Laravel as control plane (auth, orchestration, billing, auditing)
- [ ] Extract AI executor into separate service when throughput/team needs justify it

