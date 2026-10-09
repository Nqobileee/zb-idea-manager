<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * People who registered with the Smile Factory chatbot sign in with the email and password they made there
 * (the chatbot sends them to /api/zernio/link). Everyone else follows the WhatsApp steps and gets a sign-in link.
 */
#[Layout('layouts.guest')]
#[Title('Sign in')]
class Login extends Component
{
    public string $email = '';

    public string $password = '';

    public ?string $error = null;

    public function signIn()
    {
        $this->validate(['email' => 'required|email', 'password' => 'required|string'], [], ['email' => 'email address']);
        $email = strtolower(trim($this->email));
        $key = 'login:'.request()->ip().'|'.$email;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->error = 'Too many attempts. Try again in a few minutes.';

            return;
        }
        if (! Auth::attempt(['email' => $email, 'password' => $this->password], remember: true)) {
            RateLimiter::hit($key, 600);
            $this->error = 'That email and password do not match. If you are new, follow the WhatsApp steps below.';
            $this->password = '';

            return;
        }
        RateLimiter::clear($key);
        session()->regenerate();
        if ($this->password === (string) config('ideas.default_password') && \Illuminate\Support\Facades\Schema::hasColumn('users', 'must_change_password')) {
            Auth::user()->forceFill(['must_change_password' => true])->save();

            return redirect()->route('password.change');
        }

        return redirect()->intended(Auth::user()->is_admin ? route('admin.ranking') : route('home'));
    }

    public function render()
    {
        $number = preg_replace('/\D/', '', (string) config('ideas.whatsapp.display_number'));

        return view('livewire.auth.login', ['waUrl' => 'https://wa.me/'.($number ?: '263777366886').'?text='.rawurlencode('hi'), 'linkError' => session('error')]);
    }
}
