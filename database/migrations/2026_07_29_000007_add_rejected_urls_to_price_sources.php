<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('price_sources', function (Blueprint $table) {
            $table->json('rejected_urls')->nullable()->after('buy_url');
        });
    }

    public function down(): void
    {
        Schema::table('price_sources', function (Blueprint $table) {
            $table->dropColumn('rejected_urls');
        });
    }
};
