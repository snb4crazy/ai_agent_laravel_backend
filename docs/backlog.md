# AI Control Plane Backlog

Date: 2026-04-09

## How to use this backlog

- Copy ticket details into your Jira/GitHub issues board.
- Each ticket includes: **Summary**, **Description**, **Acceptance Criteria**, **Story Points**.
- **Priority** indicates urgency (P0 = blocking, P1 = MVP, P2 = next, P3 = nice-to-have).
- Link each ticket to a related use case in `docs/use-cases.md`.

---

## v0.3 MVP Tickets (Release Target: 2 weeks)

These are required to ship a demoable, reliable control plane.

### P1-001: Fix Pint Code Style Issues (Phase 1)

**Summary**: Resolve all code style violations to make CI/CD pass cleanly.

**Description**: 
GitHub Actions reports 3+ Pint style issues blocking merges. Fix all file formatting issues (single blank line at EOF, unary operator spaces, class definitions).

**Acceptance Criteria**:
- [ ] All Pint violations resolved locally
- [ ] CI passes on all PHP versions in GitHub Actions
- [ ] No pending style errors in main branch

**Story Points**: 2
**Priority**: P1
**Related Use Case**: None (ops)

---

### P1-002: Implement Real Action: Scrape URL

**Summary**: Replace `ScrapeUrlActionStub` with a working URL scraper.

**Description**:
Use a simple HTTP client (e.g., Guzzle) to fetch and parse URL content. Extract main text/title. Return clean text for downstream actions.

**Acceptance Criteria**:
- [ ] `ScrapeUrlAction` fetches URL and returns clean text
- [ ] Handles 404, timeouts, invalid URLs gracefully
- [ ] Integration test covers happy path + error cases
- [ ] Logs source URL and content length in task logs

**Story Points**: 3
**Priority**: P1
**Related Use Case**: Use Case 1 (URL Briefing)

---

### P1-003: Implement Real Action: Summarize Text

**Summary**: Replace `SummarizeTextActionStub` with real AI summarization.

**Description**:
Use configured AI provider (OpenAI/Azure) to summarize input text. Support configurable max tokens/length hints. Return summary in task output.

**Acceptance Criteria**:
- [ ] `SummarizeTextAction` calls AI provider with text
- [ ] Respects provider/model from task context
- [ ] Returns concise summary + token usage logged
- [ ] Feature test covers happy path + provider failure
- [ ] Works in pipeline after `scrape_url`

**Story Points**: 3
**Priority**: P1
**Related Use Case**: Use Case 1 (URL Briefing)

---

### P1-004: Implement Real Action: Classify Intent

**Summary**: Replace `ClassifyIntentActionStub` with real intent classification.

**Description**:
Use AI provider to classify input text into predefined categories (e.g., bug, billing, feature request, feedback). Return classification + confidence.

**Acceptance Criteria**:
- [ ] `ClassifyIntentAction` calls AI with classification prompt
- [ ] Returns intent label + confidence score
- [ ] Logs classification decision to run logs
- [ ] Feature test covers multi-class scenario + fallback

**Story Points**: 2
**Priority**: P1
**Related Use Case**: Use Case 2 (Support Triage), Use Case 5 (Lead Qualification)

---

### P1-005: Implement Real Action: Analyze Sentiment

**Summary**: Replace `AnalyzeSentimentActionStub` with real sentiment analysis.

**Description**:
Use AI provider to analyze sentiment of input text. Return sentiment label (positive/neutral/negative) + confidence.

**Acceptance Criteria**:
- [ ] `AnalyzeSentimentAction` calls AI with sentiment prompt
- [ ] Returns sentiment + confidence + explanation
- [ ] Logs to run logs with original text snippet
- [ ] Feature test covers all sentiment classes

**Story Points**: 2
**Priority**: P1
**Related Use Case**: Use Case 3 (Sentiment Monitor), Use Case 2 (Support Triage)

