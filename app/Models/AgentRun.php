<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AgentRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'public_id',
        'task_id',
        'prompt_version_id',
        'retry_of_run_id',
        'run_number',
        'status',
        'provider',
        'model',
        'deployment',
        'queue',
        'azure_request_id',
        'request_payload',
        'response_payload',
        'error_code',
        'error_message',
        'latency_ms',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'request_payload' => 'array',
            'response_payload' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function promptVersion(): BelongsTo
    {
        return $this->belongsTo(PromptVersion::class);
    }

    public function retryOfRun(): BelongsTo
    {
        return $this->belongsTo(self::class, 'retry_of_run_id');
    }

    public function retries(): HasMany
    {
        return $this->hasMany(self::class, 'retry_of_run_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(RunLog::class);
    }

    public function usage(): HasOne
    {
        return $this->hasOne(RunUsage::class);
    }

    public function artifacts(): HasMany
    {
        return $this->hasMany(RunArtifact::class);
    }
}

