<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('price_sources', function (Blueprint $table) {
            $table->decimal('latest_rating', 4, 2)->nullable()->after('latest_price');
            $table->unsignedInteger('latest_rating_count')->nullable()->after('latest_rating');
            $table->string('rating_type')->nullable()->after('latest_rating_count');
            $table->json('latest_details')->nullable()->after('rating_type');
        });

        Schema::table('price_histories', function (Blueprint $table) {
            $table->decimal('rating', 4, 2)->nullable()->after('price');
            $table->unsignedInteger('rating_count')->nullable()->after('rating');
            $table->string('rating_type')->nullable()->after('rating_count');
            $table->boolean('is_available')->default(true)->after('rating_type');
            $table->text('buy_url')->nullable()->after('is_available');
            $table->string('offer_title')->nullable()->after('buy_url');
            // Provider APIs may return either Gregorian or Jalali dates.
            $table->string('departure_at')->nullable()->after('offer_title');
            $table->string('return_at')->nullable()->after('departure_at');
            $table->json('details')->nullable()->after('return_at');
        });
    }

    public function down(): void
    {
        Schema::table('price_sources', function (Blueprint $table) {
            $table->dropColumn(['latest_rating', 'latest_rating_count', 'rating_type', 'latest_details']);
        });

        Schema::table('price_histories', function (Blueprint $table) {
            $table->dropColumn([
                'rating', 'rating_count', 'rating_type', 'is_available', 'buy_url',
                'offer_title', 'departure_at', 'return_at', 'details',
            ]);
        });
    }
};
