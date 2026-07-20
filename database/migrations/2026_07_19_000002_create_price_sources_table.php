<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tour_id')->constrained()->cascadeOnDelete();
            $table->string('provider_name');
            $table->text('source_url');
            $table->text('buy_url')->nullable();
            $table->string('extraction_type')->default('regex');
            $table->text('selector')->nullable();
            $table->decimal('price_multiplier', 10, 2)->default(1);
            $table->unsignedBigInteger('latest_price')->nullable()->index();
            $table->string('currency')->default('تومان');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('last_checked_at')->nullable();
            $table->string('last_status')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_sources');
    }
};
