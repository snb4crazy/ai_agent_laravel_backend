# API Endpoints (Auth + Task Dispatch)

Date: 2026-03-20

This document describes the current test API flow:

1. Create a user from CLI (registration route is disabled).
2. Authenticate and receive a Sanctum bearer token.
3. Call the protected task dispatch endpoint with that token.
4. Poll the task status endpoint with the returned `task_public_id`.
5. Optionally fetch task logs.

For now:

- the controller persists the task immediately,
- the controller writes an initial task log,
- the queue job only appends another log entry using the already persisted task payload,
- no AI processing is implemented yet.

## Base URL

- Local: `http://localhost:8000`
- API prefix: `/api/v1`

## User Provisioning (CLI)

Public registration is disabled (`POST /register` is not available).
Create users from terminal with:

```bash
php artisan user:create
```

The command asks for all mandatory fields:

- `Name`
- `Email`
- `Password`
- `Confirm password`

## Endpoint: Issue API Token

- Method: `POST`
- URL: `/api/v1/auth/token`
- Auth: none

### Request body

```json
{
  "email": "john@example.com",
  "password": "password",
  "device_name": "postman"
}
```

### Success response (`200`)

```json
{
  "token_type": "Bearer",
  "access_token": "<SANCTUM_TOKEN>"
}
```

## Authentication Errors (`401`) for Protected Endpoints

All protected endpoints (`POST /tasks`, `GET /tasks/{task_public_id}`, `GET /tasks/{task_public_id}/logs`)
return `401` when authentication fails.

### Missing / invalid / revoked token

Returned when no bearer token is sent, the token format is invalid, or the token is not found.

```json
{
  "error": {
    "code": "UNAUTHENTICATED",
    "message": "Unauthenticated."
  }
}
```

### Expired token

Returned when a bearer token exists but is expired.

```json
{
  "error": {
    "code": "TOKEN_EXPIRED",
    "message": "Your session has expired. Please log in again.",
    "action": "relogin"
  }
}
```

Frontend handling recommendation:

- If `error.code === "TOKEN_EXPIRED"` or `error.action === "relogin"`: clear local auth state and redirect to login.
- If `error.code === "UNAUTHENTICATED"`: treat as unauthorized and redirect to login.
- For all other API errors: keep standard error handling flow.

Note: `TOKEN_EXPIRED` is returned when token expiration is configured and the token has passed its expiry time.

## Endpoint: Dispatch Task (queued)

- Method: `POST`
- URL: `/api/v1/tasks`
- Auth: `Authorization: Bearer <SANCTUM_TOKEN>`

### Request body

```json
{
  "type": "chat.completion",
  "input": {
    "prompt": "Summarize this text"
  },
  "meta": {
    "source": "frontend"
  }
}
```

### Success response (`202`)

```json
{
  "status": "queued",
  "task_public_id": "6f4d8b77-8f8c-4f06-8ba1-3e2f1b4db3d8",
  "dispatch_id": "6f4d8b77-8f8c-4f06-8ba1-3e2f1b4db3d8"
}
```

Notes:

- `task_public_id` is the stable identifier the frontend should store and poll.
- `dispatch_id` currently mirrors `task_public_id` for backward compatibility.

## Endpoint: Get Task Status

- Method: `GET`
- URL: `/api/v1/tasks/{task_public_id}`
- Auth: `Authorization: Bearer <SANCTUM_TOKEN>`

### Success response (`200`)

```json
{
  "data": {
    "public_id": "6f4d8b77-8f8c-4f06-8ba1-3e2f1b4db3d8",
    "type": "chat.completion",
    "status": "queued",
    "priority": 5,
    "input": {
      "prompt": "Summarize this text"
    },
    "meta": {
      "source": "frontend"
    },
    "error_message": null,
    "created_at": "2026-03-20T12:00:00+00:00",
    "updated_at": "2026-03-20T12:00:00+00:00",
    "started_at": null,
    "finished_at": null
  }
}
```

## Endpoint: Get Task Logs

- Method: `GET`
- URL: `/api/v1/tasks/{task_public_id}/logs`
- Auth: `Authorization: Bearer <SANCTUM_TOKEN>`

### Success response (`200`)

```json
{
  "data": [
    {
      "id": 1,
      "level": "info",
      "event_type": "task.accepted",
      "message": "Task request accepted and persisted",
      "context": {
        "task_public_id": "6f4d8b77-8f8c-4f06-8ba1-3e2f1b4db3d8",
        "user_id": 1,
        "input": {
          "type": "chat.completion",
          "input": {
            "prompt": "Summarize this text"
          },
          "meta": {
            "source": "frontend"
          }
        }
      },
      "created_at": "2026-03-20T12:00:00+00:00"
    },
    {
      "id": 2,
      "level": "info",
      "event_type": "task.job_received",
      "message": "Queue job received persisted task payload",
      "context": {
        "task_public_id": "6f4d8b77-8f8c-4f06-8ba1-3e2f1b4db3d8",
        "user_id": 1,
        "input": {
          "type": "chat.completion",
          "input": {
            "prompt": "Summarize this text"
          },
          "meta": {
            "source": "frontend"
          }
        }
      },
      "created_at": "2026-03-20T12:00:01+00:00"
    }
  ]
}
```

### Validation errors (`422`)

Returned when payload does not satisfy:

- `type` required, string, max 100 chars
- `input` required, object/array, at least 1 key
- `meta` optional, object/array

## cURL Examples

### 1) Create user from CLI

```bash
php artisan user:create
```

### 2) Authenticate

```bash
curl -sS -X POST "http://localhost:8000/api/v1/auth/token" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "john@example.com",
    "password": "password",
    "device_name": "curl"
  }'
```

### 3) Dispatch a task

```bash
TOKEN="<PASTE_TOKEN_HERE>"

curl -sS -X POST "http://localhost:8000/api/v1/tasks" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer ${TOKEN}" \
  -d '{
    "type": "chat.completion",
    "input": {
      "prompt": "Hello from frontend"
    },
    "meta": {
      "source": "curl"
    }
  }'
```

### 4) Check task status

```bash
TASK_ID="<PASTE_TASK_PUBLIC_ID_HERE>"

curl -sS "http://localhost:8000/api/v1/tasks/${TASK_ID}" \
  -H "Authorization: Bearer ${TOKEN}"
```

### 5) Fetch task logs

```bash
curl -sS "http://localhost:8000/api/v1/tasks/${TASK_ID}/logs" \
  -H "Authorization: Bearer ${TOKEN}"
```

## Current behavior notes

- Controller persists the task before the queue is dispatched.
- Frontend should poll by `task_public_id`, not by queue job name.
- Job `LogTaskRequestJob` is dispatched to queue `ai` using the persisted task ID.
- Job appends a second log entry when it executes.
- No AI processing is implemented yet.

