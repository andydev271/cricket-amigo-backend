<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class MeController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json($this->payload($request->user()));
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'username' => ['sometimes', 'string', 'min:3', 'max:30', 'alpha_dash', Rule::unique('users', 'username')->ignore($user->id)],
            'city' => ['sometimes', 'nullable', 'string', 'max:100'],
            'country' => ['sometimes', 'nullable', 'string', 'size:2'],
            'dominant_hand' => ['sometimes', 'nullable', Rule::in(['left', 'right'])],
            'roles' => ['sometimes', 'nullable', 'array'],
            'roles.*' => [Rule::in(['player', 'coach', 'scorer', 'fan'])],
            'interests' => ['sometimes', 'nullable', 'array'],
            'interests.*' => [Rule::in(['batting', 'bowling', 'fielding', 'keeping'])],
        ]);

        if (! $user->onboarded_at && (isset($data['interests']) || isset($data['roles']))) {
            $data['onboarded_at'] = now();
        }

        $user->update($data);

        return response()->json($this->payload($user));
    }

    public function updateLocale(Request $request): JsonResponse
    {
        $data = $request->validate([
            'locale' => ['required', Rule::in(config('cricketamigo.locales'))],
        ]);

        $request->user()->update(['locale' => $data['locale']]);

        return response()->json($this->payload($request->user()));
    }

    public function avatar(Request $request): JsonResponse
    {
        $data = $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $user = $request->user();
        $path = $data['avatar']->store('avatars', 'public');

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }

        $user->update(['avatar_path' => $path]);

        return response()->json($this->payload($user));
    }

    private function payload($user): array
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
            'dominant_hand' => $user->dominant_hand,
            'roles' => $user->roles,
            'interests' => $user->interests,
            'credits' => $user->credits,
            'is_guest' => is_null($user->email),
            'onboarded' => ! is_null($user->onboarded_at),
            'friend_count' => $user->friends()->count(),
        ];
    }
}
