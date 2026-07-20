<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agencies', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->unique();
            $table->bigInteger('balance')->default(0);
            $table->unsignedBigInteger('cost_per_click')->default(0);
            $table->string('currency')->default('تومان');
            $table->timestamps();
        });

        Schema::table('price_sources', function (Blueprint $table) {
            $table->foreignId('agency_id')->nullable()->after('tour_id')->constrained()->nullOnDelete();
        });

        DB::table('price_sources')->distinct()->orderBy('provider_name')->pluck('provider_name')->each(function ($providerName) {
            $agencyId = DB::table('agencies')->insertGetId([
                'name' => $providerName,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('price_sources')->where('provider_name', $providerName)->update(['agency_id' => $agencyId]);
        });

        Schema::create('outbound_clicks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('price_source_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('tour_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('charged_amount')->default(0);
            $table->string('currency')->default('تومان');
            $table->string('status')->index();
            $table->char('ip_hash', 64)->nullable()->index();
            $table->char('user_agent_hash', 64)->nullable();
            $table->text('destination_url');
            $table->timestamp('clicked_at')->index();
            $table->timestamps();
        });

        Schema::create('agency_credit_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outbound_click_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->bigInteger('amount');
            $table->bigInteger('balance_after');
            $table->string('type')->index();
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agency_credit_transactions');
        Schema::dropIfExists('outbound_clicks');
        Schema::table('price_sources', function (Blueprint $table) {
            $table->dropConstrainedForeignId('agency_id');
        });
        Schema::dropIfExists('agencies');
    }
};
