<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Schema;

class Team extends Model
{
    protected $guarded = [];

    /** False until the teams migration has run. Until then the Teams page explains it and nothing else changes. */
    public static function ready(): bool
    {
        return self::$ready ??= Schema::hasTable('teams') && Schema::hasColumn('conversations', 'scope');
    }

    private static ?bool $ready = null;

    /** Forget the cached answer. Tests use this after adding or removing the tables. */
    public static function forgetReady(): void
    {
        self::$ready = null;
    }

    public function leader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'team_user')->withTimestamps()->orderBy('users.name');
    }

    public function ideas(): HasMany
    {
        return $this->hasMany(Idea::class);
    }

    /** The team's own group chat, made when the team was created. */
    public function groupChat(): HasOne
    {
        return $this->hasOne(Conversation::class)->where('scope', 'team');
    }

    public function hasMember(?User $user): bool
    {
        return $user && ($this->relationLoaded('members') ? $this->members->contains('id', $user->id) : $this->members()->where('users.id', $user->id)->exists());
    }

    /** The leader and executives can rename the team, add and remove people and delete it. */
    public function canBeManagedBy(?User $user): bool
    {
        return $user && ($user->id === $this->user_id || $user->is_admin);
    }
}
