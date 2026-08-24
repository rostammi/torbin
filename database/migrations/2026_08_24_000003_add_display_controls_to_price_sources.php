<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('price_sources', function (Blueprint $table) {
            $table->boolean('is_contact_only')->default(false)->after('is_featured')->index();
            $table->string('contact_phone', 40)->nullable()->after('is_contact_only');
            $table->unsignedInteger('display_priority')->default(100)->after('contact_phone')->index();
            $table->boolean('is_pinned')->default(false)->after('display_priority')->index();
        });
    }

    public function down(): void
    {
        Schema::table('price_sources', function (Blueprint $table) {
            $table->dropColumn(['is_contact_only', 'contact_phone', 'display_priority', 'is_pinned']);
        });
    }
};
