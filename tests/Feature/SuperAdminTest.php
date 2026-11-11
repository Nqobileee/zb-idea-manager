<?php

namespace Tests\Feature;

use App\Livewire\Super\Login;
use App\Livewire\Super\Portal;
use App\Models\Idea;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SuperAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['ideas.super_admin' => ['email' => 'super@example.test', 'password' => 'super-secret-1']]);
        $this->seed(\Tests\Fixtures\SampleDataSeeder::class);
    }

    private function signedIn()
    {
        return $this->withSession(['super_admin' => true]);
    }

    public function test_portal_is_off_without_credentials_and_needs_sign_in_otherwise(): void
    {
        $this->get('/super')->assertRedirect(route('super.login'));
        $this->get('/super/login')->assertOk();
        config(['ideas.super_admin' => ['email' => '', 'password' => '']]);
        $this->get('/super')->assertNotFound();
        $this->get('/super/login')->assertNotFound();
    }

    public function test_normal_members_and_executives_cannot_open_it(): void
    {
        $this->actingAs(User::where('is_admin', true)->first());
        $this->get('/super')->assertRedirect(route('super.login'));
    }

    public function test_sign_in_with_the_environment_credentials_and_lockout(): void
    {
        Livewire::test(Login::class)->set('email', 'super@example.test')->set('password', 'wrong')->call('signIn')->assertNoRedirect();
        $this->assertNull(session('super_admin'));
        Livewire::test(Login::class)->set('email', 'SUPER@example.test')->set('password', 'super-secret-1')->call('signIn')->assertRedirect(route('super.portal'));
        $this->assertTrue(session('super_admin'));

        session()->forget('super_admin');
        foreach (range(1, 5) as $i) {
            Livewire::test(Login::class)->set('email', 'super@example.test')->set('password', 'bad'.$i)->call('signIn');
        }
        Livewire::test(Login::class)->set('email', 'super@example.test')->set('password', 'super-secret-1')->call('signIn')->assertNoRedirect();
        $this->assertNull(session('super_admin'));
    }

    public function test_it_shows_everything_including_private_ideas(): void
    {
        $author = User::where('is_admin', false)->first();
        Idea::create(['num' => Idea::nextNumber(), 'user_id' => $author->id, 'title' => 'Very private plan', 'summary' => 's', 'body' => 'b', 'status' => 'Idea', 'visibility' => 'private']);
        $this->signedIn()->get('/super')->assertOk()->assertSee('Control room')->assertSee('Members');
        $this->withSession(['super_admin' => true]);
        Livewire::test(Portal::class)->assertSee('Very private plan')
            ->call('setTab', 'members')->assertSee($author->email)
            ->call('setTab', 'ideas')->assertSee('Very private plan')->assertSee('Private')
            ->call('setTab', 'challenges')->assertOk();
    }

    public function test_make_and_remove_admin_and_remove_a_member(): void
    {
        $this->withSession(['super_admin' => true]);
        $m = User::where('is_admin', false)->first();

        Livewire::test(Portal::class)->call('toggleAdmin', $m->id)->assertSee('now an Executive admin');
        $this->assertTrue($m->fresh()->is_admin);
        Livewire::test(Portal::class)->call('toggleAdmin', $m->id)->assertSee('no longer an admin');
        $this->assertFalse($m->fresh()->is_admin);

        $idea = Idea::create(['num' => Idea::nextNumber(), 'user_id' => $m->id, 'title' => 'Goes with them', 'summary' => 's', 'body' => 'b', 'status' => 'Idea']);
        Livewire::test(Portal::class)->call('removeMember', $m->id)->assertSee('was removed');
        $this->assertNull(User::find($m->id));
        $this->assertNull(Idea::find($idea->id));

        $other = Idea::first();
        Livewire::test(Portal::class)->call('removeIdea', $other->id);
        $this->assertNull(Idea::find($other->id));
    }

    public function test_actions_are_refused_once_the_super_session_is_gone(): void
    {
        $this->withSession(['super_admin' => true]);
        $m = User::where('is_admin', false)->first();
        $page = Livewire::test(Portal::class);
        session()->forget('super_admin');
        $page->call('toggleAdmin', $m->id)->assertForbidden();
        $this->assertFalse($m->fresh()->is_admin);
    }

    public function test_super_admin_adds_members_by_email_or_phone_with_a_one_time_password(): void
    {
        $this->withSession(['super_admin' => true]);

        $page = Livewire::test(Portal::class)->set('tab', 'members')->set('newName', 'Rudo Moyo')->set('newEmail', 'Rudo@Example.com')->set('newPhone', '077 123 4567')->call('addMember');
        $page->assertHasNoErrors()->assertSee('was added')->assertSee('only now');
        $u = User::where('email', 'rudo@example.com')->firstOrFail();
        $this->assertSame('263771234567', $u->phone);
        $this->assertFalse($u->is_admin);
        $this->assertTrue($u->must_change_password);
        $shown = $page->get('created')['password'];
        $this->assertSame(10, strlen($shown));
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check($shown, $u->password));
        $page->call('dismissCreated')->assertSet('created', null);

        // phone only, chosen password, as an Executive admin; they can sign in with the phone and it forces a change
        Livewire::test(Portal::class)->set('newName', 'Tendai Phiri')->set('newPhone', '0772223344')->set('newRole', 'admin')->set('newPassword', 'chosen-pass-1')->call('addMember')->assertHasNoErrors();
        $t = User::where('phone', '263772223344')->firstOrFail();
        $this->assertNull($t->email);
        $this->assertTrue($t->is_admin);
        \Livewire\Livewire::test(\App\Livewire\Auth\Login::class)->set('email', '0772223344')->set('password', 'chosen-pass-1')->call('signIn')->assertRedirect();
        $this->assertAuthenticatedAs($t);
        $this->get('/')->assertRedirect(route('password.change'));
    }

    public function test_adding_a_member_refuses_duplicates_and_empty_contact_details(): void
    {
        $this->withSession(['super_admin' => true]);
        $existing = User::whereNotNull('email')->first();
        $existing->update(['phone' => '263779990001']);
        $count = User::count();

        Livewire::test(Portal::class)->set('newName', 'No Contact')->call('addMember')->assertHasErrors('newEmail');
        Livewire::test(Portal::class)->set('newName', 'Dup Email')->set('newEmail', strtoupper($existing->email))->call('addMember')->assertHasErrors('newEmail');
        Livewire::test(Portal::class)->set('newName', 'Dup Phone')->set('newPhone', '0779990001')->call('addMember')->assertHasErrors('newPhone');
        Livewire::test(Portal::class)->set('newName', 'Bad Email')->set('newEmail', 'nope')->call('addMember')->assertHasErrors('newEmail');
        Livewire::test(Portal::class)->set('newName', 'Short Pass')->set('newEmail', 'sp@example.com')->set('newPassword', 'short')->call('addMember')->assertHasErrors('newPassword');
        $this->assertSame($count, User::count());
    }

    public function test_super_admin_links_and_changes_a_members_phone_and_email(): void
    {
        $this->withSession(['super_admin' => true]);
        $a = User::where('is_admin', false)->first();
        $b = User::where('is_admin', false)->where('id', '!=', $a->id)->first();
        $b->update(['phone' => '263778880001']);

        $page = Livewire::test(Portal::class)->set('tab', 'members')->call('editContact', $a->id)->assertSet('editEmail', $a->email);
        $page->set('editEmail', 'NEW.Mail@Example.com')->set('editPhone', '077 888 0002')->call('saveContact')->assertHasNoErrors()->assertSee('contact details were saved')->assertSet('editingId', null);
        $this->assertSame('new.mail@example.com', $a->fresh()->email);
        $this->assertSame('263778880002', $a->fresh()->phone);

        // the number can now be used to sign in on the web
        $a->update(['password' => \Illuminate\Support\Facades\Hash::make('link-pass-123'), 'must_change_password' => false]);
        \Livewire\Livewire::test(\App\Livewire\Auth\Login::class)->set('email', '0778880002')->set('password', 'link-pass-123')->call('signIn')->assertRedirect();
        $this->assertAuthenticatedAs($a);
    }

    public function test_changing_contact_details_refuses_duplicates_empty_and_invalid_values(): void
    {
        $this->withSession(['super_admin' => true]);
        $a = User::where('is_admin', false)->first();
        $b = User::where('is_admin', false)->where('id', '!=', $a->id)->first();
        $b->update(['phone' => '263778880003']);
        $before = [$a->email, $a->phone];

        Livewire::test(Portal::class)->call('editContact', $a->id)->set('editPhone', '0778880003')->call('saveContact')->assertHasErrors('editPhone');
        Livewire::test(Portal::class)->call('editContact', $a->id)->set('editEmail', strtoupper($b->email))->call('saveContact')->assertHasErrors('editEmail');
        Livewire::test(Portal::class)->call('editContact', $a->id)->set('editEmail', 'bad')->call('saveContact')->assertHasErrors('editEmail');
        Livewire::test(Portal::class)->call('editContact', $a->id)->set('editEmail', '')->set('editPhone', '')->call('saveContact')->assertHasErrors('editEmail');
        Livewire::test(Portal::class)->call('editContact', $a->id)->set('editPhone', 'abc')->call('saveContact')->assertHasErrors('editPhone');
        $this->assertSame($before, [$a->fresh()->email, $a->fresh()->phone]);

        // keeping their own details is fine, and a phone can be cleared while an email stays
        Livewire::test(Portal::class)->call('editContact', $a->id)->set('editPhone', '')->call('saveContact')->assertHasNoErrors();
        $this->assertNull($a->fresh()->phone);
    }
}
