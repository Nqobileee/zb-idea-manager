<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
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

    public function involves(User $user): bool
    {
        return $this->is_group || $this->user_a === $user->id || $this->user_b === $user->id;
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
        return static::firstOrCreate(['is_group' => true], ['name' => 'All staff']);
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
