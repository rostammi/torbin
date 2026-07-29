<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->string('category', 20)->default('tour')->after('id')->index();
        });
        Schema::table('tour_suggestions', function (Blueprint $table) {
            $table->dropUnique(['destination']);
            $table->string('category', 20)->default('tour')->after('id')->index();
            $table->unique(['category', 'destination']);
        });
    }

    public function down(): void
    {
        Schema::table('tour_suggestions', function (Blueprint $table) {
            $table->dropUnique(['category', 'destination']);
            $table->dropColumn('category');
            $table->unique('destination');
        });
        Schema::table('tours', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }
};