---

### P1-006: Implement Real Action: Generate Reply

**Summary**: Replace `GenerateReplyActionStub` with real reply draft generation.

**Description**:
Use AI provider to generate a helpful reply based on input text. Include tone/style guidance. Return draft for human review.

**Acceptance Criteria**:
- [ ] `GenerateReplyAction` calls AI with reply generation prompt
- [ ] Accepts optional tone/style parameters
- [ ] Returns draft reply in task output
- [ ] Logs prompt + response metadata
- [ ] Feature test includes edge cases (very long input, special chars)

**Story Points**: 3
**Priority**: P1
**Related Use Case**: Use Case 4 (First-Draft Reply)

---

### P1-007: Implement Real Action: Save Result

**Summary**: Implement working `SaveResultAction` to persist pipeline output.

**Description**:
Write pipeline output to persistent storage (e.g., new `TaskResult` table or external system mock). Include timestamp, task context, and all step outputs.

**Acceptance Criteria**:
- [ ] `SaveResultAction` persists task output to `task_results` table
- [ ] Output includes all prior step results
- [ ] Updates task status to `completed` if final step
- [ ] Logs persistence event
- [ ] Feature test verifies data persistence

**Story Points**: 2
**Priority**: P1
**Related Use Case**: All use cases (final step)

---

### P1-008: Implement Real Action: Ask AI (Generic)

**Summary**: Implement `AskAiAction` for arbitrary AI queries.

**Description**:
Accept free-form prompt + optional context/examples. Call AI provider and return response. Support provider override per request.

**Acceptance Criteria**:
- [ ] `AskAiAction` accepts prompt + optional context
- [ ] Supports provider override (querystring or header)
- [ ] Returns raw AI response in task output
- [ ] Logs provider/model/usage metrics
- [ ] Feature test covers provider fallback if unknown

**Story Points**: 3
**Priority**: P1
**Related Use Case**: Use Case 5 (Lead Qualification), Use Case 4, Use Case 6

---

### P1-009: End-to-End Pipeline Test: URL Briefing Flow

**Summary**: Write feature test for full URL briefing pipeline (scrape → summarize → save).

**Description**:
Create end-to-end test that authenticates, sends task, waits for completion, and verifies output via status/logs endpoints.

**Acceptance Criteria**:
- [ ] Feature test creates user and token
- [ ] Submits `run-pipeline` with name `"url_briefing"` + real URL
- [ ] Polls status until `completed`
- [ ] Verifies final output contains summary
- [ ] Verifies all run logs are present
- [ ] Test completes in < 10 seconds (mock AI if needed)

**Story Points**: 3
**Priority**: P1
**Related Use Case**: Use Case 1

---

### P1-010: Implement Named Pipelines in Config

**Summary**: Define reusable pipelines in `config/pipelines.php`.

**Description**:
Create named pipeline definitions (e.g., `url_briefing`, `support_triage`, `sentiment_monitor`). Each pipeline lists actions in sequence + any config overrides. Validate pipeline config on boot.

**Acceptance Criteria**:
- [ ] `config/pipelines.php` defines >= 3 pipelines
- [ ] `POST /api/v1/tasks/run-pipeline?pipeline=url_briefing` works
- [ ] Unknown pipeline returns clear 400 error
- [ ] Each pipeline is testable end-to-end
- [ ] Docs updated with available pipelines

**Story Points**: 2
**Priority**: P1
**Related Use Case**: All use cases

---

### P1-011: Stable Error Response Format

**Summary**: Ensure all error responses follow consistent JSON structure.

**Description**:
Audit all API endpoints to return consistent error format: `{ "error": "...", "code": "...", "details": {...} }`. Handle validation, auth, and runtime errors uniformly.

