<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:60'],
        ]);

        $users = User::whereNotNull('email')
            ->where(fn ($q) => $q
                ->where('username', 'like', "%{$data['q']}%")
                ->orWhere('name', 'like', "%{$data['q']}%"))
            ->limit(20)
            ->get(['id', 'name', 'username', 'avatar_path', 'city']);

        return response()->json($users->map(fn (User $u) => [
            'id' => $u->id,
            'name' => $u->name,
            'username' => $u->username,
            'avatar_url' => $u->avatar_url,
            'city' => $u->city,
        ]));
    }

    public function show(User $user): JsonResponse
    {
        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'avatar_url' => $user->avatar_url,
            'city' => $user->city,
            'country' => $user->country,
            'roles' => $user->roles,
            'interests' => $user->interests,
            'friend_count' => $user->friends()->count(),
        ]);
    }
}
