<?php

namespace App\Livewire\Super;

use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.guest')]
#[Title('Super admin')]
class Login extends Component
{
    public string $email = '';

    public string $password = '';

    public ?string $error = null;

    public function mount()
    {
        abort_unless(config('ideas.super_admin.email') && config('ideas.super_admin.password'), 404);
        if (session('super_admin')) {
            return redirect()->route('super.portal');
        }
    }

    public function signIn()
    {
        $key = 'super-login:'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->error = 'Too many attempts. Try again in a few minutes.';

            return;
        }
        $okEmail = hash_equals(strtolower((string) config('ideas.super_admin.email')), strtolower(trim($this->email)));
        $okPass = hash_equals((string) config('ideas.super_admin.password'), $this->password);
        if (! ($okEmail && $okPass)) {
            RateLimiter::hit($key, 600);
            $this->password = '';
            $this->error = 'Those details are not right.';

            return;
        }
        RateLimiter::clear($key);
        session()->regenerate();
        session()->put('super_admin', true);

        return redirect()->route('super.portal');
    }

    public function render()
    {
        return view('livewire.super.login');
    }
}
