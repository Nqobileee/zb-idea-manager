<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['last_message_at' => 'datetime'];
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->oldest();
    }

    public function userA()
    {
        return $this->belongsTo(User::class, 'user_a');
    }

    public function userB()
    {
        return $this->belongsTo(User::class, 'user_b');
    }

    public function involves(User $user): bool
    {
        return $this->user_a === $user->id || $this->user_b === $user->id;
    }

    public function other(User $me): User
    {
        return $this->user_a === $me->id ? $this->userB : $this->userA;
    }

    /** Find or create the single conversation between two users. */
    public static function between(User $x, User $y): self
    {
        [$a, $b] = $x->id < $y->id ? [$x->id, $y->id] : [$y->id, $x->id];

        return static::firstOrCreate(['user_a' => $a, 'user_b' => $b]);
    }
}
