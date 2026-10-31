<?php

use App\Models\CricketMatch;
use App\Models\Innings;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

function makeTeam(string $name, User $owner): Team
{
    $team = Team::create(['name' => $name, 'created_by' => $owner->id]);
    \App\Models\TeamMember::create(['team_id' => $team->id, 'user_id' => $owner->id, 'role' => 'owner']);

    return $team;
}

/** Finish a fixture match quickly by forcing innings + result. */
function completeFixture(CricketMatch $m, string $winner, int $runsA = 100, int $runsB = 80): void
{
    $m->innings()->create(['number' => 1, 'batting_side' => 'a', 'runs' => $runsA, 'wickets' => 5, 'legal_balls' => 60]);
    $m->innings()->create(['number' => 2, 'batting_side' => 'b', 'runs' => $runsB, 'wickets' => 8, 'legal_balls' => 60, 'target' => $runsA + 1, 'status' => 'completed']);
    $m->innings()->where('number', 1)->update(['status' => 'completed']);
    $m->forceFill([
        'status' => 'completed',
        'result' => ['winner' => $winner, 'text' => 'done'],
    ])->save();
}

it('creates a tournament and registers teams', function () {
    $org = User::factory()->create();
    $t = $this->actingAs($org)->postJson('/api/tournaments', [
        'name' => 'São Paulo T20 Cup', 'city' => 'São Paulo', 'overs_limit' => 10,
    ]);
    $t->assertCreated();
    $id = $t->json('id');

    $this->actingAs($org)->postJson("/api/tournaments/{$id}/teams", ['name' => 'Lions'])->assertCreated();
    $this->actingAs($org)->postJson("/api/tournaments/{$id}/teams", ['name' => 'Tigers'])->assertCreated();

    // duplicate blocked
    $this->actingAs($org)->postJson("/api/tournaments/{$id}/teams", ['name' => 'Lions'])->assertStatus(409);

    expect($t->json('teams_count'))->toBeNull(); // count lives on show
    $detail = $this->actingAs($org)->getJson("/api/tournaments/{$id}")->json();
    expect($detail['teams'])->toHaveCount(2)
        ->and($detail['is_organizer'])->toBeTrue();
});

it('generates round-robin fixtures covering every pair once', function () {
    $org = User::factory()->create();
    $t = $this->actingAs($org)->postJson('/api/tournaments', ['name' => 'Cup']);
    $id = $t->json('id');

    foreach (['A', 'B', 'C', 'D'] as $n) {
        $this->actingAs($org)->postJson("/api/tournaments/{$id}/teams", ['name' => $n]);
    }

    $res = $this->actingAs($org)->postJson("/api/tournaments/{$id}/fixtures");
    $res->assertCreated();
    expect($res->json('fixtures'))->toBe(6); // C(4,2)

    $detail = $this->actingAs($org)->getJson("/api/tournaments/{$id}")->json();
    $pairs = collect($detail['fixtures'])->map(fn ($f) => collect([$f['team_a'], $f['team_b']])->sort()->join('-'));
    expect($pairs->unique())->toHaveCount(6);
});

it('computes standings with points and NRR', function () {
    $org = User::factory()->create();
    $t = $this->actingAs($org)->postJson('/api/tournaments', ['name' => 'Cup', 'overs_limit' => 10]);
    $id = $t->json('id');
    foreach (['A', 'B', 'C'] as $n) {
        $this->actingAs($org)->postJson("/api/tournaments/{$id}/teams", ['name' => $n]);
    }
    $this->actingAs($org)->postJson("/api/tournaments/{$id}/fixtures");

    $tournament = Tournament::find($id);
    $fixture = $tournament->fixtures()->with('match')->first();
    completeFixture($fixture->match, 'a', 100, 80);

    $rows = $this->actingAs($org)->getJson("/api/tournaments/{$id}/standings")->json();
    expect($rows)->toHaveCount(3);

    $winner = collect($rows)->firstWhere('w', 1);
    $loser = collect($rows)->firstWhere('l', 1);
    expect($winner['pts'])->toBe(2)->and($winner['nrr'])->toBeGreaterThan(0);
    expect($loser['nrr'])->toBeLessThan(0);
    expect($winner['name'])->toBe($fixture->match->team_a_name);
});

