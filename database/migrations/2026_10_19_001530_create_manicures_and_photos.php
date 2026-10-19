<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // What a Manicure is when it has no visibility of its own: 'public' or 'private'.
            $table->string('manicure_default_visibility')->default('public');
        });

        Schema::create('manicures', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->date('worn_on');
            $table->string('caption', 500)->nullable();
            // Null = follow the owner's account default; otherwise 'public' or 'private'.
            $table->string('visibility')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'worn_on']);
        });

        Schema::create('manicure_photos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('manicure_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('position')->default(0);
            $table->string('path');
            $table->string('thumb_path');
            $table->timestamps();

            $table->index(['manicure_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manicure_photos');
        Schema::dropIfExists('manicures');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('manicure_default_visibility');
        });
    }
};
