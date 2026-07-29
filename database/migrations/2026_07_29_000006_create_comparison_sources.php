<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comparison_sources', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('homepage_url');
            $table->char('homepage_hash', 64)->unique();
            $table->json('categories');
            $table->boolean('is_active')->default(true)->index();
            $table->string('last_status')->nullable()->index();
            $table->text('last_error')->nullable();
            $table->json('last_scan_summary')->nullable();
            $table->timestamp('last_scanned_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comparison_sources');
    }
};
