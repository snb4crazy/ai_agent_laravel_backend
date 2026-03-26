# AI Control Plane Roadmap

Date: 2026-03-20 · Last audited: 2026-03-24

## Phase 1 - Foundation (complete)

- [x] Laravel project bootstrapped
- [x] MySQL configured
- [x] Core control-plane schema and models created
      (`tasks`, `agent_runs`, `run_logs`, `run_usage`, `run_artifacts`,
      `prompt_templates`, `prompt_versions`, `outbox_events`)
- [x] Baseline schema/model tests added

## Phase 2 - API + Auth (mostly done)

- [x] Breeze API auth flow installed and configured (`routes/auth.php`)
- [x] Sanctum installed and token issuance working (`POST /api/v1/auth/token`)
- [ ] Configure Sanctum token abilities (admin/operator/developer)
- [x] API v1 task routes:
  - `POST /api/v1/tasks`
  - `GET /api/v1/tasks/{public_id}`
  - `GET /api/v1/tasks/{public_id}/logs`
- [ ] `GET /api/v1/tasks/{public_id}/runs` (agent run list, not yet implemented)
- [x] Request validation (`StoreTaskDispatchRequest`, `IssueApiTokenRequest`)
- [ ] API resources (Eloquent Resource classes for response shaping)

## Phase 3 - Queue orchestration (partially done)

- [ ] Add `ProcessAgentRunJob` with retry policy and backoff
- [ ] Enforce idempotency key handling for task creation
      (`idempotency_key` column exists in `tasks` and `outbox_events`, but no enforcement logic yet)
- [x] Queue segmentation implemented (`QueueEnum`: `task`, `service`, `webhooks`, `maintenance`, `logs`)
- [x] Horizon installed (`laravel/horizon`, `config/horizon.php`, two supervisors: `task-supervisor`, `service-supervisor`)
- [ ] Horizon alert thresholds (waits config is in place; no notification channels configured yet)

## Phase 4 - Azure OpenAI integration

- [ ] Introduce `AIClientInterface` contract
- [ ] Implement `AzureOpenAIClient` adapter with `chat()` and `embeddings()`
- [ ] Store provider metadata in `agent_runs`
- [ ] Capture usage metrics in `run_usage`
- Note: `openai-php/laravel` is already installed in `composer.json`

## Phase 5 - Observability + governance

- [ ] Structured logging with correlation IDs (`request_id`, `task_public_id`, `run_public_id`)
- [ ] Activity/audit events for key transitions
      (`spatie/laravel-activitylog` installed but not wired up)
- [ ] Scheduled jobs for stale runs cleanup and daily cost summaries
- [ ] API docs via Scribe
      (`knuckleswtf/scribe` installed but not configured)

## Phase 6 - Hardening

- [ ] Policy/authorization coverage
      (`spatie/laravel-permission` installed but not configured)
- [x] Feature tests for task lifecycle (`TaskDispatchFlowTest`, `TaskErrorHandlingTest`)
- [ ] Rate limiting and abuse controls
- [ ] Backup/restore and migration rollback drills

## Phase 7 - Microservice extraction readiness

- [ ] Introduce outbox publisher worker from `outbox_events`
- [ ] Move AI execution behind internal API contract
- [ ] Keep Laravel as control plane (auth, orchestration, billing, auditing)
- [ ] Extract AI executor into separate service when throughput/team needs justify it

