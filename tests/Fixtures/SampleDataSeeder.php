<?php

namespace Tests\Fixtures;

use App\Models\Activity;
use App\Models\Challenge;
use App\Models\Comment;
use App\Models\Conversation;
use App\Models\Idea;
use App\Models\IdeaFile;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

/**
 * TEST FIXTURE ONLY. Loads sample people, challenges, ideas and chats so tests have something to work with.
 * The real database starts empty (see DatabaseSeeder).
 * Timestamps in sample.json are hours relative to "now" (negative = in the past).
 */
class SampleDataSeeder extends Seeder
{
    public function run(): void
    {
        $d = json_decode(File::get(base_path('tests/Fixtures/data/sample.json')), true);
        $at = fn ($h) => now()->addMinutes((int) round($h * 60));

        $users = [];
        foreach ($d['users'] as $u) {
            $users[$u['key']] = User::create([
                'name' => $u['name'], 'email' => $u['email'], 'title' => $u['title'], 'dept' => $u['dept'],
                'bio' => $u['bio'], 'joined' => $u['joined'], 'is_admin' => (bool) $u['is_admin'], 'color' => $u['color'],
            ]);
        }
        $me = User::create([
            'name' => 'Tinashe Moyo', 'email' => 'tinashe.moyo@zb.co.zw', 'title' => 'Business Analyst',
            'dept' => 'Digital Banking', 'bio' => 'I like ideas that save a customer a trip to the branch.',
            'joined' => '2023', 'color' => '#0d4a36',
        ]);
        $users['me'] = $me;

        $challenges = [];
        foreach ($d['challenges'] as $c) {
            $challenges[$c['key']] = Challenge::create([
                'user_id' => $users[$c['by']]->id, 'title' => $c['title'], 'brief' => $c['brief'],
                'keywords' => $c['keywords'], 'deadline' => $c['deadline'], 'created_at' => $at($c['posted_h']),
            ]);
        }

        $others = collect($users)->except('me')->values();
        foreach ($d['ideas'] as $i) {
            $idea = Idea::create([
                'num' => $i['num'], 'user_id' => $users[$i['author']]->id,
                'challenge_id' => $i['challenge'] ? $challenges[$i['challenge']]->id : null,
                'title' => $i['title'], 'summary' => $i['summary'], 'body' => implode("\n\n", $i['body']),
                'status' => $i['status'], 'shares' => $i['shares'],
                'approved' => $i['approved'],
                'approved_by' => $i['approved'] ? $users[$i['approvedBy']]->id : null,
                'approved_at' => $i['approved'] ? $at($i['approved_h']) : null,
                'approval_note' => $i['approved'] ? 'Strong work. Treasury wants to test this for the December payday cycle. Can you join a call next week?' : null,
                'created_at' => $at($i['created_h']), 'updated_at' => $at($i['created_h']),
            ]);
            $idea->likers()->attach($others->where('id', '!=', $idea->user_id)->take(min($i['likes'], 7))->pluck('id'));
            foreach ($i['docs'] as $doc) {
                IdeaFile::create(['idea_id' => $idea->id, 'kind' => 'doc', 'name' => $doc['n'], 'size' => $doc['s']]);
            }
            foreach ($i['imgs'] as $img) {
                IdeaFile::create(['idea_id' => $idea->id, 'kind' => 'image', 'name' => basename($img), 'path' => 'images/ideas/'.basename($img)]);
            }
            foreach ($i['comments'] as $c) {
                Comment::create(['idea_id' => $idea->id, 'user_id' => $users[$c['u']]->id, 'body' => $c['t'], 'created_at' => $at($c['at_h']), 'updated_at' => $at($c['at_h'])]);
            }
        }

        foreach ($d['convs'] as $c) {
            $conv = Conversation::between($me, $users[$c['with']]);
            foreach ($c['msgs'] as $m) {
                Message::create([
                    'conversation_id' => $conv->id, 'user_id' => $users[$m['from']]->id, 'body' => $m['t'],
                    'created_at' => $at($m['at_h']), 'updated_at' => $at($m['at_h']),
                    'read_at' => $m['from'] === 'me' ? $at($m['at_h']) : null,
                ]);
            }
            $conv->update(['last_message_at' => $at(collect($c['msgs'])->max('at_h'))]);
        }

        $ideaByKey = Idea::all()->keyBy('num');
        $numOf = collect($d['ideas'])->keyBy('key');
        foreach ($d['notes'] as $n) {
            Activity::create([
                'user_id' => $me->id, 'type' => $n['type'], 'actor_id' => $users[$n['from']]->id,
                'idea_id' => isset($n['idea']) ? $ideaByKey[$numOf[$n['idea']]['num']]->id : null,
                'challenge_id' => isset($n['challenge']) ? $challenges[$n['challenge']]->id : null,
                'note' => $n['note'] ?? null, 'read_at' => $n['unread'] ? null : $at($n['at']),
                'created_at' => $at($n['at']), 'updated_at' => $at($n['at']),
            ]);
        }
    }
}
