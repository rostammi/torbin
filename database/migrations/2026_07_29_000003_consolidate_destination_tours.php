<?php

use App\Services\TourDestinationConsolidator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->json('seo_keywords')->nullable()->after('description');
        });

        app(TourDestinationConsolidator::class)->consolidateAll();

        Schema::table('tour_suggestions', function (Blueprint $table) {
            $table->unique('destination');
        });
    }

    public function down(): void
    {
        Schema::table('tour_suggestions', function (Blueprint $table) {
            $table->dropUnique(['destination']);
        });
        Schema::table('tours', function (Blueprint $table) {
            $table->dropColumn('seo_keywords');
        });
    }
};
