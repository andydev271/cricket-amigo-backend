<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Models\TeamInvite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TeamInviteController extends Controller
{
    /** Managers mint invite tokens; the app turns them into URL/QR links. */
    public function store(Request $request, Team $team): JsonResponse
    {
        abort_unless($team->isManager($request->user()), 403);

        $data = $request->validate([
            'role' => ['sometimes', Rule::in(Team::INVITABLE_ROLES)],
            'max_uses' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'expires_in_hours' => ['nullable', 'integer', 'min:1', 'max:8760'],
        ]);

        $invite = TeamInvite::mint();
        $invite->forceFill([
            'team_id' => $team->id,
            'created_by' => $request->user()->id,
            'role' => $data['role'] ?? 'player',
            'max_uses' => $data['max_uses'] ?? null,
            'expires_at' => isset($data['expires_in_hours'])
                ? now()->addHours($data['expires_in_hours'])
                : now()->addDays(7),
        ])->save();

        return response()->json($this->payload($invite), 201);
    }

    /** Public preview — what the join screen shows before accepting. */
    public function show(string $token): JsonResponse
    {
        $invite = TeamInvite::where('token', $token)->with('team')->firstOrFail();

        return response()->json([
            'usable' => $invite->isUsable(),
            'team' => [
                'name' => $invite->team->name,
                'city' => $invite->team->city,
                'member_count' => $invite->team->memberships()->count(),
            ],
            'role' => $invite->role,
            'expires_at' => $invite->expires_at,
        ]);
    }

    /** Redeem — adds the user to the team with the invite's preset role. */
    public function accept(Request $request, string $token): JsonResponse
    {
        $invite = TeamInvite::where('token', $token)->with('team')->firstOrFail();
        abort_unless($invite->isUsable(), 410, 'invite expired or fully used');

        $member = $invite->redeem($request->user());

        return response()->json([
            'joined' => true,
            'team_id' => $invite->team_id,
            'team' => $invite->team->name,
            'role' => $member->role,
        ]);
    }

    private function payload(TeamInvite $invite): array
    {
        return [
            'token' => $invite->token,
            'role' => $invite->role,
            'max_uses' => $invite->max_uses,
            'uses' => $invite->uses,
            'expires_at' => $invite->expires_at,
            // Mobile builds cricketamigo://join/{token} + https fallback links.
            'path' => "/join/{$invite->token}",
        ];
    }
}
