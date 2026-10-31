<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('follows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->morphs('followable'); // App\Models\Team | App\Models\User
            $table->timestamps();

            $table->unique(['user_id', 'followable_type', 'followable_id'], 'follows_unique');
        });

        Schema::create('looking_for_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 20); // seeking_team|seeking_players|seeking_match|seeking_coach
            $table->string('city')->nullable();
            $table->text('text');
            $table->string('status', 16)->default('open'); // open|closed
            $table->timestamps();

            $table->index(['status', 'kind', 'city']);
        });

        Schema::create('match_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('to_team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('match_id')->nullable()->constrained('matches')->nullOnDelete();
            $table->text('message')->nullable();
            $table->timestamp('proposed_at')->nullable();
            $table->string('status', 16)->default('pending'); // pending|accepted|declined|cancelled
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_requests');
        Schema::dropIfExists('looking_for_posts');
        Schema::dropIfExists('follows');
    }
};
