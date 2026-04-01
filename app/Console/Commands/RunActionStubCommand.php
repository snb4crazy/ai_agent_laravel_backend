<?php

namespace App\Console\Commands;

use App\Services\TaskActionService;
use Illuminate\Console\Command;

/**
 * Developer utility to run any registered action directly from the CLI.
 *
 * This is a TEST/DEBUG tool only.  In production the same actions are called
 * from within ExecuteTaskStepJob (the queue boundary).
 *
 * Usage:
 *   php artisan actions:run analyze_sentiment '{"text":"This is great"}'
 *   php artisan actions:run scrape_url '{"url":"https://example.com"}'
 */
class RunActionStubCommand extends Command
{
    protected $signature = 'actions:run {action} {inputJson={}}';

    /** @var array<int, string> */
    protected $aliases = ['actions:run-stub'];

    protected $description = 'Run a registered action and print the result as JSON. (dev/debug tool)';

    public function handle(TaskActionService $taskActionService): int
    {
        $action = (string) $this->argument('action');
        $inputJson = (string) $this->argument('inputJson');

        $input = json_decode($inputJson, true);

        if (! is_array($input)) {
            $this->error('inputJson must be a valid JSON object.');

            return self::FAILURE;
        }

        $result = $taskActionService->execute($action, $input);

        $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}
