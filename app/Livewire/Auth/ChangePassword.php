<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.guest')]
#[Title('Change password')]
class ChangePassword extends Component
{
    public string $password = '';

    public string $password_confirmation = '';

    public function save()
    {
        $this->validate(['password' => 'required|string|min:8|confirmed'], [], ['password' => 'new password']);
        $user = auth()->user();
        if (Hash::check($this->password, (string) $user->password) || $this->password === (string) config('ideas.default_password')) {
            $this->addError('password', 'Choose a password that is different from the one you used to sign in.');

            return;
        }
        $user->forceFill(['password' => Hash::make($this->password), 'must_change_password' => false])->save();
        session()->flash('status', 'Password changed.');

        return redirect()->intended($user->is_admin ? route('admin.ranking') : route('home'));
    }

    public function render()
    {
        return view('livewire.auth.change-password', ['forced' => (bool) auth()->user()->must_change_password]);
    }
}
