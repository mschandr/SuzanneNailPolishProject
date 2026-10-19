<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('manicures', function (Blueprint $table) {
            // For openly licensed photos that someone else took: who to credit, where it came from, and the
            // license. Null on ordinary Manicures that users upload themselves.
            $table->string('credit', 300)->nullable();
            $table->string('credit_url', 500)->nullable();
            $table->string('license_url', 500)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('manicures', function (Blueprint $table) {
            $table->dropColumn(['credit', 'credit_url', 'license_url']);
        });
    }
};
