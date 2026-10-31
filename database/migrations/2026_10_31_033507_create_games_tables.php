<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Bilingual trivia bank — seeded, versioned per question.
        Schema::create('trivia_questions', function (Blueprint $table) {
            $table->id();
            $table->json('question');          // {en, pt-BR}
            $table->json('options');           // {en: [...], pt-BR: [...]}
            $table->unsignedTinyInteger('answer_index');
            $table->string('difficulty', 8)->default('easy');
            $table->timestamps();
        });

        // Daily trivia attempts — one set per user per day, scored + credited once.
        Schema::create('trivia_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('play_date');
            $table->unsignedTinyInteger('correct')->default(0);
            $table->unsignedTinyInteger('answered')->default(0);
            $table->json('answers')->nullable(); // {question_id: chosen_index}
            $table->timestamps();

            $table->unique(['user_id', 'play_date']);
        });

        // Match winner predictions — settled when the match completes.
        Schema::create('match_predictions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('matches')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('pick', 1); // a|b
            $table->boolean('correct')->nullable();
            $table->timestamps();

            $table->unique(['match_id', 'user_id']);
        });

        // Personal-best + leaderboard scores for client-side mini-games.
        Schema::create('game_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('game', 24); // catch_it|reflex...
            $table->unsignedInteger('score');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['game', 'score']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_scores');
        Schema::dropIfExists('match_predictions');
        Schema::dropIfExists('trivia_attempts');
        Schema::dropIfExists('trivia_questions');
    }
};
