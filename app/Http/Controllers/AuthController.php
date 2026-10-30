<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Instant-start session for a device. Creates a guest user on first use.
     */
    public function device(Request $request): JsonResponse
    {
        $data = $request->validate([
            'device_id' => ['required', 'string', 'max:100'],
            'platform' => ['nullable', 'string', 'max:20'],
            'app_version' => ['nullable', 'string', 'max:20'],
            'locale' => ['nullable', 'string', 'max:10'],
        ]);

        $device = Device::firstOrNew(['device_id' => $data['device_id']]);
        $device->fill([
            'platform' => $data['platform'] ?? null,
            'app_version' => $data['app_version'] ?? null,
        ]);

        $user = $device->user ?? tap(User::create([
            'name' => 'Guest',
            'locale' => $data['locale'] ?? 'en',
        ]))->assignRole('member')->refresh();

        $device->user()->associate($user);
        $device->save();

        return response()->json([
            'token' => $user->createToken('device')->plainTextToken,
            'user' => $this->userPayload($user),
        ], 201);
    }

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'username' => ['required', 'string', 'min:3', 'max:30', 'alpha_dash', Rule::unique('users', 'username')],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'confirmed', Password::min(8)],
            'locale' => ['nullable', 'string', 'max:10'],
            'device_id' => ['nullable', 'string', 'max:100'],
        ]);

        $user = tap(User::create([
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => $data['email'],
            'password' => $data['password'],
            'locale' => $data['locale'] ?? 'en',
            'onboarded_at' => now(),
        ]))->assignRole('member')->refresh();

        if (! empty($data['device_id'])) {
            Device::where('device_id', $data['device_id'])->update(['user_id' => $user->id]);
        }

        return response()->json([
            'token' => $user->createToken('api')->plainTextToken,
            'user' => $this->userPayload($user),
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
            'device_id' => ['nullable', 'string', 'max:100'],
        ]);

        $user = User::where('email', $data['login'])
            ->orWhere('username', $data['login'])
            ->first();

        if (! $user || ! $user->password || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'login' => __('auth.failed'),
            ]);
        }

        if (! empty($data['device_id'])) {
            Device::where('device_id', $data['device_id'])->update(['user_id' => $user->id]);
        }

        return response()->json([
            'token' => $user->createToken('api')->plainTextToken,
            'user' => $this->userPayload($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'ok']);
    }

    /**
     * Social sign-in (Google/Apple) — endpoint contract ready, provider
     * verification lands when app client IDs are configured.
     */
    public function social(Request $request, string $provider): JsonResponse
    {
        abort_unless(in_array($provider, ['google', 'apple']), 404);

        $request->validate(['id_token' => ['required', 'string']]);

        return response()->json([
            'message' => 'social_login_not_configured',
            'provider' => $provider,
        ], 501);
    }

    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'avatar_url' => $user->avatar_url,
            'locale' => $user->locale,
            'city' => $user->city,
            'country' => $user->country,
            'credits' => $user->credits,
            'roles' => $user->getRoleNames(),
            'is_admin' => $user->hasRole('super-admin'),
            'is_guest' => is_null($user->email),
            'onboarded' => ! is_null($user->onboarded_at),
        ];
    }
}
