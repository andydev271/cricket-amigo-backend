<?php

namespace App\Services;

use App\Models\CricketMatch;
use App\Models\CreditTransaction;
use App\Models\MatchPrediction;
use App\Models\TriviaAttempt;
use App\Models\TriviaQuestion;
use App\Models\User;
use Illuminate\Support\Collection;

class GameService
{
    public const TRIVIA_SET_SIZE = 5;
    public const TRIVIA_CREDIT_PER_CORRECT = 2;
    public const PICK_CREDIT = 5;

    /**
     * Today's trivia set for the user — deterministic per user+day so a
     * refresh doesn't reshuffle, different users get different questions.
     */
    public function dailyTrivia(User $user): array
    {
        $attempt = $this->attempt($user);
        $answeredIds = array_keys($attempt->answers ?? []);

        $questions = TriviaQuestion::inRandomOrder($user->id + $attempt->play_date->dayOfYear)
            ->limit(self::TRIVIA_SET_SIZE)
            ->get();

        return [
            'attempt' => [
                'correct' => $attempt->correct,
                'answered' => $attempt->answered,
                'finished' => $attempt->answered >= self::TRIVIA_SET_SIZE,
            ],
            'questions' => $questions->map(fn (TriviaQuestion $q) => $q->localized($user->locale ?? 'en')),
            'answered_ids' => $answeredIds,
        ];
    }

    public function answerTrivia(User $user, int $questionId, int $choice): array
    {
        $attempt = $this->attempt($user);
        $answers = $attempt->answers ?? [];

        if (isset($answers[$questionId])) {
            return ['correct' => null, 'already_answered' => true];
        }

        $question = TriviaQuestion::findOrFail($questionId);
        $correct = $question->answer_index === $choice;

        $answers[$questionId] = $choice;
        $attempt->forceFill([
            'answers' => $answers,
            'answered' => $attempt->answered + 1,
            'correct' => $attempt->correct + ($correct ? 1 : 0),
        ])->save();

        // Credit when the daily set is complete.
        $finished = $attempt->answered >= self::TRIVIA_SET_SIZE;
        if ($finished) {
            $granted = $attempt->correct * self::TRIVIA_CREDIT_PER_CORRECT;
            if ($granted > 0) {
                CreditTransaction::record(
                    $user,
                    $granted,
                    'trivia_win',
                    'trivia-'.$attempt->play_date->toDateString(),
                );
            }
        }

        return [
            'correct' => $correct,
            'answer_index' => $question->answer_index,
            'answered' => $attempt->answered,
            'finished' => $finished,
            'score' => $attempt->correct,
            'granted' => $finished ? $attempt->correct * self::TRIVIA_CREDIT_PER_CORRECT : 0,
        ];
    }

    /** Predict the winner of a scheduled/live match — one pick per user. */
    public function predict(User $user, CricketMatch $match, string $pick): MatchPrediction
    {
        abort_unless(in_array($match->status, ['scheduled', 'live'], true), 409, 'match already decided');

        return MatchPrediction::updateOrCreate(
            ['match_id' => $match->id, 'user_id' => $user->id],
            ['pick' => $pick],
        );
    }

    /**
     * Credit correct picks when a match completes. Called from the scoring
     * engine inside the completion transaction — ledger dedupes on ref.
     */
    public function settlePredictions(CricketMatch $match): int
    {
        $winner = $match->result['winner'] ?? null;
        $picks = MatchPrediction::where('match_id', $match->id)->whereNull('correct')->get();
        $settled = 0;

        foreach ($picks as $pick) {
            $pick->forceFill(['correct' => $winner !== null && $pick->pick === $winner])->save();
            if ($pick->correct) {
                CreditTransaction::record(
                    User::find($pick->user_id),
                    self::PICK_CREDIT,
                    'pick_win',
                    "pick-{$match->id}-{$pick->user_id}",
                );
            }
            $settled++;
        }

        return $settled;
    }

    private function attempt(User $user): TriviaAttempt
    {
        return TriviaAttempt::firstOrCreate(
            ['user_id' => $user->id, 'play_date' => today()],
        );
    }
}
