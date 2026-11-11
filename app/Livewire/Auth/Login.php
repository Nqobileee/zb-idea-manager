<?php

namespace App\Livewire\Auth;

use App\Models\User;
use App\Services\PhoneAccounts;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
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
        $this->validate(['email' => 'required|string', 'password' => 'required|string'], [], ['email' => 'phone number or email']);
        $id = trim($this->email);
        $byEmail = str_contains($id, '@');
        $id = $byEmail ? strtolower($id) : PhoneAccounts::normalize($id);
        $key = 'login:'.request()->ip().'|'.$id;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->error = 'Too many attempts. Try again in 15 minutes.';

            return;
        }
        $user = User::where($byEmail ? 'email' : 'phone', $id)->first();
        if (! $user || ! $user->password || ! Hash::check($this->password, $user->password)) {
            RateLimiter::hit($key, 900);
            $this->error = 'That phone number or email and password do not match. If you are new, follow the WhatsApp steps below.';
            $this->password = '';

            return;
        }
        RateLimiter::clear($key);
        Auth::login($user, remember: true);
        session()->regenerate();
        if ($this->password === (string) config('ideas.default_password') && Schema::hasColumn('users', 'must_change_password')) {
            $user->forceFill(['must_change_password' => true])->save();

            return redirect()->route('password.change');
        }

        return redirect()->intended($user->is_admin ? route('admin.ranking') : route('home'));
    }

    public function render()
    {
        $number = preg_replace('/\D/', '', (string) config('ideas.whatsapp.display_number'));

        return view('livewire.auth.login', ['waUrl' => 'https://wa.me/'.($number ?: '263777366886').'?text='.rawurlencode('hi'), 'linkError' => session('error')]);
    }
}
