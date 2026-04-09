# Planning Documents Overview

Date: 2026-04-09

## Three complementary guides

This project now has three planning documents that work together. Here's how to use them:

### 1. **Deliverables** (`docs/deliverables.md`)

- **Purpose**: Define what "MVP done" means.
- **Scope**: 5 strategic deliverables (stable API, canonical path, actions, providers, ops readiness).
- **Audience**: You, stakeholders.
- **Use case**: Verify you're shipping the right thing. Check off as you complete each deliverable.

### 2. **Use Cases** (`docs/use-cases.md`)

- **Purpose**: Connect technical work to real customer value.
- **Scope**: 10 concrete scenarios (easy now + harder next).
- **Audience**: You, when scope drifts.
- **Use case**: Pick one use case when starting new work. Delivers end-to-end value quickly.

### 3. **Backlog** (`docs/backlog.md`)

- **Purpose**: Sprint-ready work tickets you can copy-paste into Jira/GitHub.
- **Scope**: 45+ tickets across v0.3, v0.4, v0.5+, plus docs and quality.
- **Audience**: You, for sprint planning.
- **Use case**: Organize backlog into sprints. Each ticket is Jira-importable.

---

## Workflow

### Weekly planning

1. **Monday**: Open `docs/deliverables.md`. Check which deliverable is in focus this week.
2. **Pick a use case**: From `docs/use-cases.md`, select one use case that advances that deliverable.
3. **Extract tickets**: From `docs/backlog.md`, find all P1 tickets tied to that use case.
4. **Create sprint**: Copy those tickets into Jira/GitHub. Estimate velocity, assign.
5. **Track**: Close tickets as you complete them. Mark use case as done when all related tests pass.

### Example: URL Briefing Use Case (Use Case 1)

**Which deliverable?** Deliverable 3 (action proof-of-concept pipeline).

**Related backlog tickets (Priority P1)**:

- P1-002: Implement Real Action: Scrape URL
- P1-003: Implement Real Action: Summarize Text
- P1-007: Implement Real Action: Save Result
- P1-010: Implement Named Pipelines in Config
- P1-009: End-to-End Pipeline Test: URL Briefing Flow
- P1-012: API Documentation: Endpoints + Examples
- P1-Q01: Add Feature Tests for All API Endpoints (partial)

**Estimated effort**: 14 story points (2-3 days for solo dev).

**Done when**:
- All related tickets closed.
- Feature test passes locally and in CI.
- API docs updated with `url_briefing` pipeline.
- Demo works: token → create task → poll → inspect logs.

---

## Document relationships

```
Deliverables (strategic)
    ↓
Use Cases (product value)
    ↓
Backlog (work items)
    ↓
Sprint (weekly execution)
```

---

## Quick reference: MVP v0.3 path

**Deliverable 1**: Stable API contract
- Use Case: All (foundation)
- Tickets: P1-001 through P1-013 + P1-Q01, P1-Q02

**Deliverable 2**: Canonical execution path
- Use Case: All (implied)
- Tickets: P1-010, P1-009, P1-012, others

**Deliverable 3**: Action proof-of-concept
- Use Case: 1, 2, 4 (Phase 1 easy cases)
- Tickets: P1-002 through P1-009

**Deliverable 4**: Provider routing
- Use Case: 10 (A/B testing)
- Tickets: P1-008 (ask_ai with routing), P2-002 (usage metrics)

**Deliverable 5**: Operational readiness
- Use Case: All (implicit)
- Tickets: P1-013, P2-007 (Horizon), P2-008 (Supervisor), P2-Q02 (performance)

---

## How to stay focused

When in doubt:

1. **Am I in a deliverable?** (Check `deliverables.md`)
2. **Does this advance a use case?** (Check `use-cases.md`)
3. **Is this a backlog ticket?** (Check `backlog.md`)

If all three are yes, do it.
If two are yes, it's probably good.
If one or zero, it's probably scope drift. Defer.

---

## Common questions

### "Should I skip use cases and just do backlog tickets?"

No. Use cases anchor tickets to customer value. A ticket without a use case is probably not MVP. Safe to defer.

### "What if I finish a sprint early?"

Pick the next highest-priority P1 ticket in `backlog.md`. Or pull a P2 ticket if it unblocks other work.

### "How do I know if a use case is really done?"

Check `docs/use-cases.md` → "Definition of done per use case":
- Authenticated request creates task
- Task runs through pipeline with visible state
- Output and logs retrievable via API
- Failure path returns standard error format
- Feature test covers happy path (+ ideally failure path)

### "Can I work on multiple use cases in one sprint?"

Not recommended for solo dev. Focus one at a time. Each use case is a coherent end-to-end demo.

---

## Notes

- This overview doc is intentionally short. Bookmark the three main docs above for reference.
- Update `backlog.md` as you learn priorities. Some tickets may move between P1/P2/P3.
- Archive completed tickets so backlog stays relevant.
- Revisit this overview every 2-3 sprints to recalibrate.

