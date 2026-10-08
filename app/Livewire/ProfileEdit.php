<?php

namespace App\Livewire;

use App\Services\AuthCodes;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
#[Title('Edit profile')]
class ProfileEdit extends Component
{
    use WithFileUploads;

    public string $name = '';

    public string $title = '';

    public string $dept = '';

    public string $bio = '';

    public string $phone = '';

    public bool $whatsappOptIn = true;

    public $photo;

    public function mount(): void
    {
        $u = auth()->user();
        $this->fill(['name' => $u->name, 'title' => (string) $u->title, 'dept' => (string) $u->dept, 'bio' => (string) $u->bio, 'phone' => (string) $u->phone, 'whatsappOptIn' => (bool) ($u->whatsapp_opt_in ?? true)]);
    }

    public function save()
    {
        $this->validate([
            'name' => 'required|string|max:80', 'title' => 'nullable|string|max:80', 'dept' => 'nullable|string|max:80',
            'bio' => 'nullable|string|max:400', 'photo' => 'nullable|image|max:3072',
        ]);
        $u = auth()->user();
        $data = ['name' => trim($this->name), 'title' => trim($this->title), 'dept' => trim($this->dept), 'bio' => trim($this->bio), 'whatsapp_opt_in' => $this->whatsappOptIn];
        if ($this->photo) {
            if ($u->avatar_path) {
                Storage::disk(config('ideas.upload_disk'))->delete($u->avatar_path);
            }
            $data['avatar_path'] = $this->photo->store('avatars', config('ideas.upload_disk'));
        }
        $u->update($data);

        return $this->redirectRoute('profile', $u, navigate: true);
    }

    public function render()
    {
        return view('livewire.profile-edit');
    }
}
