<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agencies', function (Blueprint $table) {
            $table->bigInteger('balance')->default(1_000_000)->change();
            $table->unsignedBigInteger('cost_per_click')->default(1_000)->change();
        });

        DB::table('agencies')->update([
            'balance' => 1_000_000,
            'cost_per_click' => 1_000,
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('agencies', function (Blueprint $table) {
            $table->bigInteger('balance')->default(0)->change();
            $table->unsignedBigInteger('cost_per_click')->default(0)->change();
        });
    }
};
