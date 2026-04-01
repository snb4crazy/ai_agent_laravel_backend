<?php

use App\Actions\AnalyzeSentimentAction;
use App\Actions\AskAiAction;
use App\Actions\ClassifyIntentAction;
use App\Actions\GenerateReplyAction;
use App\Actions\SaveResultAction;
use App\Actions\ScrapeUrlAction;
use App\Actions\SummarizeTextAction;

return [
    /*
     * Map of action name → action class.
     *
     * Each class implements ActionInterface and is resolved via the service
     * container. Actions are called synchronously from within ExecuteTaskStepJob,
     * which itself runs in a queue — so each action is an atomic unit of work.
     *
     * To add a new action:
     *   1. Create app/Actions/MyNewAction.php implementing ActionInterface.
     *   2. Add an entry below.
     *   3. Reference the key in TaskPlannerService::defaultPlan() or supply
     *      steps explicitly in the task input_json.
     */
    'actions' => [
        'scrape_url' => ScrapeUrlAction::class,
        'analyze_sentiment' => AnalyzeSentimentAction::class,
        'generate_reply' => GenerateReplyAction::class,
        'save_result' => SaveResultAction::class,
        'summarize_text' => SummarizeTextAction::class,
        'classify_intent' => ClassifyIntentAction::class,
        'ask_ai' => AskAiAction::class,
    ],
];
