<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>{{ $appName }} Control Plane</title>
    <style>
        :root {
            color-scheme: light dark;
        }

        body {
            margin: 0;
            font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: #0b1020;
            color: #e5e7eb;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
        }

        .card {
            width: min(720px, 100%);
            background: rgba(17, 24, 39, 0.92);
            border: 1px solid #1f2937;
            border-radius: 16px;
            padding: 28px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.35);
        }

        h1 {
            margin: 0 0 8px;
            font-size: 28px;
            color: #f9fafb;
        }

        p {
            margin: 0 0 16px;
            color: #cbd5e1;
            line-height: 1.5;
        }

        .row {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 16px;
        }

        .pill {
            font-size: 13px;
            background: #111827;
            border: 1px solid #374151;
            border-radius: 999px;
            padding: 6px 12px;
            color: #d1d5db;
        }

        .actions {
            margin-top: 22px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        a.btn {
            text-decoration: none;
            font-weight: 600;
            border-radius: 10px;
            padding: 10px 14px;
            border: 1px solid #3b82f6;
            color: #dbeafe;
            background: #1d4ed8;
        }

        a.btn.secondary {
            border-color: #374151;
            background: #111827;
            color: #d1d5db;
        }
    </style>
</head>
<body>
<main class="card">
    <h1>{{ $appName }} AI Control Plane</h1>
    <p>
        Your Laravel backend is up and serving a UI stub. This page is intentionally minimal and can be replaced
        later by your real dashboard.
    </p>

    <div class="row">
        <span class="pill">Laravel {{ $appVersion }}</span>
        <span class="pill">Environment: {{ config('app.env') }}</span>
        <span class="pill">API auth: Sanctum</span>
    </div>

    <div class="actions">
        <a class="btn" href="/up">System Health</a>
        <a class="btn secondary" href="/">Refresh</a>
    </div>
</main>
</body>
</html>



