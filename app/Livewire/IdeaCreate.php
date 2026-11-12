<?php

namespace App\Livewire;

use App\Models\Challenge;
use App\Models\Idea;
use App\Models\IdeaFile;
use App\Models\Team;
use App\Services\IdeaActions;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app', ['bare' => false])]
#[Title('New idea')]
class IdeaCreate extends Component
{
    use WithFileUploads;

    public ?int $ideaId = null;

    public string $title = '';

    public string $summary = '';

    public string $body = '';

    public string $status = 'Idea';

    public string $visibility = 'private';

    #[Url]
    public ?int $challenge = null;

    /** The team the idea is put forward with, if any. */
    #[Url]
    public ?int $team = null;

    public array $docs = [];

    public array $images = [];

    public function mount(?Idea $idea = null): void
    {
        if ($idea?->exists) {
            abort_unless($idea->canBeManagedBy(auth()->user()), 403);
            $this->ideaId = $idea->id;
            $this->title = $idea->title;
            $this->summary = $idea->summary;
            $this->body = $idea->body === $idea->summary ? '' : $idea->body;
            $this->status = $idea->status;
            $this->visibility = $idea->is_public ? 'public' : 'private';
            $this->challenge = $idea->challenge_id;
            $this->team = $idea->team_id;
        }
        // a link can name any team; only one this person belongs to is kept
        if ($this->team && ! (Team::ready() && auth()->user()->teams()->whereKey($this->team)->exists())) {
            $this->team = null;
        }
    }

    public function removeExisting(int $fileId, IdeaActions $actions): void
    {
        $actions->removeFile(Idea::findOrFail($this->ideaId), auth()->user(), $fileId);
    }

    protected function rules(): array
    {
        return [
            'title' => 'required|string|max:120',
            'summary' => 'required|string|max:400',
            'body' => 'nullable|string|max:10000',
            'status' => 'required|in:'.implode(',', Idea::STATUSES),
            'visibility' => 'required|in:private,public',
            'challenge' => 'nullable|exists:challenges,id',
            'team' => ['nullable', 'integer', Team::ready() ? \Illuminate\Validation\Rule::exists('team_user', 'team_id')->where('user_id', auth()->id()) : 'prohibited'],
            'docs.*' => 'file|max:10240|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,csv,txt',
            'images.*' => 'image|max:5120',
        ];
    }

    public function removeDoc(int $i): void
    {
        unset($this->docs[$i]);
        $this->docs = array_values($this->docs);
    }

    public function removeImage(int $i): void
    {
        unset($this->images[$i]);
        $this->images = array_values($this->images);
    }

    public function save(IdeaActions $actions)
    {
        $this->validate();
        $data = [
            'title' => $this->title, 'summary' => $this->summary, 'body' => $this->body,
            'status' => $this->status, 'challenge_id' => $this->challenge, 'visibility' => $this->visibility,
            'team_id' => $this->team,
        ];
        $idea = $this->ideaId
            ? $actions->update(Idea::findOrFail($this->ideaId), auth()->user(), $data)
            : $actions->create(auth()->user(), $data);
        foreach ($this->docs as $f) {
            IdeaFile::create(['idea_id' => $idea->id, 'kind' => 'doc', 'name' => $f->getClientOriginalName(), 'path' => $f->store('ideas/docs', config('ideas.upload_disk')), 'size' => $this->human($f->getSize())]);
        }
        foreach ($this->images as $f) {
            IdeaFile::create(['idea_id' => $idea->id, 'kind' => 'image', 'name' => $f->getClientOriginalName(), 'path' => $f->store('ideas/images', config('ideas.upload_disk')), 'size' => $this->human($f->getSize())]);
        }

        return $this->redirectRoute('ideas.show', $idea, navigate: true);
    }

    private function human(int $bytes): string
    {
        return $bytes >= 1048576 ? round($bytes / 1048576, 1).' MB' : max(1, round($bytes / 1024)).' KB';
    }

    public function render()
    {
        return view('livewire.idea-create', [
            'challenges' => Challenge::orderBy('title')->get(),
            'teams' => Team::ready() ? auth()->user()->teams()->get() : collect(),
            'existing' => $this->ideaId ? IdeaFile::where('idea_id', $this->ideaId)->get() : collect(),
        ])->title($this->ideaId ? 'Edit idea' : 'New idea');
    }
}
