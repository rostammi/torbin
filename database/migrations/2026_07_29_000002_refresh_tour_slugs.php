<?php

use App\Services\TourSlugGenerator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tour_slug_redirects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tour_id')->constrained()->cascadeOnDelete();
            $table->string('old_slug')->unique();
            $table->timestamps();
        });

        app(TourSlugGenerator::class)->refreshAll();
    }

    public function down(): void
    {
        Schema::dropIfExists('tour_slug_redirects');
    }
};
