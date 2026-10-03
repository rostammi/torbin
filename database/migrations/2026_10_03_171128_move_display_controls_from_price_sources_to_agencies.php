<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agencies', function (Blueprint $table) {
            $table->string('provider_key', 120)->nullable()->index();
            $table->boolean('is_featured')->default(false)->index();
            $table->boolean('is_contact_only')->default(false)->index();
            $table->string('contact_phone', 40)->nullable();
            $table->unsignedInteger('display_priority')->default(100)->index();
            $table->boolean('is_pinned')->default(false)->index();
        });

        $canonicalName = static function (string $name): string {
            $name = str_replace(['ي', 'ك', "\u{200C}"], ['ی', 'ک', ' '], trim($name));
            $name = preg_replace('/\s+(?:تور|هتل|اقامتگاه|ویزا)\s*$/u', '', $name) ?? $name;

            return trim(preg_replace('/\s+/u', ' ', $name) ?? $name);
        };
        $providerKey = static fn (string $name): string => preg_replace(
            '/[^\pL\pN]+/u',
            '',
            mb_strtolower($canonicalName($name)),
        );

        DB::table('agencies')->orderBy('id')->get()->groupBy(fn ($agency) => $providerKey($agency->name))
            ->each(function ($agencies, string $key) use ($canonicalName) {
                $canonical = $agencies->sortBy(fn ($agency) => mb_strlen($agency->name))->first();
                $duplicateIds = $agencies->pluck('id')->reject(fn ($id) => $id === $canonical->id)->values();

                if ($duplicateIds->isNotEmpty()) {
                    foreach (['price_sources', 'outbound_clicks', 'agency_credit_transactions', 'users'] as $table) {
                        if (Schema::hasTable($table) && Schema::hasColumn($table, 'agency_id')) {
                            DB::table($table)->whereIn('agency_id', $duplicateIds)->update(['agency_id' => $canonical->id]);
                        }
                    }
                    DB::table('agencies')->whereIn('id', $duplicateIds)->delete();
                }

                DB::table('agencies')->where('id', $canonical->id)->update([
                    'name' => $canonicalName($canonical->name),
                    'provider_key' => $key,
                ]);
            });

        Schema::table('agencies', function (Blueprint $table) {
            $table->dropIndex('agencies_provider_key_index');
            $table->unique('provider_key');
        });

        DB::table('agencies')->select('id')->orderBy('id')->chunkById(200, function ($agencies) {
            foreach ($agencies as $agency) {
                $sources = DB::table('price_sources')->where('agency_id', $agency->id);
                $isPinned = (bool) (clone $sources)->max('is_pinned');
                DB::table('agencies')->where('id', $agency->id)->update([
                    'is_featured' => $isPinned || (bool) (clone $sources)->max('is_featured'),
                    'is_contact_only' => (bool) (clone $sources)->max('is_contact_only'),
                    'contact_phone' => (clone $sources)->whereNotNull('contact_phone')->value('contact_phone'),
                    'display_priority' => (clone $sources)->min('display_priority') ?? 100,
                    'is_pinned' => $isPinned,
                ]);
            }
        });

        Schema::table('price_sources', function (Blueprint $table) {
            $table->dropIndex('price_sources_is_featured_index');
            $table->dropIndex('price_sources_is_contact_only_index');
            $table->dropIndex('price_sources_display_priority_index');
            $table->dropIndex('price_sources_is_pinned_index');
            $table->dropColumn([
                'is_featured', 'is_contact_only', 'contact_phone', 'display_priority', 'is_pinned',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('price_sources', function (Blueprint $table) {
            $table->boolean('is_featured')->default(false)->index();
            $table->boolean('is_contact_only')->default(false)->index();
            $table->string('contact_phone', 40)->nullable();
            $table->unsignedInteger('display_priority')->default(100)->index();
            $table->boolean('is_pinned')->default(false)->index();
        });

        DB::table('agencies')->orderBy('id')->chunkById(200, function ($agencies) {
            foreach ($agencies as $agency) {
                DB::table('price_sources')->where('agency_id', $agency->id)->update([
                    'is_featured' => $agency->is_featured,
                    'is_contact_only' => $agency->is_contact_only,
                    'contact_phone' => $agency->contact_phone,
                    'display_priority' => $agency->display_priority,
                    'is_pinned' => $agency->is_pinned,
                ]);
            }
        });

        Schema::table('agencies', function (Blueprint $table) {
            $table->dropUnique('agencies_provider_key_unique');
            $table->dropColumn([
                'provider_key', 'is_featured', 'is_contact_only', 'contact_phone', 'display_priority', 'is_pinned',
            ]);
        });
    }
};
