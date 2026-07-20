<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('price_sources', function (Blueprint $table) {
            $table->json('content_insights')->nullable()->after('latest_details');
            $table->timestamp('content_checked_at')->nullable()->after('content_insights');
            $table->text('content_error')->nullable()->after('content_checked_at');
        });

        Schema::table('tours', function (Blueprint $table) {
            $table->json('auto_content')->nullable()->after('description');
            $table->timestamp('auto_content_updated_at')->nullable()->after('auto_content');
        });
    }

    public function down(): void
    {
        Schema::table('price_sources', function (Blueprint $table) {
            $table->dropColumn(['content_insights', 'content_checked_at', 'content_error']);
        });

        Schema::table('tours', function (Blueprint $table) {
            $table->dropColumn(['auto_content', 'auto_content_updated_at']);
        });
    }
};
