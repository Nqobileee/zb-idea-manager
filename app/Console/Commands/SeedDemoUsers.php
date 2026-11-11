<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/** Creates or refreshes the demo executive and demo member from the private IDEAS_DEMO_* environment settings. */
class SeedDemoUsers extends Command
{
    protected $signature = 'ideas:seed-demo';

    protected $description = 'Create or update the demo Executive admin and General member from IDEAS_DEMO_* in the environment';

    public function handle(): int
    {
        $made = 0;
        foreach ([
            ['Demo Executive', 'admin', true],
            ['Demo Member', 'member', false],
        ] as [$name, $key, $admin]) {
            $email = strtolower(trim((string) config("ideas.demo.{$key}.email")));
            $password = (string) config("ideas.demo.{$key}.password");
            if ($email === '' || $password === '') {
                $this->warn("Skipped {$name}: set IDEAS_DEMO_".strtoupper($key).'_EMAIL and _PASSWORD.');

                continue;
            }
            $user = User::firstOrNew(['email' => $email]);
            $user->forceFill([
                'name' => $user->exists ? $user->name : $name,
                'password' => Hash::make($password),
                'is_admin' => $admin,
                'member_type' => 'general',
                'joined' => $user->joined ?: (string) now()->year,
                'color' => $user->color ?: '#049016',
            ])->save();
            $this->info("{$name} ready: {$email}");
            $made++;
        }

        return $made ? self::SUCCESS : self::FAILURE;
    }
}
