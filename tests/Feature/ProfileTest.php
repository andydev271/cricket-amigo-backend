<?php

use App\Models\User;
use Illuminate\Support\Facades\Storage;

it('returns authenticated user profile', function () {
    $user = User::factory()->create(['username' => 'andydev']);

    $this->actingAs($user)
        ->getJson('/api/me')
        ->assertOk()
        ->assertJsonPath('username', 'andydev')
        ->assertJsonPath('is_guest', false);
});

it('updates profile fields', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->putJson('/api/me', [
            'city' => 'São Paulo',
            'country' => 'BR',
            'roles' => ['player', 'coach'],
            'interests' => ['batting', 'keeping'],
        ])->assertOk()
        ->assertJsonPath('city', 'São Paulo')
        ->assertJsonPath('country', 'BR');

    expect($user->fresh()->interests)->toBe(['batting', 'keeping']);
});

it('validates locale against supported list', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->putJson('/api/me/locale', ['locale' => 'pt-BR'])->assertOk();
    $this->actingAs($user)->putJson('/api/me/locale', ['locale' => 'xx'])->assertUnprocessable();
});

it('uploads an avatar', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/me/avatar', [
            'avatar' => \Illuminate\Http\UploadedFile::fake()->image('me.jpg', 400, 400),
        ])->assertOk();

    expect($user->fresh()->avatar_path)->not->toBeNull();
    Storage::disk('public')->assertExists($user->fresh()->avatar_path);
});

it('requires authentication', function () {
    $this->getJson('/api/me')->assertUnauthorized();
});
