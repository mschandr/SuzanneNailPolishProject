<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            // Lowercased name; unique per User so "China cabinet" and "china cabinet" are one place.
            $table->string('name_key');
            $table->timestamps();

            $table->unique(['user_id', 'name_key']);
        });

        Schema::create('bottles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('polish_id')->constrained('polishes')->cascadeOnDelete();
            // Deleting a Location leaves its Bottles unplaced rather than deleting them.
            $table->foreignUuid('location_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('owned');
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'polish_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bottles');
        Schema::dropIfExists('locations');
    }
};
