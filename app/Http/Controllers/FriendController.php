<?php

namespace App\Http\Controllers;

use App\Models\FriendRequest;
use App\Models\Friendship;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FriendController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $friends = $request->user()->friends()
            ->select('users.id', 'users.name', 'users.username', 'users.city')
            ->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'username' => $u->username,
                'avatar_url' => $u->avatar_url,
                'city' => $u->city,
            ]);

        return response()->json($friends);
    }

    public function requests(Request $request): JsonResponse
    {
        $requests = $request->user()->friendRequestsReceived()
            ->with('sender:id,name,username')
            ->where('status', 'pending')
            ->latest()
            ->get()
            ->map(fn (FriendRequest $r) => [
                'id' => $r->id,
                'sender' => [
                    'id' => $r->sender->id,
                    'name' => $r->sender->name,
                    'username' => $r->sender->username,
                    'avatar_url' => $r->sender->avatar_url,
                ],
                'created_at' => $r->created_at,
            ]);

        return response()->json($requests);
    }

    public function sendRequest(Request $request, User $user): JsonResponse
    {
        $me = $request->user();

        if ($user->id === $me->id) {
            return response()->json(['message' => 'cannot_friend_self'], 422);
        }

        if (Friendship::where('user_id', $me->id)->where('friend_id', $user->id)->exists()) {
            return response()->json(['message' => 'already_friends'], 422);
        }

        $existing = FriendRequest::where('sender_id', $me->id)
            ->where('recipient_id', $user->id)
            ->where('status', 'pending')
            ->first();

        if ($existing) {
            return response()->json(['message' => 'request_already_sent'], 422);
        }

        // If they already requested us, treat this as an accept.
        $reverse = FriendRequest::where('sender_id', $user->id)
            ->where('recipient_id', $me->id)
            ->where('status', 'pending')
            ->first();

        if ($reverse) {
            $this->accept($reverse);

            return response()->json(['message' => 'accepted', 'friend' => $user->id]);
        }

        FriendRequest::create([
            'sender_id' => $me->id,
            'recipient_id' => $user->id,
        ]);

        return response()->json(['message' => 'sent'], 201);
    }

    public function respond(Request $request, FriendRequest $friendRequest): JsonResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['accept', 'decline'])],
        ]);

        abort_unless($friendRequest->recipient_id === $request->user()->id, 403);

        if (! $friendRequest->isPending()) {
            return response()->json(['message' => 'already_resolved'], 422);
        }

        if ($data['action'] === 'accept') {
            $this->accept($friendRequest);

            return response()->json(['message' => 'accepted']);
        }

        $friendRequest->update(['status' => 'declined']);

        return response()->json(['message' => 'declined']);
    }

    public function suggestions(Request $request): JsonResponse
    {
        $me = $request->user();

        $friendIds = $me->friends()->pluck('users.id');
        $pendingIds = FriendRequest::where('status', 'pending')
            ->where(fn ($q) => $q->where('sender_id', $me->id)->orWhere('recipient_id', $me->id))
            ->get(['sender_id', 'recipient_id'])
            ->flatMap(fn ($r) => [$r->sender_id, $r->recipient_id]);

        $suggestions = User::whereNotIn('id', $friendIds->merge($pendingIds)->push($me->id))
            ->whereNotNull('email')
            ->when($me->city, fn ($q) => $q->orderByRaw('city = ? desc', [$me->city]))
            ->limit(20)
            ->get(['id', 'name', 'username', 'city']);

        return response()->json($suggestions->map(fn (User $u) => [
            'id' => $u->id,
            'name' => $u->name,
            'username' => $u->username,
            'avatar_url' => $u->avatar_url,
            'city' => $u->city,
        ]));
    }

    private function accept(FriendRequest $request): void
    {
        $request->update(['status' => 'accepted']);

        Friendship::firstOrCreate([
            'user_id' => $request->sender_id,
            'friend_id' => $request->recipient_id,
        ]);

        Friendship::firstOrCreate([
            'user_id' => $request->recipient_id,
            'friend_id' => $request->sender_id,
        ]);
    }
}
