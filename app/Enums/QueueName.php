<?php

namespace App\Enums;

enum QueueName: string
{
    case Task = 'task';
    case Service = 'service';
    case Webhook = 'webhook';
    case Maintenance = 'maintenance';

    public function queue(): string
    {
        return (string) config('queue.names.'.$this->value, $this->fallbackQueueName());
    }

    public function workerLabel(): string
    {
        return $this->value;
    }

    private function fallbackQueueName(): string
    {
        $prefix = (string) config('queue.prefix', 'ai-agent');

        return $prefix.':'.$this->value;
    }
}
