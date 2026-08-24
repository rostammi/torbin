<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legacy_redirects', function (Blueprint $table) {
            $table->id();
            $table->string('old_path', 500)->unique();
            $table->text('source_url');
            $table->string('source_title')->nullable();
            $table->foreignId('tour_id')->nullable()->constrained()->nullOnDelete();
            $table->string('match_type', 20)->default('unmatched')->index();
            $table->unsignedBigInteger('hits')->default(0);
            $table->timestamp('last_hit_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        $now = now();
        DB::table('tour_suggestions')
            ->select(['id', 'tour_id', 'metadata'])
            ->whereNotNull('metadata')
            ->orderBy('id')
            ->chunkById(200, function ($suggestions) use ($now) {
                foreach ($suggestions as $suggestion) {
                    $metadata = is_string($suggestion->metadata)
                        ? json_decode($suggestion->metadata, true)
                        : (array) $suggestion->metadata;

                    foreach (data_get($metadata, 'geyt_references', []) as $reference) {
                        $sourceUrl = (string) data_get($reference, 'page_url');
                        $host = strtolower((string) parse_url($sourceUrl, PHP_URL_HOST));
                        $path = rawurldecode((string) parse_url($sourceUrl, PHP_URL_PATH));
                        $path = '/'.trim(preg_replace('~/+~', '/', $path) ?? '', '/');

                        if (! in_array($host, ['geyt.ir', 'www.geyt.ir'], true)
                            || ! preg_match('~^/(tour|hotel|accommodation|visa)/[^/]+/?$~u', $path)) {
                            continue;
                        }

                        $path = rtrim($path, '/').'/';
                        DB::table('legacy_redirects')->insertOrIgnore([
                            'old_path' => $path,
                            'source_url' => $sourceUrl,
                            'source_title' => data_get($reference, 'label'),
                            'tour_id' => $suggestion->tour_id,
                            'match_type' => $suggestion->tour_id ? 'automatic' : 'unmatched',
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_redirects');
    }
};
