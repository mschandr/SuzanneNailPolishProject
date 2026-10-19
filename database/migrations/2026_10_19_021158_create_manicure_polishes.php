<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manicure_polishes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('manicure_id')->constrained()->cascadeOnDelete();
            // Deleting a private Polish removes it from any Manicure that listed it.
            $table->foreignUuid('polish_id')->constrained('polishes')->cascadeOnDelete();
            // The owner's own Bottle of that Polish, if they chose to link one.
            $table->foreignUuid('bottle_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('position');
            $table->timestamps();

            $table->unique(['manicure_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manicure_polishes');
    }
};
