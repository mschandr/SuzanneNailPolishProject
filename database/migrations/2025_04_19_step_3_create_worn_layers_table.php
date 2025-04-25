<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('worn_layers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('worn_id')->constrained('worn')->onDelete('cascade');
            $table->foreignId('polish_id')->constrained('polishes')->onDelete('cascade');
            $table->string('layer_type'); // base, polish, topper, glossy, etc.
            $table->integer('layer_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('worn_layers');
    }
};
