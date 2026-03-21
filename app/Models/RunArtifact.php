<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RunArtifact extends Model
{
    use HasFactory;

    protected $fillable = [
        'agent_run_id',
        'type',
        'name',
        'content_json',
        'storage_disk',
        'storage_path',
        'mime_type',
        'size_bytes',
        'checksum',
    ];

    protected function casts(): array
    {
        return [
            'content_json' => 'array',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(AgentRun::class, 'agent_run_id');
    }
}

