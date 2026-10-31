<?php

namespace App\Services;

use App\Events\BallScored;
use App\Models\CricketMatch;
use App\Models\Delivery;
use App\Models\Innings;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Ball-by-ball scoring engine. Deliveries are the source of truth; innings
 * counters are denormalized and recomputed whenever a ball lands or a ball
 * is undone (undo = delete + recalc).
 */
class ScoringService
{
    /**
     * Record one delivery against an innings and advance the match state.
     *
     * @param  array{striker_id:int, non_striker_id:int, bowler_id:int, bat_runs:int,
     *         extra_type:string, extra_runs:int, wicket_type?:string|null,
     *         out_player_id?:int|null, fielder_id?:int|null}  $ball
     */
    public function record(Innings $innings, array $ball): Delivery
    {
        $match = $innings->match;

        if ($match->status !== 'live') {
            throw ValidationException::withMessages(['match' => 'match is not live']);
        }
        if ($innings->status !== 'in_progress') {
            throw ValidationException::withMessages(['innings' => 'innings is complete']);
        }

        $this->validatePlayers($innings, $ball);

        $delivery = DB::transaction(function () use ($innings, $match, $ball) {
            $innings = $innings->newQuery()->lockForUpdate()->findOrFail($innings->id);

            $legal = ! in_array($ball['extra_type'], ['wide', 'no_ball'], true);

            $delivery = $innings->deliveries()->create([
                'seq' => ($innings->deliveries()->max('seq') ?? 0) + 1,
                'over_no' => intdiv($innings->legal_balls, 6) + 1,
                'ball_in_over' => ($innings->legal_balls % 6) + 1,
                'striker_id' => $ball['striker_id'],
                'non_striker_id' => $ball['non_striker_id'],
                'bowler_id' => $ball['bowler_id'],
                'bat_runs' => $ball['bat_runs'],
                'extra_type' => $ball['extra_type'],
                'extra_runs' => $ball['extra_runs'] ?? 0,
                'wicket_type' => $ball['wicket_type'] ?? null,
                'out_player_id' => $ball['out_player_id'] ?? null,
                'fielder_id' => $ball['fielder_id'] ?? null,
                'legal_ball' => $legal,
            ]);

            $innings->runs += $delivery->teamRuns();
            $innings->wickets += $delivery->wicket_type ? 1 : 0;
            $innings->legal_balls += $legal ? 1 : 0;
            match ($delivery->extra_type) {
                'wide' => $innings->wides += 1 + $delivery->extra_runs,
                'no_ball' => $innings->no_balls += 1 + $delivery->extra_runs,
                'bye' => $innings->byes += $delivery->extra_runs,
                'leg_bye' => $innings->leg_byes += $delivery->extra_runs,
                default => null,
            };
            $innings->save();

            $this->maybeCompleteInnings($innings, $match);

            return $delivery;
        });

        BallScored::dispatch($match->fresh());

        return $delivery;
    }

    /**
     * Pop the most recent delivery of the match's latest innings and rebuild
     * the affected counters. An emptied second innings is removed entirely.
     */
    public function undo(CricketMatch $match): ?Delivery
    {
        return DB::transaction(function () use ($match) {
            $innings = $match->innings()->reorder()->orderByDesc('number')->get();
            $last = $innings->first();

            if ($last->number === 2 && $last->deliveries()->count() === 0) {
                // The empty chase innings came from the last ball of innings 1.
                $last->delete();
                $last = $innings->skip(1)->first();
            }

            $delivery = $last->deliveries()->reorder()->orderByDesc('seq')->first();
            if (! $delivery) {
                return null;
            }
            $delivery->delete();

            $last->status = 'in_progress';
            $last->save();
            $this->recalc($last);

            if ($match->status === 'completed') {
                $match->forceFill(['status' => 'live', 'result' => null, 'ended_at' => null])->save();
            }

            BallScored::dispatch($match->fresh());

            return $delivery;
        });
    }

