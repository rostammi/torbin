<?php

use App\Services\TourSlugGenerator;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(TourSlugGenerator::class)->refreshAll();
    }

    public function down(): void
    {
        // Slug refreshes are intentionally irreversible.
    }
};
