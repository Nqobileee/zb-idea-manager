<?php

namespace App\Livewire;

use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Support\Realtime;
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
        $msg = $conv->messages()->create(['user_id' => $me->id, 'body' => mb_substr($text, 0, 4000)]);
        $conv->update(['last_message_at' => now()]);
        $this->reset('body');
        Realtime::send(new MessageSent($msg, $conv->other($me)->id));
    }

    public function render()
    {
        $me = auth()->user();
        $conv = $this->conversationId ? Conversation::with(['userA', 'userB', 'messages'])->find($this->conversationId) : null;
        if ($conv) {
            Message::where('conversation_id', $conv->id)->where('user_id', '!=', $me->id)->whereNull('read_at')->update(['read_at' => now()]);
        }

        $list = $me->conversations()->with(['userA', 'userB'])->get()
            ->map(function (Conversation $c) use ($me) {
                $c->last = Message::where('conversation_id', $c->id)->latest('id')->first();
                $c->unread = Message::where('conversation_id', $c->id)->where('user_id', '!=', $me->id)->whereNull('read_at')->count();

                return $c;
            })->filter(fn ($c) => $c->last)->sortByDesc(fn ($c) => $c->last->created_at)->values();

        $people = $this->picking
            ? User::where('id', '!=', $me->id)->when($this->find, fn ($q) => $q->whereLike('name', '%'.$this->find.'%'))->orderBy('name')->get()
            : collect();

        return view('livewire.chat', ['conv' => $conv, 'list' => $list, 'people' => $people, 'me' => $me])
            ->layout('layouts.app', ['bare' => (bool) $conv, 'noDock' => true, 'title' => 'Chat']);
    }
}