    /** Recompute an innings' denormalized counters from its deliveries. */
    public function recalc(Innings $innings): void
    {
        $balls = $innings->deliveries()->get();

        $innings->runs = $balls->sum(fn (Delivery $d) => $d->teamRuns());
        $innings->wickets = $balls->whereNotNull('wicket_type')->count();
        $innings->legal_balls = $balls->where('legal_ball', true)->count();
        $innings->wides = $balls->where('extra_type', 'wide')->sum(fn (Delivery $d) => 1 + $d->extra_runs);
        $innings->no_balls = $balls->where('extra_type', 'no_ball')->sum(fn (Delivery $d) => 1 + $d->extra_runs);
        $innings->byes = $balls->where('extra_type', 'bye')->sum('extra_runs');
        $innings->leg_byes = $balls->where('extra_type', 'leg_bye')->sum('extra_runs');
        $innings->save();
    }

    /**
     * Public score summary — used by the viewer screen and the websocket
     * broadcast payload.
     */
    public function summary(CricketMatch $match): array
    {
        $match->load(['innings.deliveries', 'players']);

        $innings = $match->innings->map(fn (Innings $i) => [
            'number' => $i->number,
            'batting' => $match->sideName($i->batting_side),
            'runs' => $i->runs,
            'wickets' => $i->wickets,
            'overs' => $i->oversDisplay(),
            'target' => $i->target,
            'status' => $i->status,
            'extras' => [
                'wides' => $i->wides,
                'no_balls' => $i->no_balls,
                'byes' => $i->byes,
                'leg_byes' => $i->leg_byes,
            ],
        ])->values();

        $live = $match->innings->firstWhere('status', 'in_progress');
        $current = $live ? $this->currentState($match, $live) : null;

        return [
            'id' => $match->id,
            'status' => $match->status,
            'team_a' => $match->team_a_name,
            'team_b' => $match->team_b_name,
            'ground' => $match->ground,
            'overs_limit' => $match->overs_limit,
            'innings' => $innings,
            'current' => $current,
            'result' => $match->result,
            'stream_url' => $match->stream_status !== 'off' ? $match->stream_url : null,
        ];
    }

    /** Striker / non-striker / bowler state + recent balls for the live card. */
    private function currentState(CricketMatch $match, Innings $innings): array
    {
        $balls = $innings->deliveries;
        $last = $balls->last();
        $byId = $match->players->keyBy('id');

        [$striker, $nonStriker, $bowler] = $this->nextParticipants($match, $innings, $balls, $byId);

        $lastBalls = $balls->take(-12)->map(fn (Delivery $d) => $this->ballLabel($d))->values();

        return [
            'striker' => $striker ? $this->batLine($balls, $striker) : null,
            'non_striker' => $nonStriker ? $this->batLine($balls, $nonStriker) : null,
            'bowler' => $bowler ? $this->bowlLine($balls, $bowler) : null,
            'last_balls' => $lastBalls,
            'required' => $innings->target
                ? [
                    'runs' => max(0, $innings->target - $innings->runs),
                    'balls' => max(0, $match->overs_limit * 6 - $innings->legal_balls),
                ]
                : null,
        ];
    }

    /**
     * Who faces the next ball: opening pair before ball 1, otherwise the
     * previous ball's rotation (odd bat-runs swap, end-of-over swap) with a
     * wicket keeping the not-out batter's partner side pending client-side.
     */
    private function nextParticipants(CricketMatch $match, Innings $innings, $balls, $byId): array
    {
        $last = $balls->last();
        if (! $last) {
            $openers = $match->sidePlayers($innings->batting_side)->take(2)->get()->values();
            $bowlerSide = $innings->batting_side === 'a' ? 'b' : 'a';

            return [
                $openers->get(0),
                $openers->get(1),
                $match->sidePlayers($bowlerSide)->first(),
            ];
        }

        $bowlerSide = $innings->batting_side === 'a' ? 'b' : 'a';
        $out = $last->wicket_type ? $last->out_player_id : null;

        // Post-run striker, then end-of-over swap.
        $next = ($last->bat_runs % 2 === 1) ? $last->non_striker_id : $last->striker_id;
        if ($last->legal_ball && $last->ball_in_over === 6) {
            $next = $next === $last->striker_id ? $last->non_striker_id : $last->striker_id;
        }
        if ($out === $next) {
            $next = null; // striker is out — scorer picks the replacement next ball
        }
        $partner = $next === $last->striker_id ? $last->non_striker_id : $last->striker_id;
        if ($out === $partner) {
            $partner = null;
        }

        // Next over → scorer picks a new bowler; same over → same bowler.
        $newOver = $last->legal_ball && $last->ball_in_over === 6;
        $bowler = $newOver ? null : $last->bowler_id;

        return [
            $next ? $byId->get($next) : null,
            $partner ? $byId->get($partner) : null,
            $bowler ? $byId->get($bowler) : null,
        ];
    }

