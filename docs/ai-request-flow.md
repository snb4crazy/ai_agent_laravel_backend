# AI Request Flow (Current State)

This document explains how the current backend flow works after the recent AI service integration.

## 1) Incoming API request flow (what happens today)

### Step 1: Route entry

- File: `routes/api.php`
- Protected endpoint: `POST /api/v1/tasks`
- Controller method: `TaskController@store`

### Step 2: Validation + persistence

- File: `app/Http/Controllers/Api/V1/TaskController.php`
- Request class validates payload via `StoreTaskDispatchRequest`
- A `tasks` row is created with:
  - `public_id`
  - `user_id`
  - `type`
  - `status = queued` (`TaskStatus::QUEUED`)
  - `input_json`, `meta_json`

### Step 3: Initial audit log row

- Same controller inserts into `run_logs`:
  - `event_type = task.accepted`
  - message: request accepted/persisted

### Step 4: Queue dispatch

- Controller dispatches `LogTaskRequestJob` on queue `QueueEnum::TASK`.
- Queue name source:
  - File: `app/Enums/QueueEnum.php`
  - `TASK` currently resolves to `task`

### Step 5: Immediate API response

- Controller returns `202 Accepted` with:
  - `status`
  - `task_public_id`
  - `dispatch_id`

## 2) Queue worker flow (what happens in job)

- File: `app/Jobs/LogTaskRequestJob.php`

### Lifecycle transitions

Inside `handle()`:

1. Load task by id.
2. Update task to `processing` (`TaskStatus::PROCESSING`).
3. Insert `run_logs` event `task.job_received`.
4. Execute predefined action stub if `task.type` matches configured action.
   - action result is stored in `meta_json.action_result`
   - action name is stored in `meta_json.action_name`
   - run log `task.action_executed` is inserted
5. Write app log (`Log::info(...)`).
6. Update task to `completed` (`TaskStatus::COMPLETED`) and set `finished_at`.

### Error path

If any exception happens:

- Best-effort update task to:
  - `status = failed` (`TaskStatus::FAILED`)
  - `error_message = exception message`
  - `finished_at = now()`
- Write error log (`Log::error(...)`)
- Re-throw exception so queue retry/failed-job behavior still works.

Status constants are centralized in:

- `app/Enums/TaskStatus.php`

## 3) New AI service layer flow

The AI adapter layer is implemented and ready, but not yet called from `TaskController`/job flow.

### Interface (app-level contract)

- File: `app/Services/AI/Contracts/AIServiceInterface.php`
- Defines:
  - `chat(...)`
  - `embeddings(...)`
  - `batch(...)`

### Provider implementations

- `app/Services/AI/AzureOpenAIService.php`
  - Uses Laravel HTTP client
  - Calls Azure endpoint format:
    - `/openai/deployments/{model}/chat/completions`
    - `/openai/deployments/{model}/embeddings`
    - `/openai/deployments/{model}/batch`
  - Adds query `api-version` and header `api-key`

- `app/Services/AI/OpenAIService.php`
  - Uses `openai-php/laravel` facade for chat and embeddings
  - Uses REST call for batches (`/v1/batches`)

## 4) How provider is selected

- File: `app/Providers/AppServiceProvider.php`
- DI binding resolves `AIServiceInterface` by config:
  - `services.ai.provider = openai` -> `OpenAIService`
  - default -> `AzureOpenAIService`

Config source:

- `config/services.php`
  - `ai.provider`
  - `openai.*`
  - `azure_openai.*`

Environment keys:

- `.env` / `.env.example`
  - `AI_PROVIDER`
  - `OPENAI_*`
  - `AZURE_OPENAI_*`

## 5) Where new AI code is used right now

Today, AI adapters are exercised through CLI example command:

- File: `app/Console/Commands/AiChatExampleCommand.php`
- Command:

```bash
php artisan ai:chat-example "Hello from AI Control Plane"
```

Optional model/deployment override:

```bash
php artisan ai:chat-example "Hello" --model=gpt-4o-mini
```

Binding behavior is covered by:

- `tests/Unit/Services/AI/AIServiceBindingTest.php`

## 6) Important note: current API does not call AI yet

Current `POST /api/v1/tasks` flow only persists task + logs + queue lifecycle updates.

It does **not** call `AIServiceInterface` yet.

Typical next integration point:

- Inject `AIServiceInterface` into a processing job (for example future `ProcessAgentRunJob`) and perform actual provider calls there.

