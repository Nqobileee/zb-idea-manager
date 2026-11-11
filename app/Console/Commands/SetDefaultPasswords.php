<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\PhoneAccounts;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/** Gives accounts that have no password the generic one, and makes them choose their own the first time they sign in. */
class SetDefaultPasswords extends Command
{
    protected $signature = 'ideas:set-default-passwords {--except-admins : Leave Executive admin accounts alone}';

    protected $description = 'Set the generic password (IDEAS_DEFAULT_PASSWORD) on accounts without one and force a change at first sign-in';

    public function handle(): int
    {
        $default = (string) config('ideas.default_password');
        if ($default === '') {
            $this->error('Set IDEAS_DEFAULT_PASSWORD first.');

            return self::FAILURE;
        }
        $q = User::whereNull('password')->where('email', 'not like', '%@'.PhoneAccounts::PLACEHOLDER_DOMAIN);
        if ($this->option('except-admins')) {
            $q->where('is_admin', false);
        }
        $users = $q->get();
        $hash = Hash::make($default);
        foreach ($users as $u) {
            $u->forceFill(['password' => $hash, 'must_change_password' => true])->save();
        }
        $this->info($users->count().' account(s) updated, '.$users->where('is_admin', true)->count().' of them Executive admin.');
        $this->warn('Until each person signs in and changes it, anyone who knows their email can use the generic password.');

        return self::SUCCESS;
    }
}
