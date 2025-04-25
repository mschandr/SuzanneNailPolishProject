<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('polishes', function (Blueprint $table) {
            $table->string('product_url')
                ->nullable()
                ->default(null)
                ->after('name');
            $table->dateTime('url_check_datetime')
                ->default(now())
                ->after('product_url');
            $table->string('polish_type')
                ->default('polish')
                ->after('url_check_datetime');
            $table->boolean('is_available_online')
                ->default(false)
                ->after('polish_type');
        });
        DB::statement("UPDATE `polishes` SET url_check_datetime = DATE_SUB(NOW(), INTERVAL 7 DAY);");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('polishes', function (Blueprint $table) {
            $table->dropColumn('product_url');
            $table->dropColumn('url_check_datetime');
            $table->dropColumn('is_available_online');
        });
    }
};
