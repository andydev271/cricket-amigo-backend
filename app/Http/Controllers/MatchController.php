<?php

namespace App\Http\Controllers;

use App\Models\CricketMatch;
use App\Models\Delivery;
use App\Models\Innings;
use App\Models\Team;
use App\Services\ScoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MatchController extends Controller
{
    public function __construct(private ScoringService $scoring) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $teamIds = $user->teams()->pluck('teams.id');

        $matches = CricketMatch::query()
            ->where('created_by', $user->id)
            ->orWhereIn('team_a_id', $teamIds)
            ->orWhereIn('team_b_id', $teamIds)
            ->with('innings')
            ->latest()
            ->paginate(20);

        return response()->json($matches->through(fn (CricketMatch $m) => [
            'id' => $m->id,
            'team_a' => $m->team_a_name,
            'team_b' => $m->team_b_name,
            'ground' => $m->ground,
            'overs_limit' => $m->overs_limit,
            'status' => $m->status,
            'result' => $m->result,
            'innings' => $m->innings->map(fn (Innings $i) => [
                'number' => $i->number,
                'batting' => $i->batting_side,
                'runs' => $i->runs,
                'wickets' => $i->wickets,
                'overs' => $i->oversDisplay(),
            ]),
            'can_score' => $m->canScore($user),
            'created_at' => $m->created_at,
        ]));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'team_a' => ['required', 'array'],
            'team_b' => ['required', 'array'],
            'team_a.name' => ['required_without:team_a.id', 'string', 'max:80'],
            'team_b.name' => ['required_without:team_b.id', 'string', 'max:80'],
            'team_a.id' => ['nullable', 'integer', 'exists:teams,id'],
            'team_b.id' => ['nullable', 'integer', 'exists:teams,id'],
            'team_a.players' => ['required', 'array', 'min:2', 'max:15'],
            'team_b.players' => ['required', 'array', 'min:2', 'max:15'],
            'team_a.players.*.name' => ['required', 'string', 'max:60'],
            'team_b.players.*.name' => ['required', 'string', 'max:60'],
            'team_a.players.*.is_captain' => ['boolean'],
            'team_a.players.*.is_keeper' => ['boolean'],
            'team_b.players.*.is_captain' => ['boolean'],
            'team_b.players.*.is_keeper' => ['boolean'],
            'overs_limit' => ['integer', 'min:1', 'max:50'],
            'ball_type' => ['in:leather,tennis,street'],
            'ground' => ['nullable', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:80'],
            'toss_winner' => ['nullable', 'in:a,b'],
            'toss_decision' => ['nullable', 'required_with:toss_winner', 'in:bat,bowl'],
        ]);

        $match = DB::transaction(function () use ($data, $request) {
            $match = CricketMatch::create([
                'created_by' => $request->user()->id,
                'team_a_id' => $data['team_a']['id'] ?? null,
                'team_b_id' => $data['team_b']['id'] ?? null,
                'team_a_name' => $data['team_a']['name'] ?? Team::find($data['team_a']['id'])->name,
                'team_b_name' => $data['team_b']['name'] ?? Team::find($data['team_b']['id'])->name,
                'overs_limit' => $data['overs_limit'] ?? 20,
                'players_per_side' => max(count($data['team_a']['players']), count($data['team_b']['players'])),
                'ball_type' => $data['ball_type'] ?? 'leather',
                'ground' => $data['ground'] ?? null,
                'city' => $data['city'] ?? null,
                'toss_winner' => $data['toss_winner'] ?? null,
                'toss_decision' => $data['toss_decision'] ?? null,
            ]);

            foreach (['a', 'b'] as $side) {
                foreach ($data["team_{$side}"]['players'] as $i => $p) {
                    $match->players()->create([
                        'side' => $side,
                        'name' => $p['name'],
                        'is_captain' => $p['is_captain'] ?? false,
                        'is_keeper' => $p['is_keeper'] ?? false,
                        'batting_order' => $i + 1,
                    ]);
                }
            }

            return $match;
        });

        return response()->json($this->detail($match->fresh()), 201);
    }

    public function show(CricketMatch $match): JsonResponse
    {
        return response()->json($this->detail($match));
    }

    /** Public live score — spectator data, no auth. */
    public function score(CricketMatch $match): JsonResponse
    {
        return response()->json($this->scoring->summary($match));
    }

    public function start(Request $request, CricketMatch $match): JsonResponse
    {
        abort_unless($match->canScore($request->user()), 403);
        abort_unless($match->status === 'scheduled', 409);

        // First innings batting side comes from the toss.
        $batting = $match->toss_winner
            ? ($match->toss_decision === 'bat' ? $match->toss_winner : $this->other($match->toss_winner))
            : 'a';

        DB::transaction(function () use ($match, $batting) {
            $match->innings()->create(['number' => 1, 'batting_side' => $batting]);
            $match->forceFill(['status' => 'live', 'started_at' => now()])->save();
        });

        return response()->json($this->detail($match->fresh()));
    }

    public function ball(Request $request, CricketMatch $match, Innings $innings): JsonResponse
    {
        abort_unless($match->canScore($request->user()), 403);
        abort_if($innings->match_id !== $match->id, 404);

        $ball = $request->validate([
            'striker_id' => ['required', 'integer'],
            'non_striker_id' => ['required', 'integer'],
            'bowler_id' => ['required', 'integer'],
            'bat_runs' => ['integer', 'min:0', 'max:7'],
            'extra_type' => ['in:'.implode(',', Delivery::EXTRAS)],
            'extra_runs' => ['integer', 'min:0', 'max:7'],
            'wicket_type' => ['nullable', 'in:'.implode(',', Delivery::WICKETS)],
            'out_player_id' => ['nullable', 'integer'],
            'fielder_id' => ['nullable', 'integer'],
        ]);

        $delivery = $this->scoring->record($innings, $ball + [
            'bat_runs' => 0, 'extra_type' => 'none', 'extra_runs' => 0,
        ]);

        return response()->json(['delivery' => $delivery, 'score' => $this->scoring->summary($match->fresh())], 201);
    }

    public function undo(Request $request, CricketMatch $match): JsonResponse
    {
        abort_unless($match->canScore($request->user()), 403);

        $undone = $this->scoring->undo($match);
        abort_unless($undone, 422, 'nothing to undo');

        return response()->json(['undone' => $undone->id, 'score' => $this->scoring->summary($match->fresh())]);
    }

    public function stream(Request $request, CricketMatch $match): JsonResponse
    {
        abort_unless($match->canScore($request->user()), 403);

        $data = $request->validate([
            'stream_url' => ['nullable', 'string', 'max:255'],
            'stream_key' => ['nullable', 'string', 'max:255'],
            'stream_status' => ['required', 'in:off,live,ended'],
        ]);

        $match->forceFill($data)->save();

        return response()->json(['stream_status' => $match->stream_status, 'stream_url' => $match->stream_url]);
    }

    public function update(Request $request, CricketMatch $match): JsonResponse
    {
        abort_unless($match->canScore($request->user()), 403);

        $data = $request->validate(['status' => ['required', 'in:completed,abandoned']]);

        $match->forceFill([
            'status' => $data['status'],
            'ended_at' => now(),
        ])->save();

        return response()->json(['status' => $match->status]);
    }

    private function detail(CricketMatch $match): array
    {
        $match->load(['players', 'innings']);

        return [
            'id' => $match->id,
            'status' => $match->status,
            'team_a' => ['id' => $match->team_a_id, 'name' => $match->team_a_name],
            'team_b' => ['id' => $match->team_b_id, 'name' => $match->team_b_name],
            'overs_limit' => $match->overs_limit,
            'ball_type' => $match->ball_type,
            'ground' => $match->ground,
            'city' => $match->city,
            'toss_winner' => $match->toss_winner,
            'toss_decision' => $match->toss_decision,
            'result' => $match->result,
            'stream_status' => $match->stream_status,
            'players' => $match->players->map(fn ($p) => [
                'id' => $p->id, 'side' => $p->side, 'name' => $p->name,
                'is_captain' => $p->is_captain, 'is_keeper' => $p->is_keeper,
                'batting_order' => $p->batting_order,
            ])->values(),
            'innings' => $match->innings->map(fn (Innings $i) => [
                'id' => $i->id, 'number' => $i->number, 'batting' => $i->batting_side,
                'runs' => $i->runs, 'wickets' => $i->wickets, 'overs' => $i->oversDisplay(),
                'target' => $i->target, 'status' => $i->status,
            ])->values(),
        ];
    }

    private function other(string $side): string
    {
        return $side === 'a' ? 'b' : 'a';
    }
}
