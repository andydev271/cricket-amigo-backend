<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TeamController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $teams = Team::whereHas('memberships', fn ($q) => $q->where('user_id', $request->user()->id))
            ->withCount('memberships')
            ->latest()
            ->get()
            ->map(fn (Team $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'city' => $t->city,
                'country' => $t->country,
                'member_count' => $t->memberships_count,
                'my_role' => $t->roleOf($request->user()),
            ]);

        return response()->json($teams);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:60'],
            'city' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'size:2'],
        ]);

        $team = Team::create([...$data, 'created_by' => $request->user()->id]);
        TeamMember::create([
            'team_id' => $team->id,
            'user_id' => $request->user()->id,
            'role' => 'owner',
        ]);

        return response()->json($this->payload($team, $request->user()), 201);
    }

    public function show(Request $request, Team $team): JsonResponse
    {
        $role = $team->roleOf($request->user());
        abort_unless($role !== null, 403);

        $team->load('memberships.user');
        $members = $team->memberships->map(fn (TeamMember $m) => [
            'id' => $m->user->id,
            'name' => $m->user->name,
            'username' => $m->user->username,
            'avatar_url' => $m->user->avatar_url,
            'role' => $m->role,
            'joined_at' => $m->created_at,
        ])->values();

        return response()->json([
            ...$this->payload($team, $request->user()),
            'members' => $members,
        ]);
    }

    public function leave(Request $request, Team $team): JsonResponse
    {
        $member = $team->memberships()->where('user_id', $request->user()->id)->first();
        abort_unless($member, 404);
        abort_if($member->role === 'owner', 422, 'owner cannot leave — transfer or delete the team');

        $member->delete();

        return response()->json(['left' => true]);
    }

    public function removeMember(Request $request, Team $team, User $user): JsonResponse
    {
        abort_unless($team->isManager($request->user()), 403);

        $member = $team->memberships()->where('user_id', $user->id)->firstOrFail();
        abort_if($member->role === 'owner', 422, 'cannot remove the owner');
        abort_if(
            $member->role === 'admin' && $team->roleOf($request->user()) !== 'owner',
            422,
            'only the owner can remove an admin',
        );

        $member->delete();

        return response()->json(['removed' => $user->id]);
    }

    private function payload(Team $team, User $user): array
    {
        return [
            'id' => $team->id,
            'name' => $team->name,
            'city' => $team->city,
            'country' => $team->country,
            'member_count' => $team->memberships()->count(),
            'my_role' => $team->roleOf($user),
        ];
    }
}
