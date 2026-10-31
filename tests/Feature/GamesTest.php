<?php

use App\Models\TriviaQuestion;
use App\Models\User;
use Tests\TestCase;

function seedTrivia(): void
{
    (new \Database\Seeders\TriviaSeeder())->run();
}

it('serves a localized daily trivia set without answers', function () {
    seedTrivia();
    $user = User::factory()->create(['locale' => 'pt-BR']);

    $res = $this->actingAs($user)->getJson('/api/games/trivia');
    $res->assertOk();
    $qs = $res->json('questions');
    expect($qs)->toHaveCount(5);
    expect($qs[0])->toHaveKeys(['id', 'question', 'options', 'difficulty']);
    expect($qs[0])->not->toHaveKey('answer_index');
    // pt-BR localization applied
    expect($qs[0]['question'])->not->toBeEmpty();
});

it('scores answers and credits on completion', function () {
    seedTrivia();
    $user = User::factory()->create();
    $qs = $this->actingAs($user)->getJson('/api/games/trivia')->json('questions');

    $correct = 0;
    foreach ($qs as $q) {
        $right = TriviaQuestion::find($q['id'])->answer_index;
        $res = $this->actingAs($user)->postJson('/api/games/trivia/answer', [
            'question_id' => $q['id'], 'choice' => $right,
        ]);
        $res->assertOk();
        if ($res->json('correct')) {
            $correct++;
        }
    }

    $last = $res->json();
    expect($last['finished'])->toBeTrue()->and($last['granted'])->toBe(10); // 5 × 2
    expect($user->fresh()->credits)->toBe(10);

    // replay same day is ignored
    $again = $this->actingAs($user)->postJson('/api/games/trivia/answer', [
        'question_id' => $qs[0]['id'], 'choice' => 0,
    ]);
    expect($again->json('already_answered'))->toBeTrue();
    expect($user->fresh()->credits)->toBe(10);
});

it('records a match pick and settles correct picks on completion', function () {
    seedTrivia();
    $scorer = User::factory()->create();
    $fan = User::factory()->create();

    $res = $this->actingAs($scorer)->postJson('/api/matches', [
        'team_a' => ['name' => 'Lions', 'players' => [['name' => 'A1'], ['name' => 'A2'], ['name' => 'A3']]],
        'team_b' => ['name' => 'Tigers', 'players' => [['name' => 'B1'], ['name' => 'B2'], ['name' => 'B3']]],
        'overs_limit' => 1,
    ]);
    $match = $res->json();
    $p = collect($match['players'])->keyBy('name');

    $this->actingAs($fan)->postJson("/api/matches/{$match['id']}/predict", ['pick' => 'a'])->assertCreated();

    $this->actingAs($scorer)->postJson("/api/matches/{$match['id']}/start");

    // innings 1: 6 balls of 2 runs → target 13
    foreach (range(1, 6) as $i) {
        $inn = $this->actingAs($scorer)->getJson("/api/matches/{$match['id']}")->json('innings');
        $live = collect($inn)->firstWhere('status', 'in_progress');
        $this->actingAs($scorer)->postJson("/api/matches/{$match['id']}/innings/{$live['id']}/balls", [
            'striker_id' => $p['A1']['id'], 'non_striker_id' => $p['A2']['id'],
            'bowler_id' => $p['B1']['id'], 'bat_runs' => 2, 'extra_type' => 'none',
        ]);
    }
    // innings 2: 6 dots → Lions win by 12 runs
    foreach (range(1, 6) as $i) {
        $inn = $this->actingAs($scorer)->getJson("/api/matches/{$match['id']}")->json('innings');
        $live = collect($inn)->firstWhere('status', 'in_progress');
        if (! $live) {
            break;
        }
        $this->actingAs($scorer)->postJson("/api/matches/{$match['id']}/innings/{$live['id']}/balls", [
            'striker_id' => $p['B1']['id'], 'non_striker_id' => $p['B2']['id'],
            'bowler_id' => $p['A1']['id'], 'bat_runs' => 0, 'extra_type' => 'none',
        ]);
    }

    $pick = $this->actingAs($fan)->getJson("/api/matches/{$match['id']}/predict")->json();
    expect($pick['correct'])->toBeTrue();
    expect($fan->fresh()->credits)->toBe(5);
});

it('stores game scores and returns personal bests', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson('/api/games/scores', ['game' => 'catch_it', 'score' => 12])->assertCreated();
    $this->actingAs($user)->postJson('/api/games/scores', ['game' => 'catch_it', 'score' => 18])->assertCreated();

    $top = $this->actingAs($user)->getJson('/api/games/scores?game=catch_it')->json();
    expect($top['my_best'])->toBe(18);
    expect(collect($top['top'])->firstWhere('user.id', $user->id)['best'])->toBe(18);
});
