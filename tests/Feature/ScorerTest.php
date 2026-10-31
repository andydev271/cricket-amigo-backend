<?php

use App\Events\BallScored;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

function makeMatch(TestCase $t, User $user, int $overs = 2): array
{
    $res = $t->actingAs($user)->postJson('/api/matches', [
        'team_a' => ['name' => 'Lions', 'players' => [
            ['name' => 'A1', 'is_captain' => true], ['name' => 'A2'], ['name' => 'A3'], ['name' => 'A4'],
        ]],
        'team_b' => ['name' => 'Tigers', 'players' => [
            ['name' => 'B1'], ['name' => 'B2'], ['name' => 'B3'], ['name' => 'B4'],
        ]],
        'overs_limit' => $overs,
        'toss_winner' => 'a',
        'toss_decision' => 'bat',
    ]);

    $res->assertCreated();
    $match = $res->json();

    return [$match, collect($match['players'])->keyBy('name')];
}

function inningsByStatus(array $match, string $status): ?array
{
    return collect($match['innings'])->firstWhere('status', $status);
}

function scoreBall(TestCase $t, User $user, int $matchId, Collection $p, array $ball = []): TestResponse
{
    $m = $t->actingAs($user)->getJson("/api/matches/{$matchId}")->json();
    $inn = inningsByStatus($m, 'in_progress');

    $res = $t->actingAs($user)->postJson("/api/matches/{$matchId}/innings/{$inn['id']}/balls", $ball + [
        'striker_id' => $p['A1']['id'],
        'non_striker_id' => $p['A2']['id'],
        'bowler_id' => $p['B1']['id'],
        'bat_runs' => 0,
        'extra_type' => 'none',
    ]);

    return $res;
}

it('creates a match with two XIs', function () {
    $user = User::factory()->create();
    [$match] = makeMatch($this, $user);

    expect($match['status'])->toBe('scheduled')
        ->and($match['players'])->toHaveCount(8)
        ->and($match['overs_limit'])->toBe(2);
});

it('starts a match and creates innings 1 honoring the toss', function () {
    $user = User::factory()->create();
    [$match] = makeMatch($this, $user);

    $res = $this->actingAs($user)->postJson("/api/matches/{$match['id']}/start");
    $res->assertOk();
    expect(collect($res->json('innings'))->firstWhere('number', 1)['batting'])->toBe('a')
        ->and($res->json('status'))->toBe('live');
});

it('scores a legal ball and updates innings counters', function () {
    $user = User::factory()->create();
    [$match, $p] = makeMatch($this, $user);
    $this->actingAs($user)->postJson("/api/matches/{$match['id']}/start");

    $res = scoreBall($this, $user, $match['id'], $p, ['bat_runs' => 4]);

    $res->assertCreated();
    $inn = collect($res->json('score.innings'))->firstWhere('number', 1);
    expect($inn['runs'])->toBe(4)->and($inn['overs'])->toBe('0.1');
    expect($res->json('delivery.legal_ball'))->toBeTrue();
});

it('counts a wide as an extra run without a legal ball', function () {
    $user = User::factory()->create();
    [$match, $p] = makeMatch($this, $user);
    $this->actingAs($user)->postJson("/api/matches/{$match['id']}/start");

    $res = scoreBall($this, $user, $match['id'], $p, ['extra_type' => 'wide']);

    $inn = collect($res->json('score.innings'))->firstWhere('number', 1);
    expect($inn['runs'])->toBe(1)
        ->and($inn['overs'])->toBe('0.0')
        ->and($inn['extras']['wides'])->toBe(1);
    expect($res->json('delivery.legal_ball'))->toBeFalse();
});

it('records a wicket', function () {
    $user = User::factory()->create();
    [$match, $p] = makeMatch($this, $user);
    $this->actingAs($user)->postJson("/api/matches/{$match['id']}/start");

    $res = scoreBall($this, $user, $match['id'], $p, [
        'wicket_type' => 'bowled',
        'out_player_id' => $p['A1']['id'],
    ]);

    expect(collect($res->json('score.innings'))->firstWhere('number', 1)['wickets'])->toBe(1);
});

it('completes innings at the overs limit and opens the chase with a target', function () {
    $user = User::factory()->create();
    [$match, $p] = makeMatch($this, $user, overs: 1);
    $this->actingAs($user)->postJson("/api/matches/{$match['id']}/start");

    foreach (range(1, 6) as $i) {
        $res = scoreBall($this, $user, $match['id'], $p, ['bat_runs' => 2]);
    }

    $inn1 = collect($res->json('score.innings'))->firstWhere('number', 1);
    $inn2 = collect($res->json('score.innings'))->firstWhere('number', 2);
    expect($inn1['status'])->toBe('completed')->and($inn1['runs'])->toBe(12);
    expect($inn2['batting'])->toBe('Tigers')->and($inn2['target'])->toBe(13);
});

