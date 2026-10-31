<?php

use App\Models\Team;
use App\Models\TeamInvite;
use App\Models\User;

it('creates a team and makes the creator owner', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/teams', ['name' => 'São Paulo Strikers', 'city' => 'São Paulo', 'country' => 'BR'])
        ->assertCreated()
        ->assertJsonPath('my_role', 'owner')
        ->assertJsonPath('member_count', 1);

    expect(Team::first()->roleOf($user))->toBe('owner');
});

it('lists only my teams', function () {
    [$a, $b] = User::factory(2)->create()->all();
    $mine = Team::create(['name' => 'Mine', 'created_by' => $a->id]);
    $mine->members()->attach($a->id, ['role' => 'owner']);
    $other = Team::create(['name' => 'Theirs', 'created_by' => $b->id]);
    $other->members()->attach($b->id, ['role' => 'owner']);

    $this->actingAs($a)->getJson('/api/teams')
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.name', 'Mine');
});

it('creates and redeems an invite', function () {
    [$owner, $joiner] = User::factory(2)->create()->all();
    $team = Team::create(['name' => 'Invite FC', 'created_by' => $owner->id]);
    $team->members()->attach($owner->id, ['role' => 'owner']);

    $invite = $this->actingAs($owner)
        ->postJson("/api/teams/{$team->id}/invites", ['role' => 'player'])
        ->assertCreated()
        ->json('token');

    // Public preview shows team + role without auth.
    $this->getJson("/api/invites/{$invite}")
        ->assertOk()
        ->assertJsonPath('usable', true)
        ->assertJsonPath('team.name', 'Invite FC')
        ->assertJsonPath('role', 'player');

    $this->actingAs($joiner)
        ->postJson("/api/invites/{$invite}/accept")
        ->assertOk()
        ->assertJsonPath('joined', true)
        ->assertJsonPath('role', 'player');

    expect($team->roleOf($joiner))->toBe('player');
});

it('rejects invites from non-managers', function () {
    [$owner, $player] = User::factory(2)->create()->all();
    $team = Team::create(['name' => 'Strict XI', 'created_by' => $owner->id]);
    $team->members()->attach($owner->id, ['role' => 'owner']);
    $team->members()->attach($player->id, ['role' => 'player']);

    $this->actingAs($player)
        ->postJson("/api/teams/{$team->id}/invites")
        ->assertForbidden();
});

it('enforces max_uses on invites', function () {
    [$owner, $u2, $u3] = User::factory(3)->create()->all();
    $team = Team::create(['name' => 'Once CC', 'created_by' => $owner->id]);
    $team->members()->attach($owner->id, ['role' => 'owner']);
    $invite = TeamInvite::mint();
    $invite->forceFill([
        'team_id' => $team->id,
        'created_by' => $owner->id,
        'max_uses' => 1,
    ])->save();

    $this->actingAs($u2)->postJson("/api/invites/{$invite->token}/accept")->assertOk();
    $this->actingAs($u3)->postJson("/api/invites/{$invite->token}/accept")->assertStatus(410);
});

it('lets members leave but not the owner', function () {
    [$owner, $player] = User::factory(2)->create()->all();
    $team = Team::create(['name' => 'Leave XI', 'created_by' => $owner->id]);
    $team->members()->attach($owner->id, ['role' => 'owner']);
    $team->members()->attach($player->id, ['role' => 'player']);

    $this->actingAs($player)->postJson("/api/teams/{$team->id}/leave")->assertOk();
    expect($team->roleOf($player))->toBeNull();

    $this->actingAs($owner)->postJson("/api/teams/{$team->id}/leave")->assertUnprocessable();
});

it('hides team detail from non-members', function () {
    [$owner, $outsider] = User::factory(2)->create()->all();
    $team = Team::create(['name' => 'Private CC', 'created_by' => $owner->id]);
    $team->members()->attach($owner->id, ['role' => 'owner']);

    $this->actingAs($outsider)->getJson("/api/teams/{$team->id}")->assertForbidden();
});
