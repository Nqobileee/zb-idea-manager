<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Accounts are keyed by WhatsApp number. Smile Factory's registration chatbot has already verified the person,
 * so the number is the identity: no email address or emailed code is asked for.
 */
class PhoneAccounts
{
    /** Domain for the stand-in email of accounts created from a number. Nothing is ever sent there. */
    public const PLACEHOLDER_DOMAIN = 'whatsapp.invalid';

    public function forPhone(string $phone, ?string $name = null): User
    {
        $user = User::where('phone', $phone)->first();
        if ($user) {
            return $user;
        }

        return User::create([
            'name' => trim((string) $name) ?: 'Member',
            'email' => $phone.'@'.self::PLACEHOLDER_DOMAIN,
            'phone' => $phone,
            'is_admin' => false,
            'member_type' => 'general',
            'joined' => (string) now()->year,
            'color' => '#049016',
        ]);
    }

    /** A one-use link, valid for 10 minutes, that signs the person in to the web app. Sent to them on WhatsApp. */
    public function loginLink(User $user): string
    {
        $token = Str::random(40);
        Cache::put('wa-login:'.$token, $user->id, now()->addMinutes(10));

        return route('wa.login', $token);
    }

    public function redeem(string $token): ?User
    {
        $id = Cache::pull('wa-login:'.$token);

        return $id ? User::find($id) : null;
    }

    public static function isPlaceholderEmail(string $email): bool
    {
        return str_ends_with($email, '@'.self::PLACEHOLDER_DOMAIN);
    }
}
