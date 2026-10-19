<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('manicures', function (Blueprint $table) {
            // Set by the Admin: the Manicure disappears from every public surface (owner and Admin still see it).
            $table->timestamp('hidden_at')->nullable();
            $table->string('hidden_reason', 300)->nullable();
        });

        Schema::table('users', function (Blueprint $table) {
            // Set by the Admin: the User cannot sign in and their public content is hidden.
            $table->timestamp('suspended_at')->nullable();
            $table->string('suspended_reason', 300)->nullable();
        });

        Schema::create('reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('manicure_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('reporter_id')->constrained('users')->cascadeOnDelete();
            $table->string('reason');
            $table->string('details', 1000)->nullable();
            $table->string('status')->default('open'); // open | dismissed | actioned
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();

            // One report per person per Manicure; repeating it changes nothing.
            $table->unique(['manicure_id', 'reporter_id']);
            $table->index('status');
        });

        // An append-only record of what the Admin did. It holds a text snapshot because the Manicure or User
        // it refers to may no longer exist.
        Schema::create('moderation_log', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->string('target_type');
            $table->uuid('target_id')->nullable();
            $table->string('summary', 500);
            $table->string('note', 300)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('moderation_log');
        Schema::dropIfExists('reports');
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['suspended_at', 'suspended_reason']));
        Schema::table('manicures', fn (Blueprint $t) => $t->dropColumn(['hidden_at', 'hidden_reason']));
    }
};
