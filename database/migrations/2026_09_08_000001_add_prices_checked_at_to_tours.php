<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->timestamp('prices_checked_at')->nullable()->index();
        });

        DB::table('tours')->select('id')->orderBy('id')->chunkById(500, function ($tours) {
            foreach ($tours as $tour) {
                $lastCheckedAt = DB::table('price_sources')
                    ->where('tour_id', $tour->id)
                    ->max('last_checked_at');
                if ($lastCheckedAt) {
                    DB::table('tours')->where('id', $tour->id)->update([
                        'prices_checked_at' => $lastCheckedAt,
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->dropColumn('prices_checked_at');
        });
    }
};
