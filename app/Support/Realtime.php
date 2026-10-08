<?php

namespace App\Support;

/** Send a broadcast event, but never let a stopped Reverb server break the request. */
class Realtime
{
    public static function send(object $event): void
    {
        try {
            broadcast($event)->toOthers();
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
