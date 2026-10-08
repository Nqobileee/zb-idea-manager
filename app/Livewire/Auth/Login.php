<?php

namespace App\Livewire\Auth;

use App\Services\AuthCodes;
use Illuminate\Support\Facades\Auth;
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

    public ?string $error = null;

    public function sendCode(AuthCodes $codes)
    {
        $this->email = strtolower(trim($this->email));
        if (! $codes->isWorkEmail($this->email)) {
            $this->error = config('ideas.email_domain') ? 'Use your ZB work email, ending in @'.config('ideas.email_domain').'.' : 'Enter a valid email address.';

            return;
        }
        $this->error = null;
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
        $user = $codes->userFor($this->email, $wantsAdmin);
        if (! $user) {
            $this->error = 'We could not find a ZB account for that email.';

            return;
        }
        if (config('ideas.allow_role_choice') && $user->is_admin !== $wantsAdmin) {
            $user->update(['is_admin' => $wantsAdmin]);
        }
        Auth::login($user, remember: true);
        session()->regenerate();

        return redirect()->intended($user->is_admin ? route('admin.ranking') : route('home'));
    }

    public function render()
    {
        return view('livewire.auth.login', ['demo' => config('ideas.accept_any_code'), 'needsCode' => config('ideas.require_code'), 'roleChoice' => config('ideas.allow_role_choice')]);
    }
}
