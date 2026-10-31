<?php

namespace App\Http\Controllers;

use App\Models\Delivery;
use App\Models\Follow;
use App\Models\LookingForPost;
use App\Models\MatchRequest;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CommunityController extends Controller
{
    /** Toggle follow on a team or user. */
    public function follow(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:team,user'],
            'id' => ['required', 'integer'],
        ]);

        $model = $data['type'] === 'team'
            ? Team::findOrFail($data['id'])
            : User::findOrFail($data['id']);
        abort_if($model instanceof User && $model->id === $request->user()->id, 422, 'cannot follow yourself');

        $existing = Follow::where('user_id', $request->user()->id)
            ->whereMorphedTo('followable', $model)
            ->first();

        if ($existing) {
            $existing->delete();
            return response()->json(['following' => false]);
        }

        Follow::create([
            'user_id' => $request->user()->id,
            'followable_type' => $model->getMorphClass(),
            'followable_id' => $model->id,
        ]);

        return response()->json(['following' => true], 201);
    }

    /** Looking For board — players seeking teams, teams seeking players/matches. */
    public function board(Request $request): JsonResponse
    {
        $posts = LookingForPost::query()
            ->where('status', 'open')
            ->when($request->kind, fn ($q, $k) => $q->where('kind', $k))
            ->when($request->city, fn ($q, $c) => $q->where('city', 'like', "%{$c}%"))
            ->with(['user:id,name,username', 'team:id,name'])
            ->latest()
            ->paginate(30);

        return response()->json($posts->through(fn (LookingForPost $p) => [
            'id' => $p->id,
            'kind' => $p->kind,
            'city' => $p->city,
            'text' => $p->text,
            'user' => $p->user?->only(['id', 'name', 'username', 'avatar_url']),
            'team' => $p->team?->only(['id', 'name']),
            'mine' => $p->user_id === $request->user()->id,
            'created_at' => $p->created_at,
        ]));
    }

    public function post(Request $request): JsonResponse
    {
        $data = $request->validate([
            'kind' => ['required', 'in:'.implode(',', LookingForPost::KINDS)],
            'team_id' => ['nullable', 'integer', 'exists:teams,id'],
            'city' => ['nullable', 'string', 'max:80'],
            'text' => ['required', 'string', 'max:1000'],
        ]);

        $post = LookingForPost::create($data + ['user_id' => $request->user()->id]);

        return response()->json($post, 201);
    }

    public function closePost(Request $request, LookingForPost $post): JsonResponse
    {
        abort_unless($post->user_id === $request->user()->id, 403);
        $post->forceFill(['status' => 'closed'])->save();

        return response()->json(['status' => 'closed']);
    }

    /** Challenge another team — creates the match on accept. */
    public function challenge(Request $request, Team $team): JsonResponse
    {
        $data = $request->validate([
            'to_team_id' => ['required', 'integer', 'exists:teams,id', 'different:'.$team->id],
            'message' => ['nullable', 'string', 'max:500'],
            'proposed_at' => ['nullable', 'date'],
        ]);

        // Only members may send challenges on a team's behalf.
        $isMember = $team->members()->where('users.id', $request->user()->id)->exists();
        abort_unless($isMember, 403);

        $req = MatchRequest::create($data + [
            'from_team_id' => $team->id,
            'created_by' => $request->user()->id,
        ]);

        return response()->json($req, 201);
    }

    /** Match requests involving my teams (sent + received). */
    public function requests(Request $request): JsonResponse
    {
        $teamIds = $request->user()->teams()->pluck('teams.id');

        $requests = MatchRequest::whereIn('from_team_id', $teamIds)
            ->orWhereIn('to_team_id', $teamIds)
            ->with(['fromTeam:id,name', 'toTeam:id,name'])
            ->latest()
            ->paginate(30);

        return response()->json($requests->through(fn (MatchRequest $r) => [
            'id' => $r->id,
            'from' => $r->fromTeam?->only(['id', 'name']),
            'to' => $r->toTeam?->only(['id', 'name']),
            'message' => $r->message,
            'proposed_at' => $r->proposed_at,
            'status' => $r->status,
            'match_id' => $r->match_id,
            'can_respond' => $r->status === 'pending' && $teamIds->contains($r->to_team_id),
        ]));
    }

    public function respond(Request $request, MatchRequest $matchRequest): JsonResponse
    {
        $data = $request->validate(['action' => ['required', 'in:accept,decline,cancel']]);
        $user = $request->user();
        $teamIds = $user->teams()->pluck('teams.id');

        abort_unless($matchRequest->status === 'pending', 409);

        if ($data['action'] === 'cancel') {
            abort_unless($teamIds->contains($matchRequest->from_team_id) || $matchRequest->created_by === $user->id, 403);
            $matchRequest->forceFill(['status' => 'cancelled'])->save();

            return response()->json(['status' => 'cancelled']);
        }

        abort_unless($teamIds->contains($matchRequest->to_team_id), 403);

        if ($data['action'] === 'decline') {
            $matchRequest->forceFill(['status' => 'declined'])->save();

            return response()->json(['status' => 'declined']);
        }

        // Accept → scheduled match seeded with both rosters.
        $match = $this->fixtureFromRequest($matchRequest, $user);
        $matchRequest->forceFill(['status' => 'accepted', 'match_id' => $match->id])->save();

        return response()->json(['status' => 'accepted', 'match_id' => $match->id]);
    }

    /**
     * Career leaderboards over scored deliveries — registered users only
     * (name-only match players can't be attributed yet).
     */
    public function leaderboards(Request $request): JsonResponse
    {
        $kind = $request->query('kind', 'batting');

        $batting = Delivery::query()
            ->join('match_players', 'deliveries.striker_id', '=', 'match_players.id')
            ->whereNotNull('match_players.user_id')
            ->where('deliveries.extra_type', '!=', 'wide')
            ->selectRaw('match_players.user_id, sum(deliveries.bat_runs) as runs, count(*) as balls')
            ->groupBy('match_players.user_id')
            ->orderByDesc('runs')
            ->limit(50)
            ->get();

        $bowling = Delivery::query()
            ->join('match_players', 'deliveries.bowler_id', '=', 'match_players.id')
            ->whereNotNull('match_players.user_id')
            ->whereIn('deliveries.wicket_type', Delivery::BOWLER_WICKETS)
            ->selectRaw('match_players.user_id, count(*) as wickets')
            ->groupBy('match_players.user_id')
            ->orderByDesc('wickets')
            ->limit(50)
            ->get();

        $users = User::whereIn('id', $batting->pluck('user_id')->merge($bowling->pluck('user_id'))->unique())
            ->get()
            ->keyBy('id');

        $shape = fn ($rows, $stat) => $rows->map(fn ($r) => [
            'user' => $users->get($r->user_id)?->only(['id', 'name', 'username', 'avatar_url', 'city']),
            ...$stat($r),
        ])->filter(fn ($r) => $r['user'])->values();

        return response()->json([
            'batting' => $shape($batting, fn ($r) => ['runs' => (int) $r->runs, 'balls' => (int) $r->balls]),
            'bowling' => $shape($bowling, fn ($r) => ['wickets' => (int) $r->wickets]),
            'kind' => $kind,
        ]);
    }

    private function fixtureFromRequest(MatchRequest $r, User $actor): \App\Models\CricketMatch
    {
        $from = $r->fromTeam;
        $to = $r->toTeam;

        $match = \App\Models\CricketMatch::create([
            'created_by' => $actor->id,
            'team_a_id' => $from->id,
            'team_b_id' => $to->id,
            'team_a_name' => $from->name,
            'team_b_name' => $to->name,
            'overs_limit' => 20,
        ]);

        foreach ([['a', $from], ['b', $to]] as [$side, $team]) {
            $names = $team->members()->pluck('name')->take(15)->values();
            $names = $names->isEmpty() ? collect(['Player 1', 'Player 2']) : $names;
            $names->each(fn ($name, $i) => $match->players()->create([
                'side' => $side,
                'name' => $name,
                'is_captain' => $i === 0,
                'batting_order' => $i + 1,
            ]));
        }

        $match->forceFill(['players_per_side' => max(2, $match->players()->where('side', 'a')->count(), $match->players()->where('side', 'b')->count())])->save();

        return $match;
    }
}
