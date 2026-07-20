<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('admin')->index()->after('password');
            $table->foreignId('agency_id')->nullable()->after('role')->constrained()->nullOnDelete();
        });

        Schema::create('tour_page_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tour_id')->constrained()->cascadeOnDelete();
            $table->char('ip_hash', 64)->nullable()->index();
            $table->char('user_agent_hash', 64)->nullable();
            $table->timestamp('viewed_at')->index();
            $table->timestamps();

            $table->index(['tour_id', 'viewed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tour_page_views');
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('agency_id');
            $table->dropColumn('role');
        });
    }
};
