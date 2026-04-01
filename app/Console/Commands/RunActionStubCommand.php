<?php

namespace App\Console\Commands;

use App\Services\TaskActionService;
use Illuminate\Console\Command;

class RunActionStubCommand extends Command
{
    protected $signature = 'actions:run {action} {inputJson={}}';

    protected $aliases = ['actions:run-stub'];

    protected $description = 'Run a predefined action and print result as JSON.';

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
