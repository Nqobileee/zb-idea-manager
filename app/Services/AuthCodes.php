<?php

namespace App\Services;

use App\Models\LoginCode;
use App\Support\Outbox;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/** Emails a 6-digit code to a ZB work address and checks it. Used by the web login and the WhatsApp link flow. */
class AuthCodes
{
    public function isWorkEmail(string $email): bool
    {
        $email = trim($email);
        $domain = config('ideas.email_domain');
        if (! $domain) {
            return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
        }

        return (bool) preg_match('/^[^@\s]+@'.preg_quote($domain, '/').'$/i', $email);
    }

    public function send(string $email): void
    {
        $email = strtolower(trim($email));
        LoginCode::where('email', $email)->delete();
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        LoginCode::create(['email' => $email, 'code_hash' => Hash::make($code), 'expires_at' => now()->addMinutes(config('ideas.code_ttl_minutes'))]);

        Outbox::send('Code', $email, config('mail.from.address'), 'Your ZB Idea Manager code',
            "Your 6-digit code is {$code}. It expires in ".config('ideas.code_ttl_minutes').' minutes. If you did not ask for it, ignore this email.');
        if (app()->environment('local')) {
            Log::info("ZB code for {$email}: {$code}");
        }
    }

    /** Returns true when the code is right. Counts failed attempts and burns the code after too many. */
    public function check(string $email, string $code): bool
    {
        $email = strtolower(trim($email));
        $code = trim($code);
        if (! preg_match('/^\d{6}$/', $code)) {
            return false;
        }
        if (config('ideas.accept_any_code')) {
            return true;
        }
        $row = LoginCode::where('email', $email)->latest()->first();
        if (! $row || $row->expires_at->isPast() || $row->attempts >= config('ideas.max_code_attempts')) {
            return false;
        }
        if (! Hash::check($code, $row->code_hash)) {
            $row->increment('attempts');

            return false;
        }
        $row->delete();

        return true;
    }

    /** Find the user for a verified email, creating the account on first sign-in when allowed. */
    public function userFor(string $email, bool $admin = false): ?User
    {
        $email = strtolower(trim($email));
        $user = User::where('email', $email)->first();
        if ($user || ! config('ideas.auto_provision')) {
            return $user;
        }

        return User::create([
            'name' => User::nameFromEmail($email), 'email' => $email, 'title' => $admin ? 'Executive, Digital Strategy' : 'Business Analyst',
            'dept' => 'Digital Banking', 'bio' => 'I like ideas that save a customer a trip to the branch.',
            'joined' => (string) now()->year, 'is_admin' => $admin, 'color' => '#0d4a36',
        ]);
    }
}
