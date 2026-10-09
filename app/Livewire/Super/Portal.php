<?php

namespace App\Livewire\Super;

use App\Models\Challenge;
use App\Models\Comment;
use App\Models\Idea;
use App\Models\User;
use App\Services\PhoneAccounts;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Everything in one place for the super admin: overview, every member, every idea (private ones too) and challenges. */
#[Layout('layouts.guest')]
#[Title('Super admin')]
class Portal extends Component
{
    #[Url]
    public string $tab = 'overview';

    #[Url(as: 'q')]
    public string $search = '';

    public ?string $notice = null;

    // the "add member" form
    public string $newName = '';

    public string $newEmail = '';

    public string $newPhone = '';

    public string $newRole = 'general';

    public string $newPassword = '';

    /** Shown once after a member is added, so the sign-in details can be passed on. Never stored. */
    public ?array $created = null;

    /** Livewire update requests do not run the route middleware, so every action checks the session itself. */
    private function guard(): void
    {
        abort_unless(session('super_admin') === true, 403);
    }

    public function mount(): void
    {
        $this->guard();
    }

    public function setTab(string $tab): void
    {
        $this->guard();
        $this->tab = in_array($tab, ['overview', 'members', 'ideas', 'challenges'], true) ? $tab : 'overview';
        $this->search = '';
        $this->notice = null;
    }

    public function addMember(): void
    {
        $this->guard();
        $email = strtolower(trim($this->newEmail));
        $phone = trim($this->newPhone) !== '' ? PhoneAccounts::normalize($this->newPhone) : '';
        $this->validate([
            'newName' => 'required|string|min:2|max:120',
            'newRole' => 'required|in:general,admin',
            'newPassword' => 'nullable|string|min:8|max:100',
        ], [], ['newName' => 'name', 'newPassword' => 'password']);
        if ($email === '' && $phone === '') {
            $this->addError('newEmail', 'Add an email, a phone number, or both so they can sign in.');

            return;
        }
        if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->addError('newEmail', 'That does not look like a valid email address.');

            return;
        }
        if ((trim($this->newPhone) !== '' && $phone === '') || ($phone !== '' && ! preg_match('/^\d{9,15}$/', $phone))) {
            $this->addError('newPhone', 'That does not look like a valid phone number.');

            return;
        }
        if ($email !== '' && User::where('email', $email)->exists()) {
            $this->addError('newEmail', 'That email already belongs to a member.');

            return;
        }
        if ($phone !== '' && User::where('phone', $phone)->exists()) {
            $this->addError('newPhone', 'That phone number already belongs to a member.');

            return;
        }

        $password = trim($this->newPassword) !== '' ? $this->newPassword : Str::password(10, symbols: false);
        $phoneOnly = PhoneAccounts::phoneOnly();
        if ($email === '' && ! $phoneOnly) {
            $this->addError('newEmail', 'An email is needed until the latest database update has been run.');

            return;
        }
        $user = User::create([
            'name' => trim($this->newName), 'email' => $email !== '' ? $email : null, 'phone' => $phone !== '' ? $phone : null,
            'password' => Hash::make($password), 'must_change_password' => true,
            'is_admin' => $this->newRole === 'admin', 'member_type' => 'general',
            'joined' => (string) now()->year, 'color' => '#049016',
        ] + ($phoneOnly ? ['source' => 'admin', 'wa_welcomed_at' => now()] : []));

