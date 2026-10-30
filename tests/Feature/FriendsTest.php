<?php

use App\Models\FriendRequest;
use App\Models\Friendship;
use App\Models\User;

it('searches users by username or name', function () {
    User::factory()->create(['name' => 'Andy Dev', 'username' => 'andydev', 'email' => 'a@b.co']);
    User::factory()->create(['name' => 'Bruno', 'username' => 'brunosp', 'email' => 'b@b.co']);
    $me = User::factory()->create();

    $this->actingAs($me)
        ->getJson('/api/users/search?q=andy')
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.username', 'andydev');
});

it('sends, accepts and lists friends', function () {
    [$a, $b] = User::factory()->count(2)->create();

    $this->actingAs($a)->postJson("/api/friends/request/{$b->id}")->assertCreated();

    $request = FriendRequest::first();
    expect($request->status)->toBe('pending');

    $this->actingAs($b)->getJson('/api/friends/requests')->assertJsonCount(1);
    $this->actingAs($b)->postJson("/api/friends/respond/{$request->id}", ['action' => 'accept'])->assertOk();

    expect(Friendship::count())->toBe(2);
    $this->actingAs($a)->getJson('/api/friends')->assertJsonCount(1);
    $this->actingAs($b)->getJson('/api/friends')->assertJsonCount(1);
});

it('auto-accepts when both sides request', function () {
    [$a, $b] = User::factory()->count(2)->create();

    $this->actingAs($a)->postJson("/api/friends/request/{$b->id}");
    $this->actingAs($b)->postJson("/api/friends/request/{$a->id}")->assertJsonPath('message', 'accepted');

    expect(Friendship::count())->toBe(2);
});

it('prevents duplicate pending requests', function () {
    [$a, $b] = User::factory()->count(2)->create();

    $this->actingAs($a)->postJson("/api/friends/request/{$b->id}");
    $this->actingAs($a)->postJson("/api/friends/request/{$b->id}")->assertJsonPath('message', 'request_already_sent');
});

it('only recipient can respond', function () {
    [$a, $b, $c] = User::factory()->count(3)->create();
    $request = FriendRequest::create(['sender_id' => $a->id, 'recipient_id' => $b->id]);

    $this->actingAs($c)
        ->postJson("/api/friends/respond/{$request->id}", ['action' => 'accept'])
        ->assertForbidden();
});
