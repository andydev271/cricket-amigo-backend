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
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable()->unique()->after('name');
            $table->string('email')->nullable()->change();
            $table->string('password')->nullable()->change();
            $table->string('avatar_path')->nullable()->after('password');
            $table->string('locale', 10)->default('en')->after('avatar_path');
            $table->string('city')->nullable()->after('locale');
            $table->string('country', 2)->nullable()->after('city');
            $table->string('dominant_hand', 10)->nullable()->after('country');
            $table->json('roles')->nullable()->after('dominant_hand');
            $table->json('interests')->nullable()->after('roles');
            $table->unsignedInteger('credits')->default(0)->after('interests');
            $table->timestamp('onboarded_at')->nullable()->after('credits');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn([
                'username',
                'avatar_path',
                'locale',
                'city',
                'country',
                'dominant_hand',
                'roles',
                'interests',
                'credits',
                'onboarded_at',
            ]);
            $table->string('email')->nullable(false)->change();
            $table->string('password')->nullable(false)->change();
        });
    }
};
