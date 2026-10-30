<?php

use App\Models\User;
use Spatie\Permission\Models\Role;

it('seeds the super admin with full access', function () {
    $admin = User::where('email', 'admin@cricketamigo.com')->first();

    expect($admin)->not->toBeNull()
        ->and($admin->hasRole('super-admin'))->toBeTrue()
        ->and($admin->can('manage users'))->toBeTrue()
        ->and($admin->can('manage flags'))->toBeTrue()
        ->and($admin->can('manage tournaments'))->toBeTrue()
        ->and($admin->can('moderate content'))->toBeTrue()
        ->and($admin->can('access admin'))->toBeTrue();
});

it('marks new device guests as members', function () {
    $this->postJson('/api/auth/device', ['device_id' => 'd-1']);

    expect(User::first()->hasRole('member'))->toBeTrue();
});

it('marks registered users as members', function () {
    $this->postJson('/api/auth/register', [
        'name' => 'Andy',
        'username' => 'andydev',
        'email' => 'andy@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertCreated();

    expect(User::where('username', 'andydev')->first()->hasRole('member'))->toBeTrue();
});

it('exposes roles and admin flag in payloads', function () {
    $admin = User::where('email', 'admin@cricketamigo.com')->first();

    $this->postJson('/api/auth/login', [
        'login' => 'admin@cricketamigo.com',
        'password' => 'Password@123#',
    ])->assertOk()
        ->assertJsonPath('user.is_admin', true)
        ->assertJsonPath('user.roles', fn ($roles) => in_array('super-admin', $roles));
});

it('super-admin passes gate before', function () {
    $admin = User::where('email', 'admin@cricketamigo.com')->first();

    expect(\Illuminate\Support\Facades\Gate::forUser($admin)->allows('anything-at-all'))->toBeTrue();
});
