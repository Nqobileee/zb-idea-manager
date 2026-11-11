<?php

namespace App\Livewire\Concerns;

use App\Models\Idea;
use App\Services\IdeaActions;

/** Like / save / share buttons used on idea cards in several components. */
trait InteractsWithIdeas
{
    public function like(int $ideaId, IdeaActions $actions): void
    {
        $actions->toggleLike(Idea::visibleTo(auth()->user())->findOrFail($ideaId), auth()->user());
    }

    public function save(int $ideaId, IdeaActions $actions): void
    {
        $saved = $actions->toggleSave(Idea::visibleTo(auth()->user())->findOrFail($ideaId), auth()->user());
        $this->dispatch('toast', message: $saved ? 'Saved to your list' : 'Removed from saved');
    }

    public function share(int $ideaId): void
    {
        $idea = Idea::visibleTo(auth()->user())->findOrFail($ideaId);
        $idea->increment('shares');
        $this->dispatch('share-idea', url: route('ideas.show', $idea), title: $idea->title);
        $this->dispatch('toast', message: 'Link ready to share');
    }
}
