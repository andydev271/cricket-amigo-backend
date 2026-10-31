<?php

namespace App\Services;

use App\Models\CricketMatch;
use App\Models\Tournament;
use App\Models\TournamentTeam;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TournamentService
{
    /**
     * Round-robin fixture list via the circle method. Creates a scheduled
     * CricketMatch per pairing — scoring flows through the normal scorer,
     * standings derive from completed matches.
     */
    public function generateLeagueFixtures(Tournament $tournament, User $actor): int
    {
        if (! $tournament->isOrganizer($actor)) {
            throw ValidationException::withMessages(['tournament' => 'not the organizer']);
        }

        $teams = $tournament->teams()->orderBy('id')->get();
        if ($teams->count() < 2) {
            throw ValidationException::withMessages(['teams' => 'need at least 2 registered teams']);
        }

        $pairs = $this->roundRobin($teams);

        $order = 0;
        DB::transaction(function () use ($pairs, $tournament, $actor, &$order) {
            foreach ($pairs as [$home, $away]) {
                $match = $this->fixtureMatch($tournament, $home, $away, $actor);
                $tournament->fixtures()->create([
                    'match_id' => $match->id,
                    'round' => 'league',
                    'fixture_order' => ++$order,
                ]);
            }
        });

        return $order;
    }

    /**
     * Circle-method pairings: [ [teamA, teamB], ... ] covering each pair once.
     * Odd team counts get a BYE marker (null) — byes produce no match.
     */
    private function roundRobin(Collection $teams): array
    {
        $list = $teams->values()->all();
        if (count($list) % 2 === 1) {
            $list[] = null; // bye
        }
        $n = count($list);
        $pairs = [];

        for ($round = 0; $round < $n - 1; $round++) {
            for ($i = 0; $i < $n / 2; $i++) {
                $a = $list[$i];
                $b = $list[$n - 1 - $i];
                if ($a && $b) {
                    // Alternate home/away orientation per round for fairness.
                    $pairs[] = $round % 2 === 0 ? [$a, $b] : [$b, $a];
                }
            }
            // Rotate: keep first fixed, shift the rest.
            $fixed = array_shift($list);
            $last = array_pop($list);
            array_unshift($list, $fixed, $last);
        }

        return $pairs;
    }

    private function fixtureMatch(Tournament $t, TournamentTeam $a, TournamentTeam $b, User $actor): CricketMatch
    {
        $match = CricketMatch::create([
            'created_by' => $actor->id,
            'team_a_id' => $a->team_id,
            'team_b_id' => $b->team_id,
            'team_a_name' => $a->name,
            'team_b_name' => $b->name,
            'overs_limit' => $t->overs_limit,
            'players_per_side' => max(2, min(11, $this->rosterSize($a), $this->rosterSize($b))),
            'ball_type' => $t->ball_type,
            'city' => $t->city,
        ]);

        // Seed XIs from linked team rosters so the scorer can start immediately;
        // name-only tournament teams get numbered placeholders to edit at toss.
        foreach (['a' => $a, 'b' => $b] as $side => $tt) {
            $names = $this->rosterNames($tt);
            foreach ($names as $i => $name) {
                $match->players()->create([
                    'side' => $side,
                    'name' => $name,
                    'is_captain' => $i === 0,
                    'is_keeper' => $i === 1,
                    'batting_order' => $i + 1,
                ]);
            }
        }

        return $match;
    }

    private function rosterSize(TournamentTeam $tt): int
    {
        return count($this->rosterNames($tt));
    }

    /** @return string[] */
    private function rosterNames(TournamentTeam $tt): array
    {
        if ($tt->team_id) {
            $names = $tt->team?->members()->pluck('name')->take(15)->all() ?? [];
            if ($names) {
                return $names;
            }
        }

        return array_map(fn ($i) => "Player {$i}", range(1, 11));
    }
}
