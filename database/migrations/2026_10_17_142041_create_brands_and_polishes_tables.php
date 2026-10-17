<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brands', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('name_key')->unique();
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('polishes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('brand_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            // Lowercased shade name; with brand_id it makes (brand + shade) unique, ignoring case.
            $table->string('name_key');
            $table->string('collection')->nullable();
            $table->foreignUuid('color_family_id')->nullable()->constrained()->nullOnDelete();
            $table->string('vendor_url', 2048)->nullable();
            $table->string('swatch_path')->nullable();
            $table->timestamps();

            $table->unique(['brand_id', 'name_key']);
        });

        Schema::create('finish_polish', function (Blueprint $table) {
            $table->foreignUuid('polish_id')->constrained('polishes')->cascadeOnDelete();
            $table->foreignUuid('finish_id')->constrained()->cascadeOnDelete();
            $table->primary(['polish_id', 'finish_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finish_polish');
        Schema::dropIfExists('polishes');
        Schema::dropIfExists('brands');
    }
};
