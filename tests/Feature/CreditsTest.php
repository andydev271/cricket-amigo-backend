<?php

use App\Models\ClipUpload;
use App\Models\CreditTransaction;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('grants rewarded-ad credits and tracks balance', function () {
    $user = User::factory()->create(['credits' => 0]);

    $this->actingAs($user)
        ->postJson('/api/credits/earn', ['kind' => 'rewarded_ad', 'ref' => 'ssv-1'])
        ->assertCreated()
        ->assertJsonPath('granted', 10)
        ->assertJsonPath('balance', 10);

    expect(CreditTransaction::count())->toBe(1);
});

it('rejects replayed reward refs', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson('/api/credits/earn', ['kind' => 'rewarded_ad', 'ref' => 'ssv-x'])->assertCreated();
    $this->actingAs($user)->postJson('/api/credits/earn', ['kind' => 'rewarded_ad', 'ref' => 'ssv-x'])->assertStatus(409);
});

it('never lets the balance go negative', function () {
    $user = User::factory()->create(['credits' => 5]);

    expect(fn () => CreditTransaction::record($user, -10, 'spend_stream'))->toThrow(RuntimeException::class);
    expect($user->fresh()->credits)->toBe(5);
});

it('returns balance and recent history', function () {
    $user = User::factory()->create(['credits' => 0]);
    CreditTransaction::record($user, 10, 'rewarded_ad');
    CreditTransaction::record($user, -3, 'spend_stream', 'stream-9');

    $this->actingAs($user)->getJson('/api/credits')
        ->assertOk()
        ->assertJsonPath('balance', 7)
        ->assertJsonPath('recent.0.amount', -3);
});

it('uploads a clip while free_mode is on', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/me/clips', [
            'clip' => UploadedFile::fake()->create('rep.mp4', 1024, 'video/mp4'),
            'discipline' => 'batting',
        ])->assertCreated()
        ->assertJsonPath('discipline', 'batting');

    expect(ClipUpload::count())->toBe(1)
        ->and(ClipUpload::first()->getFirstMedia('clip'))->not->toBeNull();
});
