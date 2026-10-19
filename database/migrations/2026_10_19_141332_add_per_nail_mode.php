<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('manicures', function (Blueprint $table) {
            // 'hand' = one list for the whole hand (the default). 'nails' = a separate list per nail.
            $table->string('polish_mode')->default('hand');
        });

        Schema::table('manicure_polishes', function (Blueprint $table) {
            // Which list a layer belongs to: 'hand' for the whole-hand list, or a nail key such as 'left_ring'.
            // Not nullable: unique indexes treat NULLs as distinct, which would let positions collide.
            $table->string('target')->default('hand');

            $table->dropUnique(['manicure_id', 'position']);
            $table->unique(['manicure_id', 'target', 'position']);
        });
    }

    public function down(): void
    {
        // Per-nail layers can't survive the old one-list shape, and would make positions collide below.
        DB::table('manicure_polishes')->where('target', '!=', 'hand')->delete();

        Schema::table('manicure_polishes', function (Blueprint $table) {
            $table->dropUnique(['manicure_id', 'target', 'position']);
            $table->unique(['manicure_id', 'position']);
            $table->dropColumn('target');
        });
        Schema::table('manicures', fn (Blueprint $t) => $t->dropColumn('polish_mode'));
    }
};
