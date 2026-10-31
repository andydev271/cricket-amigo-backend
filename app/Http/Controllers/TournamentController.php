<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Models\Tournament;
use App\Services\StandingsService;
use App\Services\TournamentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TournamentController extends Controller
{
    public function __construct(
        private TournamentService $svc,
        private StandingsService $standings,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $tournaments = Tournament::query()
            ->when($request->city, fn ($q, $c) => $q->where('city', 'like', "%{$c}%"))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->withCount('teams')
            ->latest()
            ->paginate(20);

        return response()->json($tournaments->through(fn (Tournament $t) => [
            'id' => $t->id,
            'name' => $t->name,
            'city' => $t->city,
            'format' => $t->format,
            'overs_limit' => $t->overs_limit,
            'status' => $t->status,
            'teams_count' => $t->teams_count,
            'max_teams' => $t->max_teams,
            'starts_on' => $t->starts_on?->toDateString(),
        ]));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:80'],
            'format' => ['in:league,knockout,league_knockout'],
            'overs_limit' => ['integer', 'min:1', 'max:50'],
            'ball_type' => ['in:leather,tennis,street'],
            'max_teams' => ['integer', 'min:2', 'max:64'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
        ]);

        $tournament = Tournament::create($data + [
            'created_by' => $request->user()->id,
            'status' => 'open',
        ]);

        return response()->json($this->detail($tournament), 201);
    }

    public function show(Request $request, Tournament $tournament): JsonResponse
    {
        return response()->json($this->detail($tournament, $request->user()));
    }

    /** Register an app team or a free-name entry. */
    public function registerTeam(Request $request, Tournament $tournament): JsonResponse
    {
        abort_unless($tournament->status === 'open', 409, 'registration closed');

        $data = $request->validate([
            'team_id' => ['nullable', 'integer', 'exists:teams,id'],
            'name' => ['required_without:team_id', 'string', 'max:80'],
        ]);

        $team = isset($data['team_id']) ? Team::findOrFail($data['team_id']) : null;

        // You may only register a team you belong to — or a free-name side.
        if ($team) {
            $membership = $team->members()->where('users.id', $request->user()->id)->first();
            abort_unless($membership, 403, 'not a member of that team');
        }

        $exists = $tournament->teams()
            ->where(fn ($q) => $q
                ->where('name', $team?->name ?? $data['name'])
                ->when($team, fn ($q) => $q->orWhere('team_id', $team->id)))
            ->exists();
        abort_if($exists, 409, 'already registered');
        abort_if($tournament->teams()->count() >= $tournament->max_teams, 409, 'tournament full');

        $tt = $tournament->teams()->create([
            'team_id' => $team?->id,
            'name' => $team?->name ?? $data['name'],
            'contact_user_id' => $request->user()->id,
        ]);

        return response()->json($tt, 201);
    }

    public function generateFixtures(Request $request, Tournament $tournament): JsonResponse
    {
        $count = $this->svc->generateLeagueFixtures($tournament, $request->user());
        $tournament->forceFill(['status' => 'live'])->save();

        return response()->json(['fixtures' => $count], 201);
    }

    public function standings(Tournament $tournament): JsonResponse
    {
        return response()->json($this->standings->table($tournament));
    }

    public function update(Request $request, Tournament $tournament): JsonResponse
    {
        abort_unless($tournament->isOrganizer($request->user()), 403);
        $data = $request->validate(['status' => ['required', Rule::in(['open', 'live', 'completed'])]]);
        $tournament->forceFill(['status' => $data['status']])->save();

        return response()->json(['status' => $tournament->status]);
    }

    private function detail(Tournament $tournament, ?\App\Models\User $user = null): array
    {
        $tournament->load(['teams', 'fixtures.match.innings']);

        return [
            'id' => $tournament->id,
            'name' => $tournament->name,
            'city' => $tournament->city,
            'format' => $tournament->format,
            'overs_limit' => $tournament->overs_limit,
            'ball_type' => $tournament->ball_type,
            'max_teams' => $tournament->max_teams,
            'status' => $tournament->status,
            'starts_on' => $tournament->starts_on?->toDateString(),
            'ends_on' => $tournament->ends_on?->toDateString(),
            'is_organizer' => $user ? $tournament->isOrganizer($user) : false,
            'teams' => $tournament->teams->map(fn ($tt) => [
                'id' => $tt->id, 'team_id' => $tt->team_id, 'name' => $tt->name,
            ])->values(),
            'fixtures' => $tournament->fixtures->map(fn ($f) => [
                'id' => $f->id,
                'match_id' => $f->match_id,
                'round' => $f->round,
                'team_a' => $f->match->team_a_name,
                'team_b' => $f->match->team_b_name,
                'status' => $f->match->status,
                'score' => $f->match->innings->map(fn ($i) => [
                    'batting' => $i->batting_side, 'runs' => $i->runs,
                    'wickets' => $i->wickets, 'overs' => $i->oversDisplay(),
                ])->values(),
                'result' => $f->match->result,
            ])->values(),
        ];
    }
}
