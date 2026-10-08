<?php

namespace App\Livewire\Auth;

use App\Models\User;
use App\Services\AuthCodes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.guest')]
#[Title('Sign in')]
class Login extends Component
{
    public string $step = 'email';

    public string $email = '';

    public string $code = '';

    public string $role = 'employee';

    public string $adminCode = '';

    public ?string $error = null;

    public function sendCode(AuthCodes $codes)
    {
        $this->email = strtolower(trim($this->email));
        if (! $codes->isWorkEmail($this->email)) {
            $this->error = config('ideas.email_domain') ? 'Use your ZB work email, ending in @'.config('ideas.email_domain').'.' : 'Enter a valid email address.';

            return;
        }
        $this->error = null;
        if ($this->adminCodeError()) {
            $this->error = $this->adminCodeError();

            return;
        }
        if (! config('ideas.require_code')) {
            return $this->signInAs($codes);
        }
        $codes->send($this->email);
        $this->step = 'code';
    }

    public function back(): void
    {
        $this->step = 'email';
        $this->code = '';
        $this->error = null;
    }

    public function verify(AuthCodes $codes)
    {
        if (! $codes->check($this->email, $this->code)) {
            $this->error = 'That code is not right or has expired. Check the email and try again.';

            return;
        }
        return $this->signInAs($codes);
    }

    /** Find or create the account for $this->email and sign in. Called after the code check, or straight away when codes are off. */
    private function signInAs(AuthCodes $codes)
    {
        $wantsAdmin = config('ideas.allow_role_choice') && $this->role === 'admin';
        if ($wantsAdmin && $this->adminCodeError()) {
            $this->error = $this->adminCodeError();

            return;
        }
        $user = $codes->userFor($this->email, $wantsAdmin, $this->role);
        if (! $user) {
            $this->error = 'We could not find a ZB account for that email.';

            return;
        }
        if (config('ideas.allow_role_choice')) {
            $user->update([
                'is_admin' => $wantsAdmin,
                'member_type' => array_key_exists($this->role, User::MEMBER_TYPES) ? $this->role : $user->member_type,
            ]);
        }
        Auth::login($user, remember: true);
        session()->regenerate();

        return redirect()->intended($user->is_admin ? route('admin.ranking') : route('home'));
    }

    /** Executive admin needs the secret code on top of the email. Returns an error message, or null when the code is right. */
    private function adminCodeError(): ?string
    {
        if (! config('ideas.allow_role_choice') || $this->role !== 'admin') {
            return null;
        }
        $secret = (string) config('ideas.admin_code');
        $key = 'admin-code:'.request()->ip().'|'.$this->email;
        if ($secret === '') {
            return 'Executive admin sign-in is not enabled.';
        }
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return 'Too many attempts. Try again in a few minutes.';
        }
        if (! hash_equals($secret, trim($this->adminCode))) {
            RateLimiter::hit($key, 600);

            return 'That executive access code is not right.';
        }
        RateLimiter::clear($key);

        return null;
    }

    public function render()
    {
        return view('livewire.auth.login', ['demo' => config('ideas.accept_any_code'), 'needsCode' => config('ideas.require_code'), 'roleChoice' => config('ideas.allow_role_choice'), 'roles' => User::MEMBER_TYPES + ['admin' => 'Executive admin']]);
    }
}
