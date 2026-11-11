<?php

namespace App\Support;

use App\Models\SentEmail;
use Illuminate\Support\Facades\Mail;

/** Every email is saved to the email log and sent through the configured mailer (MAIL_MAILER). */
class Outbox
{
    public static function send(string $type, string $to, string $from, string $subject, string $body): SentEmail
    {
        $row = SentEmail::create(compact('type', 'to', 'from', 'subject', 'body'));
        if (\App\Services\PhoneAccounts::isPlaceholderEmail($to)) {
            return $row; // account created from a WhatsApp number: there is no mailbox
        }

        try {
            Mail::raw($body, function ($m) use ($to, $subject) {
                $m->to($to)->subject($subject);
            });
        } catch (\Throwable $e) {
            report($e); // a mail outage must not stop sign-in or approvals; the log row remains
        }

        return $row;
    }
}
