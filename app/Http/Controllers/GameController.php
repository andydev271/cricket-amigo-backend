<?php

namespace App\Http\Controllers;

use App\Models\CricketMatch;
use App\Models\GameScore;
use App\Services\GameService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GameController extends Controller
{
    public function __construct(private GameService $games) {}

    public function trivia(Request $request): JsonResponse
    {
        return response()->json($this->games->dailyTrivia($request->user()));
    }

    public function triviaAnswer(Request $request): JsonResponse
    {
        $data = $request->validate([
            'question_id' => ['required', 'integer', 'exists:trivia_questions,id'],
            'choice' => ['required', 'integer', 'min:0', 'max:3'],
        ]);

        return response()->json(
            $this->games->answerTrivia($request->user(), $data['question_id'], $data['choice']),
        );
    }

    public function predict(Request $request, CricketMatch $match): JsonResponse
    {
        $data = $request->validate(['pick' => ['required', 'in:a,b']]);
        $pick = $this->games->predict($request->user(), $match, $data['pick']);

        return response()->json(['pick' => $pick->pick, 'match_id' => $match->id], 201);
    }

    public function myPick(Request $request, CricketMatch $match): JsonResponse
    {
        $pick = \App\Models\MatchPrediction::where('match_id', $match->id)
            ->where('user_id', $request->user()->id)
            ->first();

        return response()->json([
            'pick' => $pick?->pick,
            'correct' => $pick?->correct,
            'count_a' => \App\Models\MatchPrediction::where('match_id', $match->id)->where('pick', 'a')->count(),
            'count_b' => \App\Models\MatchPrediction::where('match_id', $match->id)->where('pick', 'b')->count(),
        ]);
    }

    /** Personal-best write + top scores for a client-side mini-game. */
    public function score(Request $request): JsonResponse
    {
        $data = $request->validate([
            'game' => ['required', 'string', 'max:24'],
            'score' => ['required', 'integer', 'min:0', 'max:1000000'],
            'meta' => ['nullable', 'array'],
        ]);

        $row = GameScore::create($data + ['user_id' => $request->user()->id]);
        $best = GameScore::where('user_id', $request->user()->id)
            ->where('game', $data['game'])
            ->max('score');

        return response()->json(['score' => $row->score, 'best' => $best], 201);
    }

    public function topScores(Request $request): JsonResponse
    {
        $game = $request->query('game', 'catch_it');

        $top = GameScore::where('game', $game)
            ->with('user:id,name,username')
            ->selectRaw('user_id, max(score) as best')
            ->groupBy('user_id')
            ->orderByDesc('best')
            ->limit(20)
            ->get()
            ->map(fn ($r) => [
                'user' => $r->user?->only(['id', 'name', 'username']),
                'best' => (int) $r->best,
            ]);

        $mine = GameScore::where('game', $game)
            ->where('user_id', $request->user()->id)
            ->max('score');

        return response()->json(['game' => $game, 'top' => $top, 'my_best' => $mine ?? 0]);
    }
}
