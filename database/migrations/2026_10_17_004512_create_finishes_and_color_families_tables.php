<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['finishes', 'color_families'] as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name');
                // Lowercased name; its unique index makes duplicates case-insensitive.
                $table->string('name_key')->unique();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('finishes');
        Schema::dropIfExists('color_families');
    }
};
