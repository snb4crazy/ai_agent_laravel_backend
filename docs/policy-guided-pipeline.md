# Policy-Guided Pipeline

## What was implemented

A new deterministic pipeline that differs from the generic `run-pipeline` endpoint by **loading a rule/policy context first**, then making that context available to every action step. This lets you later add enforcement logic (e.g. "if RULE_NO_PII is active, strip PII from output") without touching the generic pipeline code.

---

## New files

| File | Purpose |
|------|---------|
| `app/Actions/LoadPoliciesAction.php` | Stub policy loader – returns a flag + rule set |
| `app/Http/Requests/Api/V1/RunPolicyGuidedPipelineRequest.php` | Request validation for the new endpoint |
| `app/Http/Controllers/Api/V1/TaskController.php` | `runPolicyGuidedPipeline()` method added |
| `routes/api.php` | `POST /api/v1/tasks/run-policy-pipeline` route added |
| `config/actions.php` | `load_policies` action registered |

---

## Endpoint

```
POST /api/v1/tasks/run-policy-pipeline
Authorization: Bearer {token}
Content-Type: application/json
```

### Request body

```json
{
  "input": {
    "text": "I love this product!"
  },
  "actions": ["analyze_sentiment", "generate_reply"],
  "input_by_action": {
    "generate_reply": { "tone": "professional" }
  },
  "meta": {
    "caller": "test-script"
  }
}
```

| Field | Required | Default | Description |
|-------|----------|---------|-------------|
| `input` | no | `{}` | Base input merged into every step |
| `actions` | no | `["analyze_sentiment", "generate_reply"]` | Exactly **2** action names to run |
| `input_by_action` | no | `{}` | Per-action input overrides (merged on top of `input`) |
| `meta` | no | `{}` | Arbitrary metadata stored in `task.meta_json` |

### Response (202 Accepted)

```json
{
  "status": "pending_planning",
  "task_public_id": "uuid",
  "dispatch_id": "uuid",
  "links": {
    "status": "https://host/api/v1/tasks/{uuid}",
    "logs": "https://host/api/v1/tasks/{uuid}/logs"
  },
  "pipeline": {
    "name": "policy_guided",
    "actions": ["analyze_sentiment", "generate_reply"],
    "policy_flag": "POLICY_STUB_V1",
    "active_rules": [
      { "id": "RULE_TONE", "description": "...", "enabled": true },
      { "id": "RULE_MAX_LENGTH", "description": "...", "enabled": true, "params": { "max_words": 500 } },
      { "id": "RULE_NO_PII", "description": "...", "enabled": true }
    ]
  }
}
```

---

## Execution flow

```
POST /api/v1/tasks/run-policy-pipeline
        │
        ▼
TaskController::runPolicyGuidedPipeline()
  1. LoadPoliciesAction::handle()   ← runs synchronously in-process
     returns: { flag, rules[], meta }
  2. Build 2 TaskStep definitions
     each step.input_json = base_input + per_action_overrides + policy_context
  3. Task created (status: pending_planning)
     task.meta_json stores: pipeline_name, policy_flag, policy_version, policy_loaded_at
  4. PlanTaskStepsJob dispatched → queue: ai-agent:service
        │
        ▼
PlanTaskStepsJob
  - creates TaskStep records from input_json.steps
  - dispatches ExecuteTaskStepJob for step 1 → queue: ai-agent:task
        │
        ▼
ExecuteTaskStepJob (step 1)
  - input = step.input_json  (includes policy_context)
  - runs action (e.g. analyze_sentiment)
  - saves output_json
  - dispatches ExecuteTaskStepJob for step 2
        │
        ▼
ExecuteTaskStepJob (step 2)
  - input = step.input_json  (includes policy_context)
  - ALSO receives previous_output = step 1 output  ← injected by buildStepInput()
  - runs action (e.g. generate_reply)
  - saves output_json
  - dispatches CompileTaskOutputJob
        │
        ▼
CompileTaskOutputJob
  - assembles task.output_json from all step outputs
  - sets task.status = completed
```

---

## What `policy_context` looks like inside a step

When your action receives its `$input` array, it contains:

```php
$input = [
    // caller's base input
    'text' => 'I love this product!',

    // step 2 only: output from step 1
    'previous_output' => [
        'sentiment' => 'positive',
        'score' => 0.92,
    ],

    // always present on policy-guided pipeline
    'policy_context' => [
        'flag' => 'POLICY_STUB_V1',
        'loaded_at' => '2026-04-18T12:00:00+00:00',
        'rules' => [
            ['id' => 'RULE_TONE',       'enabled' => true,  'description' => '...'],
            ['id' => 'RULE_MAX_LENGTH',  'enabled' => true,  'params' => ['max_words' => 500]],
            ['id' => 'RULE_NO_PII',      'enabled' => true,  'description' => '...'],
            ['id' => 'RULE_LANGUAGE',    'enabled' => false, 'description' => '...'],
        ],
        'meta' => ['source' => 'stub', 'version' => '0.1.0'],
    ],
];
```

---

## How to compare with the generic pipeline

Run both endpoints with the same input, then poll the status URLs and compare `task.output_json` and `steps[*].input`:

