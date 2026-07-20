<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tour_id')->constrained()->cascadeOnDelete();
            $table->text('phone');
            $table->char('phone_hash', 64);
            $table->text('unsubscribe_token');
            $table->char('unsubscribe_token_hash', 64)->unique();
            $table->unsignedBigInteger('target_price');
            $table->string('currency')->default('تومان');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('last_notified_at')->nullable();
            $table->unsignedBigInteger('last_notified_price')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(['tour_id', 'phone_hash']);
            $table->index(['tour_id', 'is_active', 'target_price']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_alerts');
    }
};
