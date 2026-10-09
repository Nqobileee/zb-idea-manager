<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public const MEMBER_TYPES = ['employee' => 'Employee', 'hub_member' => 'Hub member', 'general' => 'General'];

    protected $guarded = [];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'is_admin' => 'boolean',
            'whatsapp_opt_in' => 'boolean',
            'wa_greeted_on' => 'date',
            'must_change_password' => 'boolean',
        ];
    }

    public function ideas(): HasMany
    {
        return $this->hasMany(Idea::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    /** False until the member_type migration has run. */
    public static function hasMemberType(): bool
    {
        static $has;

        return $has ??= Schema::hasColumn('users', 'member_type');
    }

    /** Label shown under the name: Executive admin, or the member type picked at sign-in. */
    public function getRoleLabelAttribute(): string
    {
        return $this->is_admin ? 'Executive admin' : (self::MEMBER_TYPES[$this->attributes['member_type'] ?? ''] ?? 'Employee');
    }

    public function getInitialsAttribute(): string
    {
        return Str::of($this->name)->explode(' ')->filter()->map(fn ($w) => Str::upper(Str::substr($w, 0, 1)))->take(2)->implode('');
    }

    public function getFirstNameAttribute(): string
    {
        return Str::before($this->name, ' ');
    }

    public function getAvatarUrlAttribute(): ?string
    {
        return $this->avatar_path ? Storage::disk(config('ideas.upload_disk'))->url($this->avatar_path) : null;
    }

    public function unreadActivityCount(): int
    {
        return $this->activities()->whereNull('read_at')->count();
    }

    /** Conversations this user takes part in. */
    public function conversations()
    {
        return Conversation::where('user_a', $this->id)->orWhere('user_b', $this->id);
    }

    /** Create a display name from a work email: tinashe.moyo@zb.co.zw becomes Tinashe Moyo. */
    public static function nameFromEmail(string $email): string
    {
        $local = Str::before($email, '@');

        return collect(preg_split('/[._-]+/', $local))->filter()->map(fn ($w) => Str::ucfirst($w))->implode(' ') ?: 'ZB Employee';
    }
}
