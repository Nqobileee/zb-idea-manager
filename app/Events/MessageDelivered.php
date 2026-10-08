<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Tells a sender's open app that their messages reached someone, so the ticks turn green without a refresh. */
class MessageDelivered implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public int $senderId) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('users.'.$this->senderId)];
    }

    public function broadcastAs(): string
    {
        return 'MessageDelivered';
    }
}