        $this->created = ['name' => $user->name, 'login' => $email !== '' ? $email : PhoneAccounts::display($phone), 'password' => $password, 'role' => $user->role_label, 'url' => route('login')];
        $this->notice = "{$user->name} was added.";
        $this->reset('newName', 'newEmail', 'newPhone', 'newPassword');
        $this->newRole = 'general';
    }

    // linking a phone number or email to an existing member
    public ?int $editingId = null;

    public string $editEmail = '';

    public string $editPhone = '';

    public function editContact(int $userId): void
    {
        $this->guard();
        $u = User::findOrFail($userId);
        $this->resetErrorBag();
        $this->editingId = $u->id;
        $this->editEmail = (string) $u->email;
        $this->editPhone = (string) $u->phone;
    }

    public function cancelEdit(): void
    {
        $this->guard();
        $this->reset('editingId', 'editEmail', 'editPhone');
        $this->resetErrorBag();
    }

    public function saveContact(): void
    {
        $this->guard();
        $u = User::findOrFail((int) $this->editingId);
        $email = strtolower(trim($this->editEmail));
        $phone = trim($this->editPhone) !== '' ? PhoneAccounts::normalize($this->editPhone) : '';
        if ($email === '' && $phone === '') {
            $this->addError('editEmail', 'Keep at least an email or a phone number so they can sign in.');

            return;
        }
        if ($email === '' && ! PhoneAccounts::phoneOnly()) {
            $this->addError('editEmail', 'An email is needed until the latest database update has been run.');

            return;
        }
        if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->addError('editEmail', 'That does not look like a valid email address.');

            return;
        }
        if ((trim($this->editPhone) !== '' && $phone === '') || ($phone !== '' && ! preg_match('/^\d{9,15}$/', $phone))) {
            $this->addError('editPhone', 'That does not look like a valid phone number.');

            return;
        }
        if ($email !== '' && User::where('email', $email)->where('id', '!=', $u->id)->exists()) {
            $this->addError('editEmail', 'That email already belongs to another member.');

            return;
        }
        if ($phone !== '' && User::where('phone', $phone)->where('id', '!=', $u->id)->exists()) {
            $this->addError('editPhone', 'That phone number already belongs to another member.');

            return;
        }
        $u->update(['email' => $email !== '' ? $email : null, 'phone' => $phone !== '' ? $phone : null]);
        $this->notice = "{$u->name}'s contact details were saved.";
        $this->cancelEdit();
    }

    public function dismissCreated(): void
    {
        $this->guard();
        $this->created = null;
    }

    public function toggleAdmin(int $userId): void
    {
        $this->guard();
        $u = User::findOrFail($userId);
        $u->update(['is_admin' => ! $u->is_admin]);
        $this->notice = $u->name.($u->is_admin ? ' is now an Executive admin.' : ' is no longer an admin.');
    }

    public function removeMember(int $userId): void
    {
        $this->guard();
        $u = User::findOrFail($userId);
        $name = $u->name;
        $u->delete(); // their ideas, comments, likes and links go with them
        $this->notice = "{$name} was removed.";
    }

    public function removeIdea(int $ideaId): void
    {
        $this->guard();
        $i = Idea::findOrFail($ideaId);
        $title = $i->title;
        $i->delete();
        $this->notice = "Idea \"{$title}\" was removed.";
    }

    public function render()
    {
        $this->guard();
        $term = '%'.trim($this->search).'%';
        $data = [];

        if ($this->tab === 'overview') {
            $data['stats'] = [
                'Members' => User::count(),
                'Executive admins' => User::where('is_admin', true)->count(),
                'Linked to WhatsApp' => User::whereNotNull('phone')->count(),
                'Ideas' => Idea::count(),
                'Public ideas' => Idea::hasVisibility() ? Idea::where('visibility', 'public')->count() : Idea::count(),
                'Private ideas' => Idea::hasVisibility() ? Idea::where('visibility', 'private')->count() : 0,
                'Approved' => Idea::where('approved', true)->count(),
                'Challenges' => Challenge::count(),
                'Comments' => Comment::count(),
                'Likes' => DB::table('idea_likes')->count(),
            ];
            $data['byStage'] = collect(Idea::STATUSES)->mapWithKeys(fn ($s) => [$s => Idea::where('status', $s)->count()]);
            $data['recent'] = Idea::with('author')->latest()->take(8)->get();
        } elseif ($this->tab === 'members') {
            $data['members'] = User::withCount('ideas')->when(trim($this->search) !== '', fn ($q) => $q->where(fn ($w) => $w->whereLike('name', $term)->orWhereLike('email', $term)->orWhereLike('phone', $term)))
                ->orderByDesc('is_admin')->orderBy('name')->get();
        } elseif ($this->tab === 'ideas') {
            $data['ideas'] = Idea::with('author')->withCount(['likers', 'comments'])->when(trim($this->search) !== '', fn ($q) => $q->where(fn ($w) => $w->whereLike('title', $term)->orWhereLike('summary', $term)))
                ->latest()->take(200)->get();
        } else {
            $data['challenges'] = Challenge::with('owner')->withCount('ideas')->orderBy('deadline')->get();
        }

        return view('livewire.super.portal', $data);
    }
}
