<?php

use App\Models\Conversation;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('users.{id}', fn ($user, $id) => (int) $user->id === (int) $id);

Broadcast::channel('conversations.{id}', function ($user, $id) {
    $c = Conversation::find($id);

    return $c && $c->involves($user);
});
