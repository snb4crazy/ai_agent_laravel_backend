<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OutboxEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'public_id',
        'event_name',
        'aggregate_type',
        'aggregate_id',
        'payload_json',
        'status',
        'idempotency_key',
        'attempts',
        'available_at',
        'published_at',
        'failed_at',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'payload_json' => 'array',
            'available_at' => 'datetime',
            'published_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }
}
