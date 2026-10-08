<?php

namespace App\Livewire\Admin;

use App\Models\Challenge;
use App\Models\Idea;
use App\Services\IdeaActions;
use App\Services\Ranking as RankingService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('AI ranking')]
class Ranking extends Component
{
    public string $challenge = 'all';

    public int $rel = 40;

    public int $eng = 35;

    public int $q = 25;

    public function approve(int $ideaId, IdeaActions $actions): void
    {
        $actions->approve(Idea::findOrFail($ideaId), auth()->user());
        $this->dispatch('toast', message: 'Approved. The author has been emailed.');
    }

    public function digest(IdeaActions $actions): void
    {
        $mail = $actions->digest(auth()->user(), $this->selected(), $this->weights());
        $this->dispatch('toast', message: 'Top 5 sent to '.$mail->to);
    }

    private function selected(): ?Challenge
    {
        return $this->challenge === 'all' ? null : Challenge::find((int) $this->challenge);
    }

    private function weights(): array
    {
        return ['rel' => $this->rel, 'eng' => $this->eng, 'q' => $this->q];
    }

    public function render(RankingService $ranking)
    {
        return view('livewire.admin.ranking', [
            'list' => $ranking->rank($this->selected(), $this->weights()),
            'challenges' => Challenge::orderBy('title')->get(),
        ]);
    }
}
