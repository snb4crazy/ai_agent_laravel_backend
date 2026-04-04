# Multi-Step AI Agent Flow

Your proposed flow is **correct and aligns with real-world AI agent architecture**.

## Flow overview

```
User Input (prompt)
    ↓
1. Create Task (status: pending_planning)
    ↓
2. Planning Job: AI decides steps → creates task_steps records
    ↓
3. Execution Jobs: Loop through steps (pending → executing → completed/failed)
    ↓
4. Aggregate outputs into task.output_json
    ↓
5. User polls GET /api/v1/tasks/{id} and gets final output + step details
```

## Data model

### tasks (existing, updated)

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint | primary key |
| `public_id` | uuid | public identifier |
| `user_id` | bigint | foreign key |
| `type` | string | e.g. `multi_step_task` |
| `status` | string | `pending_planning` → `planning` → `executing` → `completed` / `failed` |
| `input_json` | json | user input / prompt |
| `output_json` | json | **NEW**: final aggregated output |
| `meta_json` | json | metadata |
| `started_at` | timestamp | when execution started |
| `finished_at` | timestamp | when completed/failed |
| `error_message` | text | top-level error if any |

### task_steps (new)

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint | primary key |
| `task_id` | bigint | foreign key to tasks |
| `action_name` | string | e.g. `analyze_sentiment`, `scrape_url` |
| `sequence_order` | int | order to execute (1, 2, 3...) |
| `input_json` | json | step-specific input |
| `output_json` | json | step result |
| `status` | string | `pending` → `executing` → `completed` / `failed` |
| `error_message` | text | step-level error |
| `started_at` | timestamp | when step started |
| `finished_at` | timestamp | when step completed |

## Models

### Task (updated)

```php
class Task extends Model {
    public function steps(): HasMany {
        return $this->hasMany(TaskStep::class)->orderBy('sequence_order');
    }
}
```

### TaskStep (new)

```php
class TaskStep extends Model {
    public function task(): BelongsTo {
        return $this->belongsTo(Task::class);
    }
}
```

## Queue job flow (implemented)

### Job 1: PlanTaskStepsJob

```php
// Given: task with status pending_planning
// 1. Transitions task status → planning
// 2. Calls TaskPlannerService to determine steps (AI-driven or rule-based fallback)
// 3. Creates TaskStep records in DB with action_name, sequence_order, input_json
// 4. Transitions task status → executing
// 5. Dispatches ExecuteTaskStepJob for the first pending step
```

### Job 2: ExecuteTaskStepJob

```php
// Given: task_step with status pending
// 1. Guards: skips if step is already completed/failed; skips if parent task failed
// 2. Updates status → executing, started_at = now()
// 3. Builds step input (merges task input + previous step output via previous_output key)
// 4. Calls TaskActionService->execute(step.action_name, input)
// 5. Saves output_json, updates status → completed, finished_at = now()
// 6. Dispatches next pending step, or CompileTaskOutputJob when all steps are done
// On error: step status → failed, parent task status → failed
```

### Job 3: CompileTaskOutputJob

```php
// Given: task with all steps completed
// 1. Aggregates all step outputs
// 2. Saves to task.output_json
// 3. Updates task status → completed
```

## API response (current)

```json
GET /api/v1/tasks/{id}
{
  "data": {
    "public_id": "...",
    "status": "completed",
    "input": {...},
    "output": {...},
    "steps": [
      {
        "action_name": "analyze_sentiment",
        "status": "completed",
        "input": {...},
        "output": {...}
      },
      {
        "action_name": "generate_reply",
        "status": "completed",
        "input": {...},
        "output": {...}
      }
    ]
  }
}
```

## Status lifecycle

### Task statuses

- `pending_planning` - waiting for AI to decide steps
- `planning` - AI is planning steps
- `executing` - steps are being executed
- `completed` - all steps done, output ready
- `failed` - error occurred

### Step statuses

- `pending` - waiting to execute
- `executing` - currently running
- `completed` - finished successfully
- `failed` - error occurred

