<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tour_suggestions', function (Blueprint $table) {
            $table->id();
            $table->string('keyword')->unique();
            $table->string('suggested_title');
            $table->string('destination')->nullable()->index();
            $table->unsignedSmallInteger('trend_score')->default(0)->index();
            $table->string('source')->default('google_trends');
            $table->string('status')->default('pending')->index();
            $table->json('metadata')->nullable();
            $table->timestamp('discovered_at')->nullable();
            $table->foreignId('tour_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('sync_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type')->index();
            $table->string('status')->default('running')->index();
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('successful')->default(0);
            $table->unsignedInteger('failed')->default(0);
            $table->json('details')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_runs');
        Schema::dropIfExists('tour_suggestions');
    }
};
