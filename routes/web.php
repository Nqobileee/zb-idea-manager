<?php

use App\Http\Controllers\WhatsappWebhookController;
use App\Livewire\ActivityFeed;
use App\Livewire\Admin;
use App\Livewire\Auth\Login;
use App\Livewire\Challenges;
use App\Livewire\ChallengeCreate;
use App\Livewire\ChallengeShow;
use App\Livewire\Chat;
use App\Livewire\Feed;
use App\Livewire\IdeaCreate;
use App\Livewire\IdeaShow;
use App\Livewire\Members;
use App\Livewire\Profile;
use App\Livewire\ProfileEdit;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// WhatsApp Cloud API webhook (public, protected by Meta's signature)
Route::get('/webhooks/whatsapp', [WhatsappWebhookController::class, 'verify']);
Route::post('/webhooks/whatsapp', [WhatsappWebhookController::class, 'receive']);

Route::middleware('guest')->group(function () {
    Route::get('/login', Login::class)->name('login');
});

Route::post('/logout', function () {
    Auth::logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect()->route('login');
})->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/', Feed::class)->name('home');
    Route::get('/ideas/create', IdeaCreate::class)->name('ideas.create');
    Route::get('/ideas/{idea}/edit', IdeaCreate::class)->name('ideas.edit');
    Route::get('/ideas/{idea}', IdeaShow::class)->name('ideas.show');

    Route::get('/challenges', Challenges::class)->name('challenges');
    Route::get('/challenges/create', ChallengeCreate::class)->name('challenges.create');
    Route::get('/challenges/{challenge}/edit', ChallengeCreate::class)->name('challenges.edit');
    Route::get('/challenges/{challenge}', ChallengeShow::class)->name('challenges.show');

    Route::get('/chat/{conversation?}', Chat::class)->name('chat');
    Route::get('/activity', ActivityFeed::class)->name('activity');
    Route::get('/members', Members::class)->name('members');
    Route::get('/profile/edit', ProfileEdit::class)->name('profile.edit');
    Route::get('/members/{user}', Profile::class)->name('profile');

    Route::middleware('admin')->prefix('executive')->group(function () {
        Route::get('/ranking', Admin\Ranking::class)->name('admin.ranking');
        Route::get('/insights', Admin\Insights::class)->name('admin.insights');
    });
});
