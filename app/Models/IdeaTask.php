<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IdeaTask extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['done' => 'boolean', 'done_at' => 'datetime', 'due_date' => 'date'];
    }

    public function idea(): BelongsTo
    {
        return $this->belongsTo(Idea::class);
    }

    public function isOverdue(): bool
    {
        return ! $this->done && $this->due_date && $this->due_date->isPast() && ! $this->due_date->isToday();
    }
}
