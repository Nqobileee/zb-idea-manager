<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

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

    /** Members tagged on the project. */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'idea_members')->withTimestamps();
    }

    /** The author and executives manage the post; tagged members also work on the project itself. */
    public function canContribute(?User $user): bool
    {
        return $user && ($this->canBeManagedBy($user) || $this->members->contains('id', $user->id));
    }

    public function updates(): HasMany
    {
        return $this->hasMany(IdeaUpdate::class)->latest();
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(IdeaTask::class)->orderBy('done')->orderBy('id');
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

    /** Tailwind colour classes for a stage. The same colours are used on post tags and filter chips. */
    public static function stageClasses(string $status): string
    {
        return [
            'Idea' => 'bg-surface text-muted',
            'Prototype' => 'bg-[#fbeed3] text-[#8a5a00]',
            'Demo' => 'bg-[#e0ebf8] text-[#2a5a99]',
            'Pilot' => 'bg-[#ece5f7] text-[#5b3f9e]',
            'Launched' => 'bg-brand text-white',
        ][$status] ?? 'bg-surface text-muted';
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

    /** False until the visibility migration has run; until then everything stays visible so the app keeps working. */
    public static function hasVisibility(): bool
    {
        static $has;

        return $has ??= Schema::hasColumn('ideas', 'visibility');
    }

    /** Executives see every idea. Everyone else sees public ideas, their own, and projects they are tagged on. */
    public function scopeVisibleTo($query, ?User $user)
    {
        if (! static::hasVisibility() || $user?->is_admin) {
            return $query;
        }

        return $query->where(function ($w) use ($user) {
            $w->where('ideas.visibility', 'public');
            if ($user) {
                $w->orWhere('ideas.user_id', $user->id)->orWhereHas('members', fn ($m) => $m->where('users.id', $user->id));
            }
        });
    }

    public function isVisibleTo(?User $user): bool
    {
        if (! static::hasVisibility() || $user?->is_admin || ($this->attributes['visibility'] ?? 'private') === 'public') {
            return true;
        }

        return $user && ($user->id === $this->user_id || $this->members()->where('users.id', $user->id)->exists());
    }

    public function getIsPublicAttribute(): bool
    {
        return ($this->attributes['visibility'] ?? 'private') === 'public';
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
