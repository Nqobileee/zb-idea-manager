<?php

namespace App\Livewire\Admin;

use App\Models\Challenge;
use App\Models\Comment;
use App\Models\Idea;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Insights')]
class Insights extends Component
{
    public function render()
    {
        $byStage = collect(Idea::STATUSES)->mapWithKeys(fn ($s) => [$s => Idea::where('status', $s)->count()]);
        $byDept = Idea::join('users', 'users.id', '=', 'ideas.user_id')->select('users.dept', DB::raw('count(*) as n'))->groupBy('users.dept')->orderByDesc('n')->get();

        return view('livewire.admin.insights', [
            'kpis' => [
                'Ideas' => Idea::count(),
                'Approved' => Idea::where('approved', true)->count(),
                'Members' => User::count(),
                'Comments' => Comment::count(),
                'Open challenges' => Challenge::where(fn ($q) => $q->whereNull('deadline')->orWhere('deadline', '>=', now()->toDateString()))->count(),
                'From WhatsApp' => Idea::where('source', 'whatsapp')->count(),
            ],
            'byStage' => $byStage,
            'byDept' => $byDept,
            'challenges' => Challenge::withCount('ideas')->orderByDesc('ideas_count')->get(),
        ]);
    }
}
