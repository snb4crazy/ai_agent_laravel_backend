<?php

use App\Actions\Stubs\AnalyzeSentimentActionStub;
use App\Actions\Stubs\ClassifyIntentActionStub;
use App\Actions\Stubs\GenerateReplyActionStub;
use App\Actions\Stubs\SaveResultActionStub;
use App\Actions\Stubs\ScrapeUrlActionStub;
use App\Actions\Stubs\SummarizeTextActionStub;

return [
    'stubs' => [
        'scrape_url' => ScrapeUrlActionStub::class,
        'analyze_sentiment' => AnalyzeSentimentActionStub::class,
        'generate_reply' => GenerateReplyActionStub::class,
        'save_result' => SaveResultActionStub::class,
        'summarize_text' => SummarizeTextActionStub::class,
        'classify_intent' => ClassifyIntentActionStub::class,
    ],
];
