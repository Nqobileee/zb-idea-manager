<?php

namespace App\Livewire\Admin;

use App\Models\SentEmail;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Email log')]
class Emails extends Component
{
    public ?int $open = null;

    public function toggle(int $id): void
    {
        $this->open = $this->open === $id ? null : $id;
    }

    public function render()
    {
        return view('livewire.admin.emails', ['emails' => SentEmail::latest()->take(100)->get()]);
    }
}
