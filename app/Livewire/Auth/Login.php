<?php

namespace App\Livewire\Auth;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** No form: people register with Smile Factory on WhatsApp, then ask the bot for a sign-in link. */
#[Layout('layouts.guest')]
#[Title('Sign in')]
class Login extends Component
{
    public function render()
    {
        $number = preg_replace('/\D/', '', (string) config('ideas.whatsapp.display_number'));

        return view('livewire.auth.login', ['waUrl' => $number ? 'https://wa.me/'.$number.'?text='.rawurlencode('web') : null, 'error' => session('error')]);
    }
}
