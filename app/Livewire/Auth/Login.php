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

    public ?string $error = null;

    public function sendCode(AuthCodes $codes): void
    {
        $this->email = strtolower(trim($this->email));
        if (! $codes->isWorkEmail($this->email)) {
            $this->error = 'Use your ZB work email, ending in @'.config('ideas.email_domain').'.';

            return;
        }
        $this->error = null;
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
        $user = $codes->userFor($this->email);
        if (! $user) {
            $this->error = 'We could not find a ZB account for that email.';

            return;
        }
        Auth::login($user, remember: true);
        session()->regenerate();

        return redirect()->intended($user->is_admin ? route('admin.ranking') : route('home'));
    }

    public function render()
    {
        return view('livewire.auth.login', ['demo' => config('ideas.accept_any_code')]);
    }
}
