<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IdeaUpdate extends Model
{
    protected $guarded = [];

    public function idea(): BelongsTo
    {
        return $this->belongsTo(Idea::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
