<?php

namespace App\Models;

use App\Events\MessageDelivered;
use App\Support\Realtime;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['read_at' => 'datetime', 'delivered_at' => 'datetime'];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Called whenever this user's app loads or refreshes. Everything waiting for them (their private chats
     * and the group) is stamped as delivered, and each sender is told so their ticks turn green.
     */
    public static function deliverTo(User $user): void
    {
        if (! Conversation::hasGroupChat()) {
            return;
        }
        $mine = Conversation::visibleTo($user)->select('conversations.id');
        $waiting = static::whereNull('delivered_at')->where('user_id', '!=', $user->id)->whereIn('conversation_id', $mine);
        $senders = (clone $waiting)->distinct()->pluck('user_id');
        if ($senders->isEmpty()) {
            return;
        }
        $waiting->update(['delivered_at' => now()]);
        $senders->each(fn ($id) => Realtime::send(new MessageDelivered($id)));
    }
}
