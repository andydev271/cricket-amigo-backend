<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClipUploadController;
use App\Http\Controllers\CoachingPackController;
use App\Http\Controllers\CommunityController;
use App\Http\Controllers\CreditsController;
use App\Http\Controllers\FlagsController;
use App\Http\Controllers\FriendController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\MatchController;
use App\Http\Controllers\MeController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\TeamInviteController;
use App\Http\Controllers\TournamentController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/flags', [FlagsController::class, 'index']);
Route::get('/coaching-packs', [CoachingPackController::class, 'index']);
Route::get('/invites/{token}', [TeamInviteController::class, 'show']);
Route::get('/matches/{match}/score', [MatchController::class, 'score']);

Route::prefix('auth')->group(function () {
    Route::post('/device', [AuthController::class, 'device']);
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/social/{provider}', [AuthController::class, 'social']);
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [MeController::class, 'show']);
    Route::put('/me', [MeController::class, 'update']);
    Route::put('/me/locale', [MeController::class, 'updateLocale']);
    Route::post('/me/avatar', [MeController::class, 'avatar']);

    Route::get('/users/search', [UserController::class, 'search']);
    Route::get('/users/{user}', [UserController::class, 'show']);

    Route::get('/friends', [FriendController::class, 'index']);
    Route::get('/friends/requests', [FriendController::class, 'requests']);
    Route::get('/friends/suggestions', [FriendController::class, 'suggestions']);
    Route::post('/friends/request/{user}', [FriendController::class, 'sendRequest']);
    Route::post('/friends/respond/{friendRequest}', [FriendController::class, 'respond']);

    Route::get('/teams', [TeamController::class, 'index']);
    Route::post('/teams', [TeamController::class, 'store']);
    Route::get('/teams/{team}', [TeamController::class, 'show']);
    Route::post('/teams/{team}/leave', [TeamController::class, 'leave']);
    Route::delete('/teams/{team}/members/{user}', [TeamController::class, 'removeMember']);
    Route::post('/teams/{team}/invites', [TeamInviteController::class, 'store']);
    Route::post('/invites/{token}/accept', [TeamInviteController::class, 'accept']);

    Route::get('/credits', [CreditsController::class, 'show']);
    Route::get('/credits/history', [CreditsController::class, 'history']);
    Route::post('/credits/earn', [CreditsController::class, 'earn']);

    Route::get('/me/clips', [ClipUploadController::class, 'index']);
    Route::post('/me/clips', [ClipUploadController::class, 'store']);

    Route::get('/matches', [MatchController::class, 'index']);
    Route::post('/matches', [MatchController::class, 'store']);
    Route::get('/matches/{match}', [MatchController::class, 'show']);
    Route::put('/matches/{match}', [MatchController::class, 'update']);
    Route::post('/matches/{match}/start', [MatchController::class, 'start']);
    Route::post('/matches/{match}/undo', [MatchController::class, 'undo']);
    Route::post('/matches/{match}/stream', [MatchController::class, 'stream']);
    Route::post('/matches/{match}/innings/{innings}/balls', [MatchController::class, 'ball']);

    Route::get('/tournaments', [TournamentController::class, 'index']);
    Route::post('/tournaments', [TournamentController::class, 'store']);
    Route::get('/tournaments/{tournament}', [TournamentController::class, 'show']);
    Route::put('/tournaments/{tournament}', [TournamentController::class, 'update']);
    Route::post('/tournaments/{tournament}/teams', [TournamentController::class, 'registerTeam']);
    Route::post('/tournaments/{tournament}/fixtures', [TournamentController::class, 'generateFixtures']);
    Route::get('/tournaments/{tournament}/standings', [TournamentController::class, 'standings']);

    Route::post('/follows', [CommunityController::class, 'follow']);
    Route::get('/looking-for', [CommunityController::class, 'board']);
    Route::post('/looking-for', [CommunityController::class, 'post']);
    Route::post('/looking-for/{post}/close', [CommunityController::class, 'closePost']);
    Route::post('/teams/{team}/challenges', [CommunityController::class, 'challenge']);
    Route::get('/match-requests', [CommunityController::class, 'requests']);
    Route::post('/match-requests/{matchRequest}/respond', [CommunityController::class, 'respond']);
    Route::get('/leaderboards', [CommunityController::class, 'leaderboards']);

    Route::get('/games/trivia', [GameController::class, 'trivia']);
    Route::post('/games/trivia/answer', [GameController::class, 'triviaAnswer']);
    Route::get('/games/scores', [GameController::class, 'topScores']);
    Route::post('/games/scores', [GameController::class, 'score']);
    Route::post('/matches/{match}/predict', [GameController::class, 'predict']);
    Route::get('/matches/{match}/predict', [GameController::class, 'myPick']);
});
