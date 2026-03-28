<?php

namespace App\Enums;

abstract class TaskStepStatus
{
    public const PENDING = 'pending';

    public const EXECUTING = 'executing';

    public const COMPLETED = 'completed';

    public const FAILED = 'failed';
}
