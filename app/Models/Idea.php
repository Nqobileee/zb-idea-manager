<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Idea extends Model
{
    public const STATUSES = ['Idea', 'Prototype', 'Demo', 'Pilot', 'Launched'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['approved' => 'boolean', 'approved_at' => 'datetime'];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function challenge(): BelongsTo
    {
        return $this->belongsTo(Challenge::class);
    }

    public function likers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'idea_likes');
    }

    public function savers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'idea_saves');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class)->oldest();
    }

    public function files(): HasMany
    {
        return $this->hasMany(IdeaFile::class);
    }

    public function docs(): HasMany
    {
        return $this->files()->where('kind', 'doc');
    }

    public function images(): HasMany
    {
        return $this->files()->where('kind', 'image');
    }

    /** The author can edit or delete their own idea; executives can too. */
    public function canBeManagedBy(?User $user): bool
    {
        return $user && ($user->id === $this->user_id || $user->is_admin);
    }

    public function getCodeAttribute(): string
    {
        return 'ZB-IDEA-'.str_pad((string) $this->num, 4, '0', STR_PAD_LEFT);
    }

    public function paragraphs(): array
    {
        return array_values(array_filter(preg_split('/\R{2,}|\R/', $this->body)));
    }

    public function stageIndex(): int
    {
        return (int) array_search($this->status, self::STATUSES, true);
    }

    public function isLikedBy(?User $user): bool
    {
        return $user && $this->likers->contains($user->id);
    }

    public function isSavedBy(?User $user): bool
    {
        return $user && $this->savers->contains($user->id);
    }

    public function scopeFeed($query)
    {
        return $query->with(['author', 'challenge', 'likers:id', 'savers:id', 'files'])->withCount(['comments', 'likers', 'docs as docs_count', 'images as images_count']);
    }

    public static function nextNumber(): int
    {
        return (int) static::max('num') + 1;
    }
}
