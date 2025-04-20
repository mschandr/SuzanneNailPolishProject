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
        Schema::create('worn_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('worn_id')->constrained('worn')->onDelete('cascade');
            $table->string('filename');
            $table->boolean('flash_used');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
