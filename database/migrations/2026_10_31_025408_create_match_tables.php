<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            // Teams may be real app teams or free-text names for pickup games.
            $table->foreignId('team_a_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->foreignId('team_b_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->string('team_a_name');
            $table->string('team_b_name');
            $table->string('ground')->nullable();
            $table->string('city')->nullable();
            $table->unsignedTinyInteger('overs_limit')->default(20);
            $table->unsignedTinyInteger('players_per_side')->default(11);
            $table->string('ball_type', 16)->default('leather'); // leather|tennis|street
            $table->string('status', 16)->default('scheduled');  // scheduled|live|completed|abandoned
            $table->char('toss_winner', 1)->nullable();          // a|b
            $table->string('toss_decision', 8)->nullable();      // bat|bowl
            $table->json('result')->nullable();
            $table->string('stream_url')->nullable();
            $table->string('stream_key')->nullable();            // YouTube RTMP key — treat as secret
            $table->string('stream_status', 16)->default('off'); // off|live|ended
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
        });

        Schema::create('match_players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('matches')->cascadeOnDelete();
            $table->char('side', 1); // a|b
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->boolean('is_captain')->default(false);
            $table->boolean('is_keeper')->default(false);
            $table->unsignedTinyInteger('batting_order');
            $table->timestamps();

            $table->index(['match_id', 'side', 'batting_order']);
        });

        Schema::create('innings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('matches')->cascadeOnDelete();
            $table->unsignedTinyInteger('number'); // 1|2
            $table->char('batting_side', 1);
            $table->unsignedInteger('runs')->default(0);
            $table->unsignedTinyInteger('wickets')->default(0);
            $table->unsignedSmallInteger('legal_balls')->default(0);
            $table->unsignedSmallInteger('wides')->default(0);
            $table->unsignedSmallInteger('no_balls')->default(0);
            $table->unsignedSmallInteger('byes')->default(0);
            $table->unsignedSmallInteger('leg_byes')->default(0);
            $table->unsignedSmallInteger('target')->nullable();
            $table->string('status', 16)->default('in_progress'); // in_progress|completed
            $table->timestamps();
        });

        // Every delivery is a row — the source of truth; innings counters are
        // derived denormalized state recomputed on undo/edit.
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('innings_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('seq');
            $table->unsignedTinyInteger('over_no');
            $table->unsignedTinyInteger('ball_in_over');
            $table->foreignId('striker_id')->constrained('match_players')->cascadeOnDelete();
            $table->foreignId('non_striker_id')->constrained('match_players')->cascadeOnDelete();
            $table->foreignId('bowler_id')->constrained('match_players')->cascadeOnDelete();
            $table->unsignedTinyInteger('bat_runs')->default(0);
            $table->string('extra_type', 8)->default('none'); // none|wide|no_ball|bye|leg_bye
            $table->unsignedTinyInteger('extra_runs')->default(0);
            $table->string('wicket_type', 16)->nullable(); // bowled|caught|lbw|run_out|stumped|hit_wicket
            $table->foreignId('out_player_id')->nullable()->constrained('match_players')->nullOnDelete();
            $table->foreignId('fielder_id')->nullable()->constrained('match_players')->nullOnDelete();
            $table->boolean('legal_ball')->default(true);
            $table->timestamps();

            $table->index(['innings_id', 'seq']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deliveries');
        Schema::dropIfExists('innings');
        Schema::dropIfExists('match_players');
        Schema::dropIfExists('matches');
    }
};
