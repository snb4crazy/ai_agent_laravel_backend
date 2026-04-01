<?php

namespace Tests\Unit\Actions;

use App\Services\TaskActionService;
use Tests\TestCase;

class TaskActionServiceTest extends TestCase
{
    public function test_known_action_returns_real_result(): void
    {
        $service = app(TaskActionService::class);

        $result = $service->execute('analyze_sentiment', ['text' => 'This is great']);

        $this->assertTrue($result['executed']);
        $this->assertSame('analyze_sentiment', $result['action']);
        $this->assertSame('ok', $result['result']['status']);
        $this->assertSame('positive', $result['result']['label']);
    }

    public function test_unknown_action_is_skipped(): void
    {
        $service = app(TaskActionService::class);

        $result = $service->execute('unknown_action', ['x' => 1]);

        $this->assertFalse($result['executed']);
        $this->assertSame('unknown_action', $result['action']);
        $this->assertNull($result['result']);
    }
}
