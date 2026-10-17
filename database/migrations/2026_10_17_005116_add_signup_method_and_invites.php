<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // self | invite | admin. Null for accounts that predate the cap; never counted.
            $table->string('signup_method')->nullable()->index();
        });

        Schema::create('invite_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('email')->unique();
            $table->timestamp('invited_at')->nullable();
            $table->timestamps();
        });

        Schema::create('invites', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('email')->index();
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invites');
        Schema::dropIfExists('invite_requests');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('signup_method');
        });
    }
};
