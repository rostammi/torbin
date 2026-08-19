<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('price_alerts', function (Blueprint $table) {
            $table->dropUnique(['tour_id', 'phone_hash']);
            $table->unsignedBigInteger('target_price')->nullable()->change();
            $table->string('origin', 30)->default('price_drop')->after('phone_hash')->index();
            $table->string('contact_status', 20)->default('pending')->after('origin')->index();
            $table->timestamp('contacted_at')->nullable()->after('contact_status');
            $table->unique(['tour_id', 'phone_hash', 'origin']);
        });
    }

    public function down(): void
    {
        Schema::table('price_alerts', function (Blueprint $table) {
            $table->dropUnique(['tour_id', 'phone_hash', 'origin']);
            $table->dropIndex(['origin']);
            $table->dropIndex(['contact_status']);
            $table->dropColumn(['origin', 'contact_status', 'contacted_at']);
            $table->unsignedBigInteger('target_price')->nullable(false)->change();
            $table->unique(['tour_id', 'phone_hash']);
        });
    }
};
