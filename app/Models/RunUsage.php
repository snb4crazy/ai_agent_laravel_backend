<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RunUsage extends Model
{
    use HasFactory;

    protected $table = 'run_usage';

    protected $fillable = [
        'agent_run_id',
        'input_tokens',
        'output_tokens',
        'total_tokens',
        'estimated_cost_usd',
        'latency_ms',
    ];

    protected function casts(): array
    {
        return [
            'estimated_cost_usd' => 'decimal:6',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(AgentRun::class, 'agent_run_id');
    }
}
