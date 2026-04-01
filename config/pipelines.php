<?php

return [
    /*
     * Default named pipeline used by POST /api/v1/tasks/run-pipeline when the
     * request does not provide a specific pipeline.
     */
    'default' => 'all_actions',

    /*
     * Named reusable pipeline definitions.
     *
     * Each pipeline is an ordered list of action names registered in
     * config/actions.php under actions.actions.
     */
    'definitions' => [
        'all_actions' => [
            'description' => 'Run every registered action in configured order.',
            'actions' => [
                'scrape_url',
                'analyze_sentiment',
                'generate_reply',
                'save_result',
                'summarize_text',
                'classify_intent',
                'ask_ai',
            ],
        ],
        'text_only' => [
            'description' => 'Text-focused pipeline without URL scraping.',
            'actions' => [
                'analyze_sentiment',
                'classify_intent',
                'generate_reply',
                'save_result',
            ],
        ],
        'ai_only' => [
            'description' => 'Minimal pipeline to call AI and persist result.',
            'actions' => [
                'ask_ai',
                'save_result',
            ],
        ],
    ],
];