**Acceptance Criteria**:
- [ ] All 400/401/403/500 responses use same structure
- [ ] Validation errors include field-level details
- [ ] Auth failures include retry hint
- [ ] Domain/queue failures include actionable message
- [ ] Feature tests verify error format per endpoint

**Story Points**: 3
**Priority**: P1
**Related Use Case**: Deliverable 1

---

### P1-012: API Documentation: Endpoints + Examples

**Summary**: Update or create `docs/api-endpoints.md` with all routes and cURL examples.

**Description**:
Document all v1 endpoints: auth, tasks, status, logs. Include request/response examples for each. Update README to link to this doc.

**Acceptance Criteria**:
- [ ] `docs/api-endpoints.md` covers all routes
- [ ] Each endpoint has cURL example
- [ ] Examples show happy path + error case
- [ ] Response payloads are real (not stubs)
- [ ] README links to this doc

**Story Points**: 2
**Priority**: P1
**Related Use Case**: Deliverable 1

---

### P1-013: Local Testing Guide

**Summary**: Create `docs/local-testing.md` for developers to test end-to-end locally.

**Description**:
Guide for running Laravel locally, seeding test data, running queue workers, and testing a full flow with cURL/Postman.

**Acceptance Criteria**:
- [ ] Step-by-step setup instructions (Docker optional)
- [ ] Example: create user → get token → post task → poll status
- [ ] Include common errors + solutions
- [ ] Mention how to tail queue logs
- [ ] Easy for new dev to follow

**Story Points**: 2
**Priority**: P1
**Related Use Case**: Deliverable 5

---

---

## v0.4 Tickets (Release Target: 2-4 weeks after v0.3)

These improve reliability, monitoring, and basic operations.

### P2-001: Task Retry Logic and Idempotency

**Summary**: Implement automatic retry for failed queue jobs with exponential backoff.

**Description**:
Add retry count + max attempts to task steps. On job failure, requeue with exponential backoff. Track retry history in run logs.

**Acceptance Criteria**:
- [ ] Failed jobs automatically retry up to 3 times
- [ ] Retry wait times increase exponentially (1s, 5s, 30s)
- [ ] Each retry attempt is logged
- [ ] Task marked as `failed` only after all retries exhausted
- [ ] Unit test covers retry logic

**Story Points**: 3
**Priority**: P2
**Related Use Case**: Deliverable 5 (reliability)

---

### P2-002: Provider Usage Metrics + Logging

**Summary**: Track and log AI provider usage (tokens, cost estimates, response time).

**Description**:
When `AskAiAction` or any AI action runs, capture: provider, model, prompt_tokens, completion_tokens, total_cost (if available), latency. Store in `run_usage` table. Expose in task logs.

**Acceptance Criteria**:
- [ ] `RunUsage` model captures provider/model/tokens
- [ ] `AskAiAction` populates `run_usage` after each call
- [ ] Task logs include usage summary (e.g., "OpenAI: 500 tokens, 0.001s")
- [ ] Feature test verifies usage is captured

**Story Points**: 3
**Priority**: P2
**Related Use Case**: Deliverable 4, Use Case 10

---

### P2-003: Local AI Provider Stub (LLaMA/Ollama compatibility)

**Summary**: Add stub for local LLM (e.g., Ollama) so developers can test offline.

