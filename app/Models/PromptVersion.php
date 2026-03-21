<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PromptVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'prompt_template_id',
        'version',
        'content',
        'variables_schema',
        'is_active',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'variables_schema' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(PromptTemplate::class, 'prompt_template_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(AgentRun::class);
    }
}

