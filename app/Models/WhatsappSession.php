<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappSession extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['draft' => 'array', 'last_inbound_at' => 'datetime', 'locked_until' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
