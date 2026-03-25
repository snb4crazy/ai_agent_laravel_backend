<?php

namespace App\Enums;

abstract class TaskStatus
{
    public const QUEUED = 'queued';

    public const PROCESSING = 'processing';

    public const COMPLETED = 'completed';

    public const FAILED = 'failed';
}
