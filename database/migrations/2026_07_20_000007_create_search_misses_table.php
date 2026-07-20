<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_misses', function (Blueprint $table) {
            $table->id();
            $table->string('query', 200);
            $table->string('normalized_query', 200)->index();
            $table->char('ip_hash', 64)->nullable()->index();
            $table->timestamp('searched_at')->index();
            $table->timestamps();

            $table->index(['normalized_query', 'searched_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_misses');
    }
};
