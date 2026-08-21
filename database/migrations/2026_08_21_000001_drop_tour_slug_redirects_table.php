<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('tour_slug_redirects');
    }

    public function down(): void
    {
        // Legacy slug redirects are intentionally not restored.
    }
};
