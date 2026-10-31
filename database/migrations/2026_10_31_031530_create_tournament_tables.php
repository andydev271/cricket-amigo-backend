<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournaments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('city')->nullable();
            $table->string('format', 20)->default('league'); // league|knockout|league_knockout
            $table->unsignedTinyInteger('overs_limit')->default(20);
            $table->string('ball_type', 16)->default('tennis');
            $table->unsignedTinyInteger('max_teams')->default(8);
            $table->unsignedTinyInteger('points_win')->default(2);
            $table->unsignedTinyInteger('points_draw')->default(1);   // tie/no-result
            $table->string('status', 16)->default('open');            // open|live|completed
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->timestamps();
        });

        Schema::create('tournament_teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->foreignId('contact_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 16)->default('registered'); // registered|withdrawn
            $table->timestamps();

            $table->unique(['tournament_id', 'team_id']);
            $table->index(['tournament_id', 'status']);
        });

        // A fixture row links a tournament to a real match — the match's own
        // deliveries drive standings, so nothing is duplicated.
        Schema::create('tournament_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->foreignId('match_id')->constrained('matches')->cascadeOnDelete();
            $table->string('round', 16)->default('league'); // league|qf|sf|f
            $table->unsignedSmallInteger('fixture_order')->default(0);
            $table->timestamps();

            $table->unique(['tournament_id', 'match_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_matches');
        Schema::dropIfExists('tournament_teams');
        Schema::dropIfExists('tournaments');
    }
};
