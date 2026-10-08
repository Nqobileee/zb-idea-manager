<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Challenge extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['keywords' => 'array', 'deadline' => 'date'];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function ideas(): HasMany
    {
        return $this->hasMany(Idea::class);
    }

    public function daysLeft(): ?int
    {
        return $this->deadline ? max(0, (int) now()->startOfDay()->diffInDays($this->deadline, false)) : null;
    }
}
