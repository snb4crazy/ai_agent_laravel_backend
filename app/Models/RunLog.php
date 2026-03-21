<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RunLog extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'task_id',
        'agent_run_id',
        'level',
        'event_type',
        'message',
        'context_json',
    ];

    protected function casts(): array
    {
        return [
            'context_json' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(AgentRun::class, 'agent_run_id');
    }
}

