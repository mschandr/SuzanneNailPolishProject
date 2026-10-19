<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per self-signup ever made, with no link to the account. The "first 100" cap counts these rather
        // than current users, so someone deleting their account can't quietly reopen a place.
        Schema::create('self_signups', function (Blueprint $table) {
            $table->id();
            $table->timestamp('created_at')->useCurrent();
        });

        // Backfill from the accounts that exist today, in one statement. (Accounts deleted before this ledger
        // existed can't be recovered, which is why the ledger should not be dropped and rebuilt later.)
        $existing = DB::table('users')->where('signup_method', 'self')->count();
        if ($existing > 0) {
            DB::table('self_signups')->insert(array_fill(0, $existing, ['created_at' => now()]));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('self_signups');
    }
};
