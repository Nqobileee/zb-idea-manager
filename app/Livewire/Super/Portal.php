<?php

namespace App\Livewire\Super;

use App\Models\Challenge;
use App\Models\Comment;
use App\Models\Idea;
use App\Models\User;
use Illuminate\Support\Facades\DB;
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
