<?php

use App\Http\Controllers\WhatsappWebhookController;
use App\Http\Controllers\ZernioController;
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
use App\Livewire\Pipeline;
use App\Livewire\ProjectShow;
use App\Livewire\Profile;
use App\Livewire\ProfileEdit;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// WhatsApp Cloud API webhook (public, protected by Meta's signature)
Route::get('/webhooks/whatsapp', [WhatsappWebhookController::class, 'verify']);
Route::post('/webhooks/whatsapp', [WhatsappWebhookController::class, 'receive']);

// Zernio chatbot backend: POST only, protected by the X-Zernio-Secret header
Route::middleware('zernio')->prefix('api/zernio')->controller(ZernioController::class)->group(function () {
    Route::post('/link', 'link');
    Route::post('/web-link', 'webLink');
    Route::post('/notifications', 'notifications');
    Route::post('/notifications/list', 'notificationList');
    Route::post('/pipeline', 'pipeline');
    Route::post('/account/check', 'accountCheck');
    Route::post('/account/switch', 'accountSwitch');
    Route::post('/account/login', 'accountLogin');
    Route::post('/account/register', 'accountRegister');
    Route::post('/challenges/options', 'challengeOptions');
    Route::post('/challenges', 'challenges');
    Route::post('/challenges/detail', 'challengeDetail');
    Route::post('/challenges/ideas', 'challengeIdeas');
    Route::post('/ideas/preview', 'previewIdea');
    Route::post('/ideas', 'createIdea');
    Route::post('/ideas/mine', 'myIdeas');
    Route::post('/ideas/top', 'topIdeas');
    Route::post('/ideas/top5', 'topFive');
    Route::post('/ideas/detail', 'ideaDetail');
    Route::post('/ideas/like', 'like');
    Route::post('/ideas/approve', 'approve');
    Route::post('/reports', 'reports');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', Login::class)->name('login');
    // One-use sign-in link the WhatsApp bot sends when someone says "web".
    Route::get('/wa-login/{token}', function (string $token, \App\Services\PhoneAccounts $accounts) {
        $user = $accounts->redeem($token);
        if (! $user) {
            return redirect()->route('login')->with('error', 'That link has expired. Send "web" to the WhatsApp number for a new one.');
        }
        Auth::login($user, remember: true);
        session()->regenerate();

        return redirect()->intended($user->is_admin ? route('admin.ranking') : route('home'));
    })->name('wa.login');
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
    Route::get('/pipeline', Pipeline::class)->name('pipeline');
    Route::get('/projects/{idea}', ProjectShow::class)->name('projects.show');
    Route::get('/profile/edit', ProfileEdit::class)->name('profile.edit');
    Route::get('/members/{user}', Profile::class)->name('profile');

    Route::middleware('admin')->prefix('executive')->group(function () {
        Route::get('/ranking', Admin\Ranking::class)->name('admin.ranking');
        Route::get('/insights', Admin\Insights::class)->name('admin.insights');
    });
});