    private function batLine($balls, $player): array
    {
        $faced = $balls->where('striker_id', $player->id)->where('extra_type', '!=', 'wide');

        return [
            'id' => $player->id,
            'name' => $player->name,
            'runs' => $faced->sum('bat_runs'),
            'balls' => $faced->count(),
        ];
    }

    private function bowlLine($balls, $player): array
    {
        $bowled = $balls->where('bowler_id', $player->id);
        $runs = $bowled->sum(fn (Delivery $d) =>
            $d->bat_runs
            + (in_array($d->extra_type, ['wide', 'no_ball'], true) ? 1 + $d->extra_runs : 0)
        );

        return [
            'id' => $player->id,
            'name' => $player->name,
            'overs' => intdiv($bowled->where('legal_ball', true)->count(), 6)
                .'.'.($bowled->where('legal_ball', true)->count() % 6),
            'runs' => $runs,
            'wickets' => $bowled->whereIn('wicket_type', Delivery::BOWLER_WICKETS)->count(),
        ];
    }

    private function ballLabel(Delivery $d): string
    {
        if ($d->wicket_type) {
            return 'W';
        }

        return match ($d->extra_type) {
            'wide' => $d->extra_runs ? "{$d->extra_runs}wd" : 'wd',
            'no_ball' => $d->bat_runs ? "nb+{$d->bat_runs}" : 'nb',
            'bye' => "{$d->extra_runs}b",
            'leg_bye' => "{$d->extra_runs}lb",
            default => (string) $d->bat_runs,
        };
    }

    private function validatePlayers(Innings $innings, array $ball): void
    {
        $batting = $innings->batting_side;
        $players = $innings->match->players->keyBy('id');

        foreach (['striker_id', 'non_striker_id', 'out_player_id'] as $key) {
            if (! empty($ball[$key]) && $players->get($ball[$key])?->side !== $batting) {
                throw ValidationException::withMessages([$key => 'must be a batting-side player']);
            }
        }
        if (! empty($ball['bowler_id']) && $players->get($ball['bowler_id'])?->side === $batting) {
            throw ValidationException::withMessages(['bowler_id' => 'must be a bowling-side player']);
        }
    }

    private function maybeCompleteInnings(Innings $innings, CricketMatch $match): void
    {
        $allOut = $innings->wickets >= $match->players_per_side - 1;
        $oversDone = $innings->legal_balls >= $match->overs_limit * 6;
        $chased = $innings->target && $innings->runs >= $innings->target;

        if (! $allOut && ! $oversDone && ! $chased) {
            return;
        }

        $innings->status = 'completed';
        $innings->save();

        if ($innings->number === 1) {
            $match->innings()->create([
                'number' => 2,
                'batting_side' => $innings->batting_side === 'a' ? 'b' : 'a',
                'target' => $innings->runs + 1,
            ]);
        } else {
            $match->forceFill([
                'status' => 'completed',
                'ended_at' => now(),
                'result' => $this->result($match),
            ])->save();
        }
    }

    private function result(CricketMatch $match): array
    {
        [$first, $second] = [$match->innings()->where('number', 1)->first(), $match->innings()->where('number', 2)->first()];
        $batting2 = $second->batting_side;
        $bowling2 = $batting2 === 'a' ? 'b' : 'a';

        if ($second->runs >= $second->target) {
            return [
                'winner' => $batting2,
                'text' => $match->sideName($batting2).' won by '.($match->players_per_side - 1 - $second->wickets).' wickets',
            ];
        }
        if ($second->runs === $second->target - 1) {
            return ['winner' => null, 'text' => 'Match tied'];
        }

        return [
            'winner' => $bowling2,
            'text' => $match->sideName($bowling2).' won by '.($second->target - 1 - $second->runs).' runs',
        ];
    }
}
