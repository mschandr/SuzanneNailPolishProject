<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('imports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('filename', 200);
            $table->string('status')->default('preview'); // preview | applied
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('applied_count')->default(0);
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();
        });

        // The rows of an uploaded file, held until the Admin has looked at them and chosen what to add.
        Schema::create('import_rows', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('import_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('line');
            $table->text('brand')->nullable();
            $table->text('name')->nullable();
            $table->text('collection')->nullable();
            $table->text('color_family')->nullable();
            $table->text('finishes')->nullable();
            $table->text('vendor_url')->nullable();
            $table->text('image_url')->nullable();
            $table->string('status'); // new | exists | duplicate | invalid
            $table->text('problems')->nullable(); // what is wrong, or what will be left blank
            $table->index(['import_id', 'line']);
        });

        Schema::table('polishes', function (Blueprint $table) {
            // Imported without a color family or finish: listed for the Admin to complete. Cleared when saved in the form.
            $table->boolean('needs_review')->default(false);
            // A link to an image hosted elsewhere (from an import). Never copied; the page shows it from its source.
            $table->string('swatch_url', 2048)->nullable();
            $table->index('needs_review');
        });
    }

    public function down(): void
    {
        Schema::table('polishes', function (Blueprint $table) {
            $table->dropIndex(['needs_review']);
            $table->dropColumn(['needs_review', 'swatch_url']);
        });
        Schema::dropIfExists('import_rows');
        Schema::dropIfExists('imports');
    }
};
