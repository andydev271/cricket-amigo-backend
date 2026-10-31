<?php

namespace App\Services;

use App\Models\Innings;
use App\Models\Tournament;
use App\Models\TournamentTeam;
use Illuminate\Support\Collection;

/**
 * Points table + NRR. Standings derive from the deliveries inside each
 * fixture's match — nothing is stored, so corrections/undo stay consistent.
 */
class StandingsService
{
    /** @return array<int, array<string, mixed>> sorted by pts then NRR. */
    public function table(Tournament $tournament): array
    {
        $teams = $tournament->teams()->get()->keyBy('id');
        // Plain array — Collection offsetGet isn't by-reference, so stats
        // must accumulate on native arrays.
        $rows = $teams->mapWithKeys(fn (TournamentTeam $tt) => [$tt->id => [
            'tournament_team_id' => $tt->id,
            'team_id' => $tt->team_id,
            'name' => $tt->name,
            'p' => 0, 'w' => 0, 'l' => 0, 't' => 0, 'nr' => 0,
            'pts' => 0,
            'runs_for' => 0, 'balls_for' => 0,
            'runs_against' => 0, 'balls_against' => 0,
            'nrr' => 0.0,
        ]])->all();

        $fixtures = $tournament->fixtures()
            ->with('match.innings')
            ->whereHas('match', fn ($q) => $q->where('status', 'completed'))
            ->get();

        foreach ($fixtures as $fixture) {
            $match = $fixture->match;
            [$a, $b] = [$this->sideOf($teams, $match, 'a'), $this->sideOf($teams, $match, 'b')];
            if (! $a || ! $b) {
                continue;
            }

            foreach ([[$a->id, 'a', 'b'], [$b->id, 'b', 'a']] as [$ttId, $side, $opp]) {
                $row = &$rows[$ttId];
                $row['p']++;

                $winner = $match->result['winner'] ?? null;
                if ($winner === null) {
                    $row['nr']++;
                    $row['pts'] += $tournament->points_draw;
                } elseif ($winner === $side) {
                    $row['w']++;
                    $row['pts'] += $tournament->points_win;
                } else {
                    $row['l']++;
                }

                [$for, $against] = [$match->innings->firstWhere('batting_side', $side), $match->innings->firstWhere('batting_side', $opp)];
                if ($for && $against) {
                    $row['runs_for'] += $for->runs;
                    $row['balls_for'] += $this->nrrBalls($for, $match);
                    $row['runs_against'] += $against->runs;
                    $row['balls_against'] += $this->nrrBalls($against, $match);
                }
            }
        }

        return collect($rows)->map(function (array $r) {
                $forRate = $r['balls_for'] ? $r['runs_for'] / ($r['balls_for'] / 6) : 0;
                $againstRate = $r['balls_against'] ? $r['runs_against'] / ($r['balls_against'] / 6) : 0;
                $r['nrr'] = round($forRate - $againstRate, 3);

                return $r;
            })
            ->sortBy([['pts', 'desc'], ['nrr', 'desc']])
            ->values()
            ->all();
    }

    /**
     * Which registered tournament team is playing as side $side — linked
     * app-team first, name fallback for unlinked entries.
     */
    private function sideOf(Collection $teams, $match, string $side): ?TournamentTeam
    {
        $teamId = $match->{"team_{$side}_id"};
        $name = $match->{"team_{$side}_name"};

        return $teams->first(fn (TournamentTeam $tt) =>
            ($teamId && $tt->team_id === $teamId) || $tt->name === $name
        );
    }

    /**
     * NRR denominator: a side bowled out is charged its full allocation
     * (ICC convention); otherwise its actual balls faced.
     */
    private function nrrBalls(Innings $innings, $match): int
    {
        $allOut = $innings->wickets >= $match->players_per_side - 1;

        return $allOut ? $match->overs_limit * 6 : $innings->legal_balls;
    }
}