it('finishes the match with a result when the chase ends', function () {
    $user = User::factory()->create();
    [$match, $p] = makeMatch($this, $user, overs: 1);
    $this->actingAs($user)->postJson("/api/matches/{$match['id']}/start");

    // innings 1: 6 dot balls → target = 1
    foreach (range(1, 6) as $i) {
        scoreBall($this, $user, $match['id'], $p);
    }

    // innings 2: Tigers need 1 — a single wins it
    $m = $this->actingAs($user)->getJson("/api/matches/{$match['id']}")->json();
    $inn = collect($m['innings'])->firstWhere('number', 2);
    $res = $this->actingAs($user)->postJson("/api/matches/{$match['id']}/innings/{$inn['id']}/balls", [
        'striker_id' => $p['B1']['id'], 'non_striker_id' => $p['B2']['id'],
        'bowler_id' => $p['A1']['id'], 'bat_runs' => 1, 'extra_type' => 'none',
    ]);

    expect($res->json('score.status'))->toBe('completed');
    expect($res->json('score.result.text'))->toContain('Tigers');
    expect($res->json('score.result.winner'))->toBe('b');
});

it('undoes the last ball and recomputes counters', function () {
    $user = User::factory()->create();
    [$match, $p] = makeMatch($this, $user);
    $this->actingAs($user)->postJson("/api/matches/{$match['id']}/start");

    scoreBall($this, $user, $match['id'], $p, ['bat_runs' => 6]);
    scoreBall($this, $user, $match['id'], $p, ['extra_type' => 'wide']);

    $res = $this->actingAs($user)->postJson("/api/matches/{$match['id']}/undo");
    $res->assertOk();
    $inn = collect($res->json('score.innings'))->firstWhere('number', 1);
    expect($inn['runs'])->toBe(6)->and($inn['overs'])->toBe('0.1');
});

it('rejects balls from non-scorers and batting-side violations', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    [$match, $p] = makeMatch($this, $user);
    $this->actingAs($user)->postJson("/api/matches/{$match['id']}/start");
    $inn = $this->actingAs($user)->getJson("/api/matches/{$match['id']}")->json('innings')[0];

    $this->actingAs($other)->postJson("/api/matches/{$match['id']}/innings/{$inn['id']}/balls", [
        'striker_id' => $p['A1']['id'], 'non_striker_id' => $p['A2']['id'],
        'bowler_id' => $p['B1']['id'], 'bat_runs' => 1, 'extra_type' => 'none',
    ])->assertForbidden();

    $this->actingAs($user)->postJson("/api/matches/{$match['id']}/innings/{$inn['id']}/balls", [
        'striker_id' => $p['B1']['id'], 'non_striker_id' => $p['A2']['id'],
        'bowler_id' => $p['B2']['id'], 'bat_runs' => 1, 'extra_type' => 'none',
    ])->assertUnprocessable();
});

it('dispatches a broadcast event on every ball', function () {
    Event::fake([BallScored::class]);
    $user = User::factory()->create();
    [$match, $p] = makeMatch($this, $user);
    $this->actingAs($user)->postJson("/api/matches/{$match['id']}/start");

    scoreBall($this, $user, $match['id'], $p, ['bat_runs' => 1]);

    Event::assertDispatched(BallScored::class, fn ($e) => $e->match->id === $match['id']);
});

it('lets a team scorer score a linked-team match', function () {
    $owner = User::factory()->create();
    $scorer = User::factory()->create();
    $team = \App\Models\Team::create(['name' => 'Lions CC', 'created_by' => $owner->id]);
    \App\Models\TeamMember::create(['team_id' => $team->id, 'user_id' => $scorer->id, 'role' => 'scorer']);

    $res = $this->actingAs($owner)->postJson('/api/matches', [
        'team_a' => ['id' => $team->id, 'players' => [['name' => 'A1'], ['name' => 'A2'], ['name' => 'A3'], ['name' => 'A4']]],
        'team_b' => ['name' => 'Tigers', 'players' => [['name' => 'B1'], ['name' => 'B2'], ['name' => 'B3'], ['name' => 'B4']]],
        'overs_limit' => 2,
    ]);
    $res->assertCreated();
    $match = $res->json();
    expect($match['team_a']['name'])->toBe('Lions CC');

    $this->actingAs($scorer)->postJson("/api/matches/{$match['id']}/start")->assertOk();
});
