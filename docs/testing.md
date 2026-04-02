AI action:
curl -X POST http://local.aiagent.com/api/v1/tasks \
-H "Authorization: Bearer YOUR_TOKEN" \
-H "Content-Type: application/json" \
-d '{
"type": "ask_ai_once",
"input": {
"prompt": "Give me one sentence about Laravel queues",
"provider": "openai"
}
}'

curl -H "Authorization: Bearer YOUR_TOKEN" \
http://local.aiagent.com/api/v1/tasks/TASK_PUBLIC_ID

cd project root and run:
php artisan actions:run ask_ai '{"prompt":"Say hello","provider":"ollama"}'

#Predefined pipeline tests:
# 1) Token
curl -X POST http://local.aiagent.com/api/v1/auth/token \
-H "Content-Type: application/json" \
-d '{"email":"admin@example.com","password":"secret","device_name":"cli-test"}'

# 2) Run all registered actions, skipping some
curl -X POST http://local.aiagent.com/api/v1/tasks/run-pipeline \
-H "Authorization: Bearer YOUR_ACCESS_TOKEN" \
-H "Content-Type: application/json" \
-d '{
"pipeline": "all_actions",
"input": {"prompt":"Hello","text":"This is great","url":"https://example.com"},
"skip_actions": ["save_result"],
"input_by_action": {
"analyze_sentiment": {"text":"This is great"},
"scrape_url": {"url":"https://example.com"}
}
}'

# 3) Run one action
curl -X POST http://local.aiagent.com/api/v1/tasks/run-action \
-H "Authorization: Bearer YOUR_ACCESS_TOKEN" \
-H "Content-Type: application/json" \
-d '{"action":"analyze_sentiment","input":{"text":"This is great"}}'

# Quick example (named pipeline):
curl -X POST http://local.aiagent.com/api/v1/tasks/run-pipeline \
-H "Authorization: Bearer YOUR_ACCESS_TOKEN" \
-H "Content-Type: application/json" \
-d '{
"pipeline": "text_only",
"input": {"prompt":"Need help with billing"},
"skip_actions": ["save_result"]
}'
