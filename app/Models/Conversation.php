<?php

namespace App\Models;

use App\Events\MessageSent;
use App\Support\Realtime;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class Conversation extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['last_message_at' => 'datetime', 'is_group' => 'boolean'];
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

    /** Named people in a team or project group. The all-staff group keeps no list: everyone is in it. */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_members');
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function idea()
    {
        return $this->belongsTo(Idea::class);
    }

    /** The group every signed-in member belongs to, as opposed to a team or project group. */
    public function isAllStaff(): bool
    {
        return $this->is_group && ! $this->scope;
    }

    public function involves(User $user): bool
    {
        if ($this->is_group) {
            return $this->isAllStaff() || $this->members()->where('users.id', $user->id)->exists();
        }

        return $this->user_a === $user->id || $this->user_b === $user->id;
    }

    /** Everything this person can open: their private chats, the all-staff group and the groups they were added to. */
    public function scopeVisibleTo($query, User $user)
    {
        return $query->where(function ($w) use ($user) {
            $w->where('user_a', $user->id)->orWhere('user_b', $user->id);
            if (static::hasGroupChat()) {
                $w->orWhere(function ($g) use ($user) {
                    $g->where('is_group', true);
                    if (Team::ready()) {
                        $g->where(fn ($x) => $x->whereNull('scope')->orWhereIn('id', DB::table('conversation_members')->where('user_id', $user->id)->select('conversation_id')));
                    }
                });
            }
        });
    }

    /** Save a message and tell whoever should see it. A private chat tells the other person, a team or project group tells its members. */
    public function post(User $from, string $body): Message
    {
        $msg = $this->messages()->create(['user_id' => $from->id, 'body' => mb_substr(trim($body), 0, 4000)]);
        $this->update(['last_message_at' => now()]);
        if (! $this->is_group) {
            Realtime::send(new MessageSent($msg, $this->other($from)->id));
        } elseif ($this->isAllStaff()) {
            Realtime::send(new MessageSent($msg, null));
        } else {
            $this->members()->where('users.id', '!=', $from->id)->pluck('users.id')->each(fn ($id) => Realtime::send(new MessageSent($msg, $id)));
        }

        return $msg;
    }

    /** Make a team or project group, with the given people in it. */
    public static function openGroup(string $scope, string $name, array $userIds, array $links): self
    {
        $group = static::create(['is_group' => true, 'scope' => $scope, 'name' => mb_substr($name, 0, 255)] + $links);
        $group->members()->syncWithoutDetaching($userIds);

        return $group;
    }

    /** The other person in a private conversation. A group has none. */
    public function other(User $me): ?User
    {
        return $this->user_a === $me->id ? $this->userB : $this->userA;
    }

    /** Find or create the single conversation between two users. */
    public static function between(User $x, User $y): self
    {
        [$a, $b] = $x->id < $y->id ? [$x->id, $y->id] : [$y->id, $x->id];

        return static::firstOrCreate(['user_a' => $a, 'user_b' => $b]);
    }

    /** False until the group chat migration has run. Until then chat stays one to one, with no ticks. */
    public static function hasGroupChat(): bool
    {
        static $has;

        return $has ??= Schema::hasColumn('conversations', 'is_group');
    }

    /** The one group chat that every signed-in member belongs to. */
    public static function everyone(): self
    {
        $q = static::where('is_group', true);
        if (Team::ready()) {
            $q->whereNull('scope'); // team and project groups are groups too, but not this one
        }

        return $q->first() ?? static::create(['is_group' => true, 'name' => 'All staff']);
    }

    /** Messages from other people that this user has not opened yet. */
    public function unreadFor(User $user): int
    {
        $q = Message::where('conversation_id', $this->id)->where('user_id', '!=', $user->id);

        return $this->is_group ? $q->where('id', '>', $this->readUpTo($user))->count() : $q->whereNull('read_at')->count();
    }

    public function markRead(User $user): void
    {
        if (! $this->is_group) {
            Message::where('conversation_id', $this->id)->where('user_id', '!=', $user->id)->whereNull('read_at')->update(['read_at' => now()]);

            return;
        }
        $latest = (int) Message::where('conversation_id', $this->id)->max('id');
        if ($latest > $this->readUpTo($user)) {
            DB::table('conversation_reads')->updateOrInsert(['conversation_id' => $this->id, 'user_id' => $user->id], ['last_message_id' => $latest]);
        }
    }

    /** Id of the last group message this user has seen. */
    private function readUpTo(User $user): int
    {
        return (int) DB::table('conversation_reads')->where(['conversation_id' => $this->id, 'user_id' => $user->id])->value('last_message_id');
    }
}