```bash
# 1. Get token
TOKEN=$(curl -s -X POST http://local.aiagent.com/api/v1/auth/token \
  -H 'Content-Type: application/json' \
  -d '{"email":"you@example.com","password":"secret","device_name":"cli"}' \
  | jq -r '.token')

# 2a. Generic pipeline (no policies)
curl -s -X POST http://local.aiagent.com/api/v1/tasks/run-pipeline \
  -H "Authorization: Bearer $TOKEN" \
  -H 'Content-Type: application/json' \
  -d '{"pipeline":"text_only","input":{"text":"I love this product!"}}' | jq .

# 2b. Policy-guided pipeline
curl -s -X POST http://local.aiagent.com/api/v1/tasks/run-policy-pipeline \
  -H "Authorization: Bearer $TOKEN" \
  -H 'Content-Type: application/json' \
  -d '{"input":{"text":"I love this product!"}}' | jq .

# 3. Poll status (replace UUID)
curl -s http://local.aiagent.com/api/v1/tasks/{uuid} \
  -H "Authorization: Bearer $TOKEN" | jq '.data.steps[].input.policy_context.flag'
```

The policy-guided task will show `"POLICY_STUB_V1"` in each step's input; the generic pipeline will not.

---

## Current stub – `LoadPoliciesAction`

```
app/Actions/LoadPoliciesAction.php
```

The stub currently returns hard-coded data. It is intentionally the simplest possible implementation. The `// TODO` comment marks where to swap in a real source.

Active rules (enabled by default):

| Rule ID | Description |
|---------|-------------|
| `RULE_TONE` | All generated replies must use a professional, neutral tone |
| `RULE_MAX_LENGTH` | Output must not exceed 500 words (`params.max_words`) |
| `RULE_NO_PII` | Do not include PII in the output |

Disabled by default:

| Rule ID | Description |
|---------|-------------|
| `RULE_LANGUAGE` | Respond in the same language as the input |

---

## How to proceed (proposals)

These are ordered from easiest to most impactful. Pick any one as the next sprint ticket.

### 1 — Make policies readable from a config file *(easy, ~30 min)*

Replace the hard-coded array in `LoadPoliciesAction` with a YAML or PHP config file (`config/policies.php`).  
No DB, no migration. Just externalise the rules so you can edit them without touching PHP.

```php
// config/policies.php
return [
    'flag'  => 'POLICY_CONFIG_V1',
    'rules' => [
        ['id' => 'RULE_TONE', 'enabled' => true, ...],
    ],
];

// LoadPoliciesAction::handle()
return config('policies');
```

### 2 — Store policies in a DB table *(medium, ~2 h)*

Create a `policies` migration with columns:  
`id, name, flag, version, rules_json (JSON), is_active, created_at, updated_at`

`LoadPoliciesAction` then queries the active policy row. This lets you change rules at runtime via a future admin UI or API endpoint without re-deploying code.

```php
$policy = Policy::query()->where('is_active', true)->firstOrFail();
return $policy->toArray();
```

### 3 — Add rule enforcement inside existing actions *(medium, ~2–3 h per action)*

Each action already receives `$input['policy_context']`. Add enforcement logic:

```php
// In GenerateReplyAction::handle()
$rules = collect($input['policy_context']['rules'] ?? [])
    ->where('enabled', true)
    ->keyBy('id');

if ($rules->has('RULE_MAX_LENGTH')) {
    $maxWords = $rules->get('RULE_MAX_LENGTH')['params']['max_words'] ?? 500;
    // truncate or pass as constraint to AI prompt
}
```

### 4 — Add a `PolicyEnforcerAction` step *(medium, ~2 h)*

Insert a third step **after** the two main actions. Its job: read `previous_output` + `policy_context`, validate the output against active rules, and either pass it through or flag a violation.

```json
{ "action_name": "enforce_policy", "sequence_order": 3 }
```

This keeps enforcement logic out of individual actions and makes it composable.

### 5 — Policy versioning *(medium, ~3 h)*

Add a `policy_version` column to `tasks` (or use the existing `meta_json.policy_version`).  
This lets you replay old tasks with the same policy that was active when they ran — useful for auditing and debugging.

### 6 — A/B compare endpoint *(advanced, ~4 h)*

New endpoint `POST /api/v1/tasks/compare` that dispatches **both** a generic pipeline task and a policy-guided task from the same input, returns both `task_public_id` values, and lets you poll both in parallel for a side-by-side diff.

### 7 — Admin API to manage policies *(advanced, requires step 2)*

```
GET    /api/v1/admin/policies
POST   /api/v1/admin/policies
PATCH  /api/v1/admin/policies/{id}
POST   /api/v1/admin/policies/{id}/activate
```

Requires role-based access control (the `spatie/laravel-permission` package is already installed).

---

## Quick reference – all task endpoints

| Method | URL | Description |
|--------|-----|-------------|
| `POST` | `/api/v1/tasks` | Single-step or multi-step task |
| `POST` | `/api/v1/tasks/run-pipeline` | Named pipeline from `config/pipelines.php` |
| `POST` | `/api/v1/tasks/run-action` | Single action via pipeline |
| `POST` | `/api/v1/tasks/run-policy-pipeline` | **Policy-guided pipeline (new)** |
| `GET`  | `/api/v1/tasks/{public_id}` | Poll task status + step outputs |
| `GET`  | `/api/v1/tasks/{public_id}/logs` | Full run log for a task |

