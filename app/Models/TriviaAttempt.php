<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'play_date', 'correct', 'answered', 'answers'])]
class TriviaAttempt extends Model
{
    protected function casts(): array
    {
        return [
            'play_date' => 'date',
            'answers' => 'array',
        ];
    }
}