it('toggles follow on a team', function () {
    $user = User::factory()->create();
    $team = makeTeam('Lions', User::factory()->create());

    $this->actingAs($user)->postJson('/api/follows', ['type' => 'team', 'id' => $team->id])
        ->assertCreated()->assertJsonPath('following', true);
    $this->actingAs($user)->postJson('/api/follows', ['type' => 'team', 'id' => $team->id])
        ->assertOk()->assertJsonPath('following', false);
});

it('posts to the Looking For board and closes it', function () {
    $user = User::factory()->create();
    $post = $this->actingAs($user)->postJson('/api/looking-for', [
        'kind' => 'seeking_team', 'city' => 'São Paulo', 'text' => 'Batsman looking for weekend games',
    ]);
    $post->assertCreated();

    $board = $this->actingAs($user)->getJson('/api/looking-for?kind=seeking_team')->json();
    expect($board['data'])->toHaveCount(1)->and($board['data'][0]['mine'])->toBeTrue();

    $this->actingAs($user)->postJson("/api/looking-for/{$post->json('id')}/close")->assertOk();
    expect($this->actingAs($user)->getJson('/api/looking-for')->json('data'))->toHaveCount(0);
});

it('sends a team challenge and accept creates a match', function () {
    $ownerA = User::factory()->create();
    $ownerB = User::factory()->create();
    $teamA = makeTeam('Lions', $ownerA);
    $teamB = makeTeam('Tigers', $ownerB);

    $req = $this->actingAs($ownerA)->postJson("/api/teams/{$teamA->id}/challenges", [
        'to_team_id' => $teamB->id, 'message' => 'friendly T10?',
    ]);
    $req->assertCreated();

    $list = $this->actingAs($ownerB)->getJson('/api/match-requests')->json();
    expect($list['data'][0]['can_respond'])->toBeTrue();

    $res = $this->actingAs($ownerB)->postJson("/api/match-requests/{$req->json('id')}/respond", ['action' => 'accept']);
    $res->assertOk()->assertJsonPath('status', 'accepted');
    expect($res->json('match_id'))->not->toBeNull();

    $match = CricketMatch::find($res->json('match_id'));
    expect($match->team_a_name)->toBe('Lions')->and($match->players()->count())->toBeGreaterThanOrEqual(2);
});

it('builds leaderboards from deliveries of registered users', function () {
    $scorer = User::factory()->create();
    $batter = User::factory()->create();

    $res = $this->actingAs($scorer)->postJson('/api/matches', [
        'team_a' => ['name' => 'Lions', 'players' => [['name' => 'A1'], ['name' => 'A2'], ['name' => 'A3']]],
        'team_b' => ['name' => 'Tigers', 'players' => [['name' => 'B1'], ['name' => 'B2'], ['name' => 'B3']]],
    ]);
    $match = $res->json();
    // attach the batter's match_player row to their user account
    $mp = \App\Models\MatchPlayer::where('match_id', $match['id'])->where('name', 'A1')->first();
    $mp->forceFill(['user_id' => $batter->id])->save();

    $this->actingAs($scorer)->postJson("/api/matches/{$match['id']}/start");
    $inn = $this->actingAs($scorer)->getJson("/api/matches/{$match['id']}")->json('innings')[0];

    $p = collect($match['players'])->keyBy('name');
    $this->actingAs($scorer)->postJson("/api/matches/{$match['id']}/innings/{$inn['id']}/balls", [
        'striker_id' => $p['A1']['id'], 'non_striker_id' => $p['A2']['id'],
        'bowler_id' => $p['B1']['id'], 'bat_runs' => 6, 'extra_type' => 'none',
    ]);

    $lb = $this->actingAs($scorer)->getJson('/api/leaderboards')->json();
    $entry = collect($lb['batting'])->firstWhere('user.id', $batter->id);
    expect($entry['runs'])->toBe(6);
});
