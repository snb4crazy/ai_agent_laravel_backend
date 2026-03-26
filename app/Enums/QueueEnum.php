<?php

namespace App\Enums;

abstract class QueueEnum
{
    public const TASK = 'task';

    public const SERVICE = 'service';

    public const WEBHOOKS = 'webhooks';

    public const MAINTENANCE = 'maintenance';

    public const LOGS = 'logs';

    /**
     * All known queues in one place for worker/supervisor configuration.
     *
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [
            self::TASK,
            self::SERVICE,
            self::WEBHOOKS,
            self::MAINTENANCE,
            self::LOGS,
        ];
    }
}
