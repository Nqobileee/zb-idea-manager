<?php

namespace App\Livewire;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Team;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Chat')]
class Chat extends Component
{
    public ?int $conversationId = null;

    public string $body = '';

    public bool $picking = false;

    public string $find = '';

    public function mount(?Conversation $conversation = null): void
    {
        if ($conversation) {
            abort_unless($conversation->involves(auth()->user()), 403);
            $this->conversationId = $conversation->id;
        }
    }

    #[On('realtime')]
    public function refresh(): void {}

    public function open(int $id): void
    {
        $this->redirectRoute('chat', Conversation::findOrFail($id), navigate: true);
    }

    public function close(): void
    {
        $this->redirectRoute('chat', navigate: true);
    }

    public function start(int $userId)
    {
        $other = User::where('id', '!=', auth()->id())->findOrFail($userId);

        return $this->redirectRoute('chat', Conversation::between(auth()->user(), $other), navigate: true);
    }

    public function send(): void
    {
        $me = auth()->user();
        $conv = Conversation::findOrFail($this->conversationId);
        abort_unless($conv->involves($me), 403);
        $text = trim($this->body);
        if ($text === '') {
            return;
        }
        $conv->post($me, $text);
        $this->reset('body');
    }

    public function render()
    {
        $me = auth()->user();
        Message::deliverTo($me);
        $conv = $this->conversationId ? Conversation::with(['userA', 'userB', 'messages.sender'])->find($this->conversationId) : null;
        $conv?->markRead($me);

        $summarise = function (Conversation $c) use ($me) {
            $c->last = Message::with('sender')->where('conversation_id', $c->id)->latest('id')->first();
            $c->unread = $c->unreadFor($me);

            return $c;
        };

        // The all-staff group is always pinned first; private chats follow, newest on top.
        $group = Conversation::hasGroupChat() ? $summarise(Conversation::everyone()) : null;
        $list = $me->conversations()->with(['userA', 'userB'])->get()->map($summarise)
            ->filter(fn ($c) => $c->last)->sortByDesc(fn ($c) => $c->last->created_at)->values();

        // Team and project groups the person was added to. Empty ones still show, so a new team's chat can be found.
        $groups = Team::ready()
            ? $me->groupChats()->get()->map($summarise)->sortByDesc(fn ($c) => ($c->last?->created_at ?? $c->created_at)->timestamp)->values()
            : collect();

        $people = $this->picking
            ? User::where('id', '!=', $me->id)->when($this->find, fn ($q) => $q->whereLike('name', '%'.$this->find.'%'))->orderBy('name')->get()
            : collect();

        return view('livewire.chat', ['conv' => $conv, 'group' => $group, 'groups' => $groups, 'list' => $list, 'people' => $people, 'me' => $me, 'members' => User::count()])
            ->layout('layouts.app', ['bare' => (bool) $conv, 'noDock' => true, 'title' => 'Chat']);
    }
}
