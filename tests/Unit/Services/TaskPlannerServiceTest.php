<?php

namespace Tests\Unit\Services;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use App\Services\TaskPlannerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TaskPlannerServiceTest extends TestCase
{
    use RefreshDatabase;

    private TaskPlannerService $planner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->planner = app(TaskPlannerService::class);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeTask(string $type, array $inputJson = []): Task
    {
        return Task::query()->create([
            'public_id' => (string) Str::uuid(),
            'user_id' => User::factory()->create()->id,
            'type' => $type,
            'status' => TaskStatus::PENDING_PLANNING,
            'input_json' => $inputJson,
        ]);
    }

    // -------------------------------------------------------------------------
    // multi_step_task
    // -------------------------------------------------------------------------

    public function test_plan_returns_three_ordered_steps_for_multi_step_task(): void
    {
        $task = $this->makeTask('multi_step_task', ['prompt' => 'Hello world']);
        $steps = $this->planner->plan($task);

        $this->assertCount(3, $steps);

        $names = array_column($steps, 'action_name');
        $this->assertSame(['analyze_sentiment', 'generate_reply', 'save_result'], $names);

        $orders = array_column($steps, 'sequence_order');
        $this->assertSame([1, 2, 3], $orders);
    }

    public function test_plan_injects_prompt_into_step_input_for_multi_step_task(): void
    {
        $task = $this->makeTask('multi_step_task', ['prompt' => 'Great news today']);
        $steps = $this->planner->plan($task);

        $this->assertSame('Great news today', $steps[0]['input_json']['text']);
        $this->assertSame('Great news today', $steps[1]['input_json']['text']);
    }

    // -------------------------------------------------------------------------
    // scrape_and_summarize
    // -------------------------------------------------------------------------

    public function test_plan_returns_two_steps_for_scrape_and_summarize(): void
    {
        $task = $this->makeTask('scrape_and_summarize', ['url' => 'https://example.com']);
        $steps = $this->planner->plan($task);

        $this->assertCount(2, $steps);
        $this->assertSame('scrape_url', $steps[0]['action_name']);
        $this->assertSame('summarize_text', $steps[1]['action_name']);
        $this->assertSame(1, $steps[0]['sequence_order']);
        $this->assertSame(2, $steps[1]['sequence_order']);
    }

    public function test_plan_injects_url_into_scrape_step_input(): void
    {
        $task = $this->makeTask('scrape_and_summarize', ['url' => 'https://test.io']);
        $steps = $this->planner->plan($task);

        $this->assertSame('https://test.io', $steps[0]['input_json']['url']);
    }

    // -------------------------------------------------------------------------
    // classify_and_reply
    // -------------------------------------------------------------------------

    public function test_plan_returns_two_steps_for_classify_and_reply(): void
    {
        $task = $this->makeTask('classify_and_reply', ['prompt' => 'Help me']);
        $steps = $this->planner->plan($task);

        $this->assertCount(2, $steps);
        $this->assertSame('classify_intent', $steps[0]['action_name']);
        $this->assertSame('generate_reply', $steps[1]['action_name']);
    }

    // -------------------------------------------------------------------------
    // Unknown task type
    // -------------------------------------------------------------------------

    public function test_plan_returns_single_save_result_step_for_unknown_type(): void
    {
        $task = $this->makeTask('unknown_custom_task', ['foo' => 'bar']);
        $steps = $this->planner->plan($task);

        $this->assertCount(1, $steps);
        $this->assertSame('save_result', $steps[0]['action_name']);
        $this->assertSame(1, $steps[0]['sequence_order']);
    }

    // -------------------------------------------------------------------------
    // Explicit steps in input_json
    // -------------------------------------------------------------------------

    public function test_plan_honours_explicit_steps_from_input_json(): void
    {
        $task = $this->makeTask('multi_step_task', [
            'prompt' => 'Hello',
            'steps' => [
                ['action_name' => 'analyze_sentiment', 'sequence_order' => 1, 'input_json' => ['text' => 'Hi']],
                ['action_name' => 'save_result',       'sequence_order' => 2, 'input_json' => ['key' => 'val']],
            ],
        ]);

        $steps = $this->planner->plan($task);

        $this->assertCount(2, $steps);
        $this->assertSame('analyze_sentiment', $steps[0]['action_name']);
        $this->assertSame('save_result', $steps[1]['action_name']);
        $this->assertSame(['text' => 'Hi'], $steps[0]['input_json']);
    }

    public function test_plan_normalises_out_of_order_explicit_steps(): void
    {
        $task = $this->makeTask('multi_step_task', [
            'steps' => [
                ['action_name' => 'save_result',       'sequence_order' => 3],
                ['action_name' => 'analyze_sentiment', 'sequence_order' => 1],
                ['action_name' => 'generate_reply',    'sequence_order' => 2],
            ],
        ]);

        $steps = $this->planner->plan($task);

        $this->assertCount(3, $steps);
        $this->assertSame('analyze_sentiment', $steps[0]['action_name']);
        $this->assertSame('generate_reply', $steps[1]['action_name']);
        $this->assertSame('save_result', $steps[2]['action_name']);
    }

    public function test_plan_fills_missing_action_name_with_save_result(): void
    {
        $task = $this->makeTask('multi_step_task', [
            'steps' => [
                ['sequence_order' => 1],
            ],
        ]);

        $steps = $this->planner->plan($task);

        $this->assertSame('save_result', $steps[0]['action_name']);
    }

    public function test_plan_assigns_sequential_order_when_step_order_is_missing(): void
    {
        $task = $this->makeTask('multi_step_task', [
            'steps' => [
                ['action_name' => 'analyze_sentiment'],
                ['action_name' => 'generate_reply'],
            ],
        ]);

        $steps = $this->planner->plan($task);

        $this->assertSame(1, $steps[0]['sequence_order']);
        $this->assertSame(2, $steps[1]['sequence_order']);
    }

    public function test_plan_falls_back_to_task_input_when_step_has_no_input_json(): void
    {
        $taskInput = ['prompt' => 'Test input'];
        $task = $this->makeTask('multi_step_task', array_merge($taskInput, [
            'steps' => [
                ['action_name' => 'analyze_sentiment', 'sequence_order' => 1],
            ],
        ]));

        $steps = $this->planner->plan($task);

        // No explicit input_json → falls back to entire task input
        $this->assertArrayHasKey('prompt', $steps[0]['input_json']);
        $this->assertSame('Test input', $steps[0]['input_json']['prompt']);
    }
}
