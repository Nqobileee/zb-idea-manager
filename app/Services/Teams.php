<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Team;
use App\Models\User;

/** Making and running teams. Every team gets a group chat that always matches its members. */
class Teams
{
    public function create(User $leader, string $name, ?string $about = null): Team
    {
        $team = Team::create(['user_id' => $leader->id, 'name' => trim($name), 'about' => trim((string) $about) ?: null]);
        $team->members()->attach($leader->id);
        Conversation::openGroup('team', $team->name, [$leader->id], ['team_id' => $team->id]);

        return $team;
    }

    public function update(Team $team, User $by, string $name, ?string $about = null): Team
    {
        abort_unless($team->canBeManagedBy($by), 403);
        $team->update(['name' => trim($name), 'about' => trim((string) $about) ?: null]);
        $team->groupChat?->update(['name' => $team->name]);

        return $team;
    }

    public function add(Team $team, User $by, User $member): void
    {
        abort_unless($team->canBeManagedBy($by), 403);
        if ($team->hasMember($member)) {
            return;
        }
        $team->members()->attach($member->id);
        $team->unsetRelation('members');
        $group = $team->groupChat;
        $group?->members()->syncWithoutDetaching([$member->id]);
        // The note also gives the new member an unread badge, which is how they find out.
        $group?->post($by, $by->id === $member->id ? "{$by->name} joined the team." : "{$by->name} added {$member->name} to the team.");
    }

    /** A leader (or executive) removes someone, or a member leaves. The leader cannot leave: delete the team instead. */
    public function remove(Team $team, User $by, User $member): void
    {
        abort_unless($team->canBeManagedBy($by) || $by->id === $member->id, 403);
        abort_if($member->id === $team->user_id, 422, 'The team leader cannot be removed. Delete the team instead.');
        $team->members()->detach($member->id);
        $team->unsetRelation('members');
        $team->groupChat?->members()->detach($member->id);
    }

    /** Ideas the team put forward are kept. The team's group chat goes with it. */
    public function delete(Team $team, User $by): void
    {
        abort_unless($team->canBeManagedBy($by), 403);
        $team->delete();
    }
}
