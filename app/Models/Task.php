<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Task extends Model
{
    use HasFactory;

    protected $fillable = [
        'public_id',
        'user_id',
        'type',
        'status',
        'priority',
        'idempotency_key',
        'input_json',
        'meta_json',
        'scheduled_for',
        'started_at',
        'finished_at',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'input_json' => 'array',
            'meta_json' => 'array',
            'scheduled_for' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function runs(): HasMany
    {
        return $this->hasMany(AgentRun::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(RunLog::class);
    }
}

