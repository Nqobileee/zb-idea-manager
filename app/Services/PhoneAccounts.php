<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Accounts are keyed by WhatsApp number. Smile Factory's registration chatbot has already verified the person,
 * so the number is the identity: no email address or emailed code is asked for.
 */
class PhoneAccounts
{
    /** Domain for the stand-in email of accounts created from a number. Nothing is ever sent there. */
    public const PLACEHOLDER_DOMAIN = 'whatsapp.invalid';

    /** 0771234567, +263771234567 and 263771234567 are the same number: 263771234567. */
    public static function normalize(string $input): string
    {
        $d = preg_replace('/\D/', '', $input);
        if (str_starts_with($d, '00')) {
            $d = substr($d, 2);
        }
        if (strlen($d) === 10 && str_starts_with($d, '0')) {
            return '263'.substr($d, 1);
        }
        if (strlen($d) === 9 && str_starts_with($d, '7')) {
            return '263'.$d;
        }

        return $d;
    }

    /** +263 77 123 4567 */
    public static function display(string $phone): string
    {
        if (preg_match('/^263(\d{2})(\d{3})(\d{4})$/', $phone, $m)) {
            return "+263 {$m[1]} {$m[2]} {$m[3]}";
        }

        return '+'.$phone;
    }

    /** The temporary password for a new member: the fixed one from the environment if set, otherwise a random one. */
    public function tempPassword(): string
    {
        $fixed = (string) config('ideas.temp_password');

        return $fixed !== '' ? $fixed : Str::password(10, symbols: false);
    }

    /** True once the phone-only sign-up migration has run, so older databases keep working. */
    public static function phoneOnly(): bool
    {
        return Schema::hasColumn('users', 'wa_welcomed_at');
    }

    /** A General member made from a WhatsApp number alone: no email, a hashed temporary password that must be changed. */
    public function createMember(string $phone, ?string $name = null): User
    {
        $phoneOnly = self::phoneOnly();

        return User::create([
            'name' => trim((string) $name) ?: 'Member',
            'email' => $phoneOnly ? null : $phone.'@'.self::PLACEHOLDER_DOMAIN,
            'phone' => $phone,
            'password' => Hash::make($this->tempPassword()),
            'must_change_password' => true,
            'is_admin' => false,
            'member_type' => 'general',
            'joined' => (string) now()->year,
            'color' => '#049016',
        ] + ($phoneOnly ? ['source' => 'whatsapp'] : []));
    }

    public function forPhone(string $phone, ?string $name = null): User
    {
        return User::where('phone', $phone)->first() ?? $this->createMember($phone, $name);
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
