# AI Control Plane Use Cases

Date: 2026-04-09

## Why this document exists

This file translates technical work into practical outcomes.
If you feel scope drift, pick one use case below and deliver it end-to-end.

## Product purpose in plain language

Turn raw user requests into reliable, traceable outcomes:

1. User sends authenticated API request.
2. Backend creates a task.
3. Queue workers execute action steps.
4. Frontend polls status and logs until done.

## How to use this doc

- Start with one use case from **Easy now**.
- Implement the smallest useful version first.
- Verify with: token -> create task -> poll status -> inspect logs.
- Only then move to **Harder next**.

---

## Easy to implement now (real-world, MVP-friendly)

These fit your current architecture with minimal new complexity.

### 1) URL Briefing for Busy Teams

- **Problem**: Team shares long articles; nobody has time to read all.
- **Actors**: Founder, PM, analyst.
- **Minimal input**: `url`.
- **Suggested actions**: `scrape_url -> summarize_text -> save_result`.
- **Expected output**: Short summary + key points.
- **Difficulty**: Easy.
- **Why it matters**: Immediate value, low risk, easy demo.

### 2) Support Ticket Intent Triage

- **Problem**: Incoming support messages are mixed (bug, billing, feature request).
- **Actors**: Support agent.
- **Minimal input**: `text` (ticket body).
- **Suggested actions**: `classify_intent -> analyze_sentiment -> save_result`.
- **Expected output**: Intent label + sentiment + routing hint.
- **Difficulty**: Easy.
- **Why it matters**: Saves manual triage time.

### 3) Quick Sentiment Monitor for Feedback

- **Problem**: Product feedback volume grows, mood is unclear.
- **Actors**: Product owner.
- **Minimal input**: `text` or list chunked into tasks.
- **Suggested actions**: `analyze_sentiment -> save_result`.
- **Expected output**: Sentiment classification with confidence notes.
- **Difficulty**: Easy.
- **Why it matters**: Fast signal for product health.

### 4) First-Draft Reply Assistant

- **Problem**: Writing repetitive replies takes too long.
- **Actors**: Support, customer success.
- **Minimal input**: `text` + optional context.
- **Suggested actions**: `classify_intent -> generate_reply -> save_result`.
- **Expected output**: Draft response for human review.
- **Difficulty**: Easy-Medium.
- **Why it matters**: Increases response speed without full automation risk.

### 5) Lead Qualification Notes

- **Problem**: Sales forms arrive unstructured.
- **Actors**: Sales rep.
- **Minimal input**: `text` (lead message).
- **Suggested actions**: `classify_intent -> ask_ai -> save_result`.
- **Expected output**: Lead quality summary + recommended next step.
- **Difficulty**: Easy-Medium.
- **Why it matters**: Better handoff from inbound forms to sales.

### 6) Incident Update Summarizer

- **Problem**: Incident channels are noisy; stakeholders want concise updates.
- **Actors**: On-call engineer, ops.
- **Minimal input**: `text` (incident notes).
- **Suggested actions**: `summarize_text -> generate_reply -> save_result`.
- **Expected output**: Status update draft in plain language.
- **Difficulty**: Easy-Medium.
- **Why it matters**: Reduces communication overhead during incidents.

---

## Harder but achievable next (after MVP stability)

These require more orchestration, but still fit your current direction.

### 7) Competitor or Topic Watch Digest

- **Problem**: Need regular summary from multiple URLs/sources.
- **Actors**: Founder, marketing.
- **Minimal input**: List of URLs + cadence.
- **Suggested actions**: Scheduled task -> repeated `scrape_url` -> `summarize_text` -> merge summary -> `save_result`.
- **Expected output**: Daily/weekly digest task output.
- **Difficulty**: Medium.
- **Why it matters**: Creates recurring product value, not only ad-hoc tasks.

### 8) Human Approval Gate Before Final Output

- **Problem**: AI output must be reviewed before external use.
- **Actors**: Reviewer, manager.
- **Minimal input**: Any generated draft.
- **Suggested actions**: Generate draft -> set task state `waiting_approval` -> continue pipeline on approval.
- **Expected output**: Auditable approval flow.
- **Difficulty**: Medium-Hard.
- **Why it matters**: Safer rollout for business-critical communications.

### 9) SLA Risk Escalation Helper

- **Problem**: Some tickets are likely to breach SLA but are missed.
- **Actors**: Support lead.
- **Minimal input**: Ticket text + metadata (priority, age).
- **Suggested actions**: `classify_intent -> analyze_sentiment -> ask_ai -> save_result` with escalation rule.
- **Expected output**: Risk label + suggested escalation note.
- **Difficulty**: Medium-Hard.
- **Why it matters**: Prevents customer dissatisfaction.

### 10) Provider Routing and A/B Quality Checks

- **Problem**: Need to compare providers/models for quality and cost.
- **Actors**: Solo developer/operator.
- **Minimal input**: Same prompt with provider override.
- **Suggested actions**: `ask_ai` with provider routing + usage/log metadata.
- **Expected output**: Measurable comparison by provider/model.
- **Difficulty**: Medium-Hard.
- **Why it matters**: Data-driven model selection as usage grows.

---

## Recommended implementation order (practical)

### Phase 1 (ship fast)

- Use cases: 1, 2, 4.
- Goal: prove end-to-end reliability and frontend integration.

### Phase 2 (stabilize operations)

- Use cases: 3, 5, 6, 7.
- Goal: improve repeated usage, scheduling, and observability.

### Phase 3 (scale decisions safely)

- Use cases: 8, 9, 10.
- Goal: approvals, escalation logic, and provider strategy.

---

## Definition of done per use case

A use case is done only when all are true:

- Authenticated request creates a task.
- Task runs through queue pipeline with visible state transitions.
- Output and logs are retrievable via API.
- Failure path returns standard error format and logs context.
- One feature test covers happy path (and ideally one failure path).

## Keep yourself focused

Before starting any new feature, ask:

1. Which use case does this support?
2. Does it improve reliability, observability, or user value?
3. Can I demo it with 2-3 API calls today?

If the answer is no, defer it to post-MVP backlog.

