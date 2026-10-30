<?php

use App\Models\Device;
use App\Models\User;

it('returns public flags', function () {
    $this->getJson('/api/flags')
        ->assertOk()
        ->assertJsonPath('free_mode', true)
        ->assertJsonPath('ads.enabled', false)
        ->assertJsonPath('ads.interstitial', false);
});

it('creates a guest user for a new device', function () {
    $this->postJson('/api/auth/device', [
        'device_id' => 'dev-1',
        'platform' => 'ios',
        'locale' => 'pt-BR',
    ])->assertCreated()
        ->assertJsonPath('user.is_guest', true)
        ->assertJsonPath('user.locale', 'pt-BR')
        ->assertJsonStructure(['token', 'user' => ['id', 'name']]);

    expect(Device::count())->toBe(1)
        ->and(User::count())->toBe(2); // device guest + seeded admin
});

it('reuses the user for an existing device', function () {
    $this->postJson('/api/auth/device', ['device_id' => 'dev-1']);
    $this->postJson('/api/auth/device', ['device_id' => 'dev-1']);

    expect(User::count())->toBe(2); // device guest + seeded admin
});

it('registers a user and links the device', function () {
    $this->postJson('/api/auth/device', ['device_id' => 'dev-1']);

    $this->postJson('/api/auth/register', [
        'name' => 'Andy',
        'username' => 'andydev',
        'email' => 'andy@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'device_id' => 'dev-1',
    ])->assertCreated()
        ->assertJsonPath('user.username', 'andydev')
        ->assertJsonPath('user.is_guest', false)
        ->assertJsonPath('user.onboarded', true);

    expect(Device::first()->user_id)->toBe(User::where('username', 'andydev')->first()->id);
});

it('logs in with username or email', function () {
    User::factory()->create([
        'username' => 'andydev',
        'email' => 'andy@example.com',
        'password' => 'password123',
    ]);

    $this->postJson('/api/auth/login', ['login' => 'andydev', 'password' => 'password123'])->assertOk();
    $this->postJson('/api/auth/login', ['login' => 'andy@example.com', 'password' => 'password123'])->assertOk();
    $this->postJson('/api/auth/login', ['login' => 'andydev', 'password' => 'wrong'])->assertUnprocessable();
});

it('rejects duplicate username and email', function () {
    User::factory()->create(['username' => 'andydev', 'email' => 'andy@example.com']);

    $this->postJson('/api/auth/register', [
        'name' => 'Andy',
        'username' => 'andydev',
        'email' => 'other@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertUnprocessable()->assertJsonValidationErrors('username');

    $this->postJson('/api/auth/register', [
        'name' => 'Andy',
        'username' => 'otherdev',
        'email' => 'andy@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertUnprocessable()->assertJsonValidationErrors('email');
});
