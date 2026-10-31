<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'innings_id', 'seq', 'over_no', 'ball_in_over',
    'striker_id', 'non_striker_id', 'bowler_id',
    'bat_runs', 'extra_type', 'extra_runs',
    'wicket_type', 'out_player_id', 'fielder_id', 'legal_ball',
])]
class Delivery extends Model
{
    public const EXTRAS = ['none', 'wide', 'no_ball', 'bye', 'leg_bye'];

    public const WICKETS = ['bowled', 'caught', 'lbw', 'run_out', 'stumped', 'hit_wicket'];

    /** Wicket types that credit the bowler. */
    public const BOWLER_WICKETS = ['bowled', 'caught', 'lbw', 'stumped', 'hit_wicket'];

    public function innings(): BelongsTo
    {
        return $this->belongsTo(Innings::class);
    }

    /** Runs added to the team total. */
    public function teamRuns(): int
    {
        $penalty = in_array($this->extra_type, ['wide', 'no_ball'], true) ? 1 : 0;

        return $this->bat_runs + $penalty + $this->extra_runs;
    }
}
