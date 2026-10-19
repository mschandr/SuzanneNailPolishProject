<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hearts', function (Blueprint $table) {
            // Cascades both ways: deleting a User removes their hearts, deleting a Manicure removes its hearts.
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('manicure_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();

            // One heart per user per manicure.
            $table->primary(['user_id', 'manicure_id']);
            $table->index('manicure_id');
        });

        // "Worn by others" and the Gallery's Polish filter look layers up by polish.
        Schema::table('manicure_polishes', function (Blueprint $table) {
            $table->index('polish_id');
        });
    }

    public function down(): void
    {
        Schema::table('manicure_polishes', function (Blueprint $table) {
            $table->dropIndex(['polish_id']);
        });
        Schema::dropIfExists('hearts');
    }
};
