<?php

it('serves versioned coaching packs publicly', function () {
    $res = $this->getJson('/api/coaching-packs')->assertOk();

    $res->assertJsonStructure([
        'packs' => [
            '*' => ['version', 'discipline', 'phases', 'metrics', 'checks'],
        ],
        'latest',
    ]);

    $latest = $res->json('latest');
    expect($latest)->toHaveKeys(['batting', 'bowling', 'fielding', 'keeping']);

    foreach ($res->json('packs') as $pack) {
        expect($pack['phases'])->toHaveKeys(['setup', 'action', 'contact', 'recovery'])
            ->and($pack['metrics'])->not->toBeEmpty()
            ->and($pack['checks'])->not->toBeEmpty();
    }
});
