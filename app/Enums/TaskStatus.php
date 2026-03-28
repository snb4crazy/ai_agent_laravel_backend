<?php

namespace App\Enums;

abstract class TaskStatus
{
    // Initial state: task created, waiting for planning job to decide steps
    public const PENDING_PLANNING = 'pending_planning';

    // Planning job is running: AI is selecting tools/actions
    public const PLANNING = 'planning';

    // Steps created, execution jobs are running
    public const EXECUTING = 'executing';

    // Legacy single-job queue state (used before multi-step flow)
    public const QUEUED = 'queued';

    // Legacy single-job in-progress state
    public const PROCESSING = 'processing';

    // All steps done, output_json populated
    public const COMPLETED = 'completed';

    // Any step or planning failed
    public const FAILED = 'failed';
}
