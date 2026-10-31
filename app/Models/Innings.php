<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'match_id', 'number', 'batting_side', 'runs', 'wickets', 'legal_balls',
    'wides', 'no_balls', 'byes', 'leg_byes', 'target', 'status',
])]
class Innings extends Model
{
    public function match(): BelongsTo
    {
        return $this->belongsTo(CricketMatch::class, 'match_id');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class)->orderBy('seq');
    }

    /** Display overs e.g. 14.3 (balls in current over). */
    public function oversDisplay(): string
    {
        return intdiv($this->legal_balls, 6).'.'.($this->legal_balls % 6);
    }
}