**Description**:
Implement `LocalLLMService` that calls local endpoint (e.g., http://localhost:11434). Use same interface as OpenAI/Azure. Graceful fallback if local unavailable.

**Acceptance Criteria**:
- [ ] `LocalLLMService` implements `AIServiceInterface`
- [ ] Routes calls to configurable local endpoint
- [ ] Falls back to default provider if local fails
- [ ] Unit test mocks local endpoint
- [ ] Config option to enable local testing

**Story Points**: 2
**Priority**: P2
**Related Use Case**: None (developer experience)

---

### P2-004: Anthropic Claude Service Integration

**Summary**: Add Claude/Anthropic as a provider option.

**Description**:
Implement `AnthropicService` using Anthropic API. Use same interface. Support in provider resolver.

**Acceptance Criteria**:
- [ ] `AnthropicService` implements `AIServiceInterface`
- [ ] Handles Anthropic-specific response format
- [ ] Config includes Anthropic API key
- [ ] Unit test covers happy path
- [ ] Provider resolver includes Anthropic as option

**Story Points**: 2
**Priority**: P2
**Related Use Case**: Use Case 10 (provider A/B testing)

---

### P2-005: Support Triage Pipeline (Pre-built)

**Summary**: Define and test `support_triage` pipeline: classify_intent → analyze_sentiment → ask_ai → save_result.

**Description**:
Build and validate a realistic support triage pipeline. Include prompt templates for each step. Make it demoable.

**Acceptance Criteria**:
- [ ] Pipeline defined in `config/pipelines.php`
- [ ] Feature test runs full pipeline on sample support message
- [ ] Output includes intent, sentiment, and AI-suggested response
- [ ] All logs captured and readable via `/logs` endpoint

**Story Points**: 3
**Priority**: P2
**Related Use Case**: Use Case 2

---

### P2-006: Sentiment Monitor Pipeline (Pre-built)

**Summary**: Define and test `sentiment_monitor` pipeline: analyze_sentiment → save_result.

**Description**:
Simple pipeline for bulk feedback sentiment analysis. Chain multiple tasks for batch processing.

**Acceptance Criteria**:
- [ ] Pipeline defined in `config/pipelines.php`
- [ ] Feature test runs sentiment on multiple text samples
- [ ] Output aggregates sentiment distribution
- [ ] Suitable for batch feedback import

**Story Points**: 2
**Priority**: P2
**Related Use Case**: Use Case 3

---

### P2-007: Horizon Dashboard Basic Setup

**Summary**: Configure Laravel Horizon for local/dev queue monitoring.

**Description**:
Install and configure Horizon. Expose at `/admin/horizon` (optional auth). Show queue stats, failed jobs, job traces.

**Acceptance Criteria**:
- [ ] Horizon installed and accessible locally
- [ ] Dashboard shows queue depth, job counts
- [ ] Failed jobs visible and retryable from dashboard
- [ ] Docs updated with Horizon setup

**Story Points**: 2
**Priority**: P2
**Related Use Case**: Deliverable 5 (observability)

---

### P2-008: Supervisor Config + Deployment Guide

**Summary**: Document Supervisor setup for production queue workers.

**Description**:
Create `docs/supervisor-setup.md` with example Supervisor configs for different queue names (task, service, webhook, logs). Include start/stop/restart commands.

**Acceptance Criteria**:
- [ ] Example configs for each queue name in `QueueEnum`
- [ ] Instructions for deploying to Linux server
- [ ] Health check and auto-restart settings
- [ ] Log rotation guidelines
- [ ] Easy copy-paste for solo dev ops

**Story Points**: 2
**Priority**: P2
**Related Use Case**: Deliverable 5

---

### P2-009: GitHub Actions: Multi-PHP Version Testing

**Summary**: Extend CI to test against PHP 8.3 and 8.4 stably.

**Description**:
Fix dependency version constraints so tests pass on both PHP 8.3 and 8.4. Update composer.lock. Add matrix in GitHub Actions.

**Acceptance Criteria**:
- [ ] composer.lock resolves for PHP 8.3 and 8.4
- [ ] GitHub Actions matrix tests both versions
- [ ] All tests pass on both versions
- [ ] No warnings in dependency resolution

**Story Points**: 3
**Priority**: P2
**Related Use Case**: Deliverable 5 (CI/CD)

---

### P2-010: First-Draft Reply Pipeline (Pre-built)

**Summary**: Define and test `first_draft_reply` pipeline: classify_intent → generate_reply → save_result.

**Description**:
Build realistic reply-draft pipeline. Include tone/style parameters. Make output suitable for human review.

**Acceptance Criteria**:
- [ ] Pipeline defined in `config/pipelines.php`
- [ ] Feature test runs full pipeline on support message
- [ ] Output includes intent and draft reply
- [ ] Generated reply is professional and contextual

**Story Points**: 3
**Priority**: P2
**Related Use Case**: Use Case 4

---

### P2-011: Request Context Logging (Middleware)

**Summary**: Add request context (user_id, task_public_id, route, request_id) to all logs.

**Description**:
Implement middleware to inject request context into log payloads. Use Laravel's context feature or custom channel. Apply to all API requests.

**Acceptance Criteria**:
- [ ] Request ID generated and logged for each request
- [ ] User ID added to logs if authenticated
- [ ] Task public ID included in job logs if present
- [ ] Route name logged with context
- [ ] Existing logs already have context (verify)

**Story Points**: 2
**Priority**: P2
**Related Use Case**: Deliverable 5 (observability)

---

### P2-012: Task Output Storage Table

**Summary**: Create `task_results` table to persist final pipeline outputs.

**Description**:
Migration + model for storing final task output. Link to task via FK. Include structured output (JSON), completion time, provider metadata if applicable.

**Acceptance Criteria**:
- [ ] Migration creates `task_results` table
- [ ] `TaskResult` model created
- [ ] `Task` model has `hasOne('TaskResult')` relationship
- [ ] Populated by `SaveResultAction`
- [ ] Test verifies persistence

**Story Points**: 1
**Priority**: P2
**Related Use Case**: Deliverable 2

---

---

## v0.5+ Tickets (Post-MVP, Low Priority)

Features and improvements for future phases.

### P3-001: Human Approval Gate for Task Output

**Summary**: Implement approval workflow: mark task as `waiting_approval`, expose approval endpoint, transition to `completed` on approval.

**Description**:
Add task state `waiting_approval`. Create `PUT /api/v1/tasks/{taskPublicId}/approve` endpoint. Auditable approval history.

**Acceptance Criteria**:
- [ ] Task can transition to `waiting_approval` state
- [ ] `/approve` endpoint accessible to authorized user
- [ ] Approval logged with user + timestamp
- [ ] Feature test covers happy path + missing approval

**Story Points**: 3
**Priority**: P3
**Related Use Case**: Use Case 8

---

### P3-002: Webhook Integration for Task Completion

**Summary**: Support task completion callbacks to external URLs.

**Description**:
Add optional `webhook_url` field to task. On completion, POST task data + output to webhook. Retry on failure. Log webhook attempts.

**Acceptance Criteria**:
- [ ] Task includes optional `webhook_url` field
- [ ] On completion, callback is fired with task + output
- [ ] Webhook requests retried if failed
- [ ] Webhook events logged in run logs
- [ ] Feature test covers success + failure cases

**Story Points**: 3
**Priority**: P3
**Related Use Case**: Use Case 7

---

### P3-003: Task Scheduling (One-time and Recurring)

**Summary**: Support scheduled task creation (e.g., daily digest).

**Description**:
Add `scheduled_for` + `recurrence` fields to Task. Use Laravel scheduler to dispatch recurring tasks. Track recurrence history.

**Acceptance Criteria**:
- [ ] Task can be scheduled for future time
- [ ] Recurring tasks run on schedule (cron)
- [ ] Each recurrence creates new task record
- [ ] Dashboard can list scheduled/recurring tasks
- [ ] Feature test covers scheduling logic

**Story Points**: 4
**Priority**: P3
**Related Use Case**: Use Case 7 (Watch Digest)

---

### P3-004: Multi-Step Approval Workflow

**Summary**: Support approval by multiple reviewers with conditional routing.

**Description**:
Task can require approval from multiple roles. Routing logic decides approval path. Audit trail of all approvals.

**Acceptance Criteria**:
- [ ] Task includes approval chain definition
- [ ] Each approver notified (email or in-app)
- [ ] Task blocked until all required approvers sign off
- [ ] Audit log captures all approval events
- [ ] Feature test covers multi-approver scenario

**Story Points**: 5
**Priority**: P3
**Related Use Case**: Use Case 8

---

### P3-005: AI Planning Service (Experimental)

**Summary**: Prototype simple task decomposition: given goal, AI suggests action pipeline.

**Description**:
Endpoint to accept user goal (e.g., "get summary of tech news"). AI suggests action sequence (scrape → summarize → save). User reviews, then executes.

**Acceptance Criteria**:
- [ ] `POST /api/v1/ai/suggest-pipeline` accepts goal
- [ ] Returns ordered action list + reasoning
- [ ] User can execute suggested pipeline or modify
- [ ] Feature test covers suggestion + execution

**Story Points**: 5
**Priority**: P3
**Related Use Case**: Future use case (AI-driven automation)

---

### P3-006: Cost Tracking and Alerts

**Summary**: Track cumulative AI usage cost. Alert if threshold exceeded.

**Description**:
Aggregate `run_usage` data. Calculate cost per provider/day. Email alert if daily cost exceeds configurable threshold.

**Acceptance Criteria**:
- [ ] `RunUsage` includes cost estimate per call
- [ ] Daily cost report generated
- [ ] Alert email sent if threshold exceeded
- [ ] Dashboard shows cost trends
- [ ] Configurable thresholds per environment

**Story Points**: 3
**Priority**: P3
**Related Use Case**: Deliverable 4

---

### P3-007: Basic Admin Dashboard

**Summary**: Minimal web UI to view recent tasks, queues, usage.

**Description**:
Simple Blade template at `/admin/dashboard` showing: recent tasks + status, queue depth, daily usage, error rate.

**Acceptance Criteria**:
- [ ] Dashboard accessible to authenticated user
- [ ] Shows last 20 tasks + status
- [ ] Shows current queue depth per queue name
- [ ] Shows daily usage chart (tokens, cost)
- [ ] Responsive design (mobile-friendly)

**Story Points**: 4
**Priority**: P3
**Related Use Case**: Deliverable 5

---

### P3-008: Advanced Provider Routing (A/B Testing)

**Summary**: Support splitting traffic between providers for cost/quality comparison.

**Description**:
Config option to route X% of tasks to provider A, Y% to provider B. Collect metrics per provider. Feature flag to switch default.

**Acceptance Criteria**:
- [ ] Config defines provider weights
- [ ] Tasks randomly routed based on weights
- [ ] Metrics collected per provider
- [ ] Feature flag to switch default easily
- [ ] Unit test verifies routing distribution

**Story Points**: 3
**Priority**: P3
**Related Use Case**: Use Case 10

---

### P3-009: Email Notifications for Task Events

**Summary**: Send email on task completion, failure, or manual approval needed.

**Description**:
Configure email notifications for task state changes. Support per-task overrides. Use Laravel Mail.

**Acceptance Criteria**:
- [ ] Email sent on task completion
- [ ] Email sent on task failure (with error context)
- [ ] Email sent when approval needed
- [ ] User can subscribe/unsubscribe
- [ ] Feature test mocks email sending

**Story Points**: 2
**Priority**: P3
**Related Use Case**: Use Case 8

---

### P3-010: API Rate Limiting per User

**Summary**: Implement token-bucket rate limiting on task creation.

**Description**:
Limit authenticated user to N tasks/minute. Return 429 if exceeded. Configurable per environment.

**Acceptance Criteria**:
- [ ] Rate limit enforced in middleware
- [ ] Response includes `X-RateLimit-*` headers
- [ ] 429 response if exceeded
- [ ] Config to adjust limits
- [ ] Feature test verifies rate limit

**Story Points**: 2
**Priority**: P3
**Related Use Case**: Deliverable 5 (reliability)

---

---

## Docs-Only Tickets (No Code, High Value)

### P1-D01: README Overhaul

**Summary**: Rewrite `README.md` to focus on AI agent purpose, not Laravel boilerplate.

**Description**:
Remove generic Laravel scaffolding. Add: product goal (1 sentence), key features (3-5 bullets), quick start (5 steps), architecture diagram (ASCII ok), link to docs.

**Acceptance Criteria**:
- [ ] README < 150 lines
- [ ] Product goal clear upfront
- [ ] Quick start runnable in 5 minutes
- [ ] Links to key docs (API, testing, use cases)
- [ ] No Laravel jargon for non-technical reader

**Story Points**: 1
**Priority**: P1

---

### P1-D02: Operations Runbook

**Summary**: Create `docs/operations-runbook.md` for solo dev ops.

**Description**:
Guide for daily operations: monitoring logs, restarting workers, handling failures, scaling queues. Assume single dev.

**Acceptance Criteria**:
- [ ] How to check queue health
- [ ] How to restart failed workers
- [ ] How to troubleshoot a stuck task
- [ ] How to scale workers for surge
- [ ] Common errors + solutions

**Story Points**: 2
**Priority**: P1

---

### P1-D03: Architecture Diagram

**Summary**: Create ASCII or Mermaid diagram showing request flow and queue architecture.

**Description**:
Visual showing: API -> controller -> queue -> job -> action -> result storage -> status/logs poll. Include queue names and state transitions.

**Acceptance Criteria**:
- [ ] Diagram is clear and complete
- [ ] Includes all queue names from `QueueEnum`
- [ ] Shows state transitions (queued -> processing -> completed)
- [ ] Suitable for README and onboarding

**Story Points**: 1
**Priority**: P1

---

### P2-D01: Action Contract Documentation

**Summary**: Document exact input/output format for each action.

**Description**:
For each action (scrape, summarize, classify, etc.), document: parameters, return type, error cases, example payload.

**Acceptance Criteria**:
- [ ] All 7+ actions documented
- [ ] Examples include success + failure
- [ ] Parameter types clearly specified
- [ ] Suitable for frontend/API consumer

**Story Points**: 1
**Priority**: P2

---

### P2-D02: Provider Setup Guide

**Summary**: Create `docs/provider-setup.md` with step-by-step keys/configs for OpenAI, Azure, etc.

**Description**:
Detailed instructions: where to get API keys, how to set env vars, how to test provider connectivity.

**Acceptance Criteria**:
- [ ] OpenAI setup covered
- [ ] Azure OpenAI setup covered
- [ ] Local LLM (Ollama) setup covered
- [ ] Test endpoint for each provider
- [ ] Troubleshooting section

**Story Points**: 2
**Priority**: P2

---

### P3-D01: Advanced Features Roadmap

**Summary**: Document intentionally deferred features and why.

**Description**:
Explain what's NOT in MVP and when it might be revisited. Set expectations for future phases.

**Acceptance Criteria**:
- [ ] RBAC/permissions deferred section
- [ ] Microservice split deferred section
- [ ] Cost dashboards deferred section
- [ ] Rationale for each deferral

**Story Points**: 1
**Priority**: P3

---

---

## Quality / Testing Tickets

### P1-Q01: Add Feature Tests for All API Endpoints

**Summary**: Write feature tests for 100% endpoint coverage.

**Description**:
Ensure all routes have feature tests: auth, task create, status, logs. Cover happy path + 3 common error cases per endpoint.

**Acceptance Criteria**:
- [ ] Feature test for each API route
- [ ] Test count >= 20
- [ ] All tests pass locally and in CI
- [ ] Minimum coverage target: 80% code coverage

**Story Points**: 5
**Priority**: P1

---

### P1-Q02: Add Unit Tests for Core Services

**Summary**: Test `AIServiceResolver`, `TaskPlannerService`, action classes in isolation.

**Description**:
Unit tests for service logic, no DB/queue required. Mock dependencies.

**Acceptance Criteria**:
- [ ] `AIServiceResolver` logic fully tested
- [ ] `TaskPlannerService` pipeline planning tested
- [ ] Each action class has unit tests
- [ ] Test count >= 15
- [ ] All pass locally

**Story Points**: 4
**Priority**: P1

---

### P2-Q01: Performance Benchmarks

**Summary**: Document and track task processing latency.

**Description**:
Create baseline for task creation -> completion time. Measure per action type. Log in CI after each commit.

**Acceptance Criteria**:
- [ ] Baseline benchmark documented
- [ ] Tracked per action (scrape, summarize, etc.)
- [ ] P95 latency target: 5s for simple actions
- [ ] Regression detected in CI if exceeded

**Story Points**: 3
**Priority**: P2

---

### P2-Q02: Load Testing Spike

**Summary**: Test how system handles 100 concurrent tasks in queue.

**Description**:
Use Apache Bench or similar to create task volume. Observe queue behavior, memory usage, latency.

**Acceptance Criteria**:
- [ ] Load test script created
- [ ] 100 concurrent tasks run without crash
- [ ] Report on memory, latency, throughput
- [ ] Identified bottlenecks documented

**Story Points**: 3
**Priority**: P2

---

---

## Recommended Sprint Order

### Sprint 1 (v0.3 Sprint, ~10 days)

1. P1-001 (Pint fixes)
2. P1-002 (Scrape URL)
3. P1-003 (Summarize)
4. P1-004 (Classify Intent)
5. P1-005 (Sentiment)
6. P1-012 (API Docs)
7. P1-Q01 (Feature Tests)

### Sprint 2 (v0.3 Sprint, ~10 days)

1. P1-006 (Generate Reply)
2. P1-007 (Save Result)
3. P1-008 (Ask AI)
4. P1-009 (URL Briefing E2E Test)
5. P1-010 (Named Pipelines)
6. P1-011 (Error Format)
7. P1-013 (Testing Guide)
8. P1-Q02 (Unit Tests)

### Sprint 3 (v0.4 Sprint, ~14 days)

1. P2-001 (Retry Logic)
2. P2-002 (Usage Metrics)
3. P2-005 (Support Triage Pipeline)
4. P2-007 (Horizon)
5. P2-008 (Supervisor Docs)
6. P2-012 (Task Results Table)
7. P2-D02 (Provider Setup Guide)

---

## Maintenance/Hygiene Tickets

### P3-M01: Dependency Updates Quarterly

**Summary**: Update composer dependencies quarterly.

**Description**:
Check for security updates and minor version bumps. Run tests after update. Document any breaking changes.

**Acceptance Criteria**:
- [ ] All dependencies checked for updates
- [ ] No CVEs present
- [ ] Tests pass after update
- [ ] composer.lock updated

**Story Points**: 2
**Priority**: P3

---

### P3-M02: Archive Old Branches

**Summary**: Clean up merged branches older than 1 week.

**Description**:
Remove feature branches that have been merged. Keep main and develop. Document branch strategy.

**Acceptance Criteria**:
- [ ] Stale branches deleted
- [ ] Strategy documented in CONTRIBUTING.md
- [ ] Automatic branch cleanup configured in GitHub

**Story Points**: 1
**Priority**: P3

---

---

## Notes for Sprint Planning

- **Dependencies**: Some tickets have hard dependencies (e.g., P1-002 requires pipeline config in P1-010).
- **Testing**: Every feature ticket should include feature test. Plan separately or bundle with feature.
- **Docs**: Docs tickets can run in parallel with feature development; allocate reviewer time.
- **Velocity**: Adjust story points per your velocity once you've completed 1-2 sprints.
- **Deferrals**: All P3 tickets are safe to defer if time runs out.

