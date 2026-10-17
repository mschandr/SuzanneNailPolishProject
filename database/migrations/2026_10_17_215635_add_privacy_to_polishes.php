<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('polishes', function (Blueprint $table) {
            // Null = public Catalog Polish. Set = a Private Polish visible only to that User.
            // Cascades so deleting an account removes its Private Polishes (promoted ones have null).
            $table->foreignUuid('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamp('promotion_requested_at')->nullable();

            $table->dropUnique(['brand_id', 'name_key']);
        });

        // Public shades are unique per brand; private ones are unique per brand and owner,
        // so two Users can each hold a private shade of the same name.
        DB::statement('create unique index polishes_public_unique on polishes (brand_id, name_key) where user_id is null');
        DB::statement('create unique index polishes_private_unique on polishes (brand_id, name_key, user_id) where user_id is not null');
    }

    public function down(): void
    {
        DB::statement('drop index polishes_private_unique');
        DB::statement('drop index polishes_public_unique');

        Schema::table('polishes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn('promotion_requested_at');
            $table->unique(['brand_id', 'name_key']);
        });
    }
};
