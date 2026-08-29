<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $paths = [
        'worldwide-tours.jpg' => 'worldwide-tours.webp',
        'copacabana-beach.jpg' => 'copacabana-beach.webp',
        'maldives.jpg' => 'maldives.webp',
        'france.jpg' => 'france.webp',
        'dubai.jpg' => 'dubai.webp',
        'villa-bushehr.jpeg' => 'villa-bushehr.webp',
        'villa-shirgah.jpg' => 'villa-shirgah.webp',
        'villa-main.jpg' => 'villa-main.webp',
        'domestic-tours.jpg' => 'domestic-tours.webp',
        'sea.jpg' => 'sea.webp',
        'mesr-desert.jpg' => 'mesr-desert.webp',
        'badab-surt.jpg' => 'badab-surt.webp',
        'masal.jpg' => 'masal.webp',
        'domestic-hotels.jpeg' => 'domestic-hotels.webp',
        'hotel-spinas.jpg' => 'hotel-spinas.webp',
        'kermanshah-hotel.jpg' => 'kermanshah-hotel.webp',
        'hotel-3.jpg' => 'hotel-3.webp',
        'worldwide-hotels.jpg' => 'worldwide-hotels.webp',
        'hotels-1.jpg' => 'hotels-1.webp',
        'hotels-pool.jpg' => 'hotels-pool.webp',
    ];

    public function up(): void
    {
        $this->replacePaths($this->paths);
    }

    public function down(): void
    {
        $this->replacePaths(array_flip($this->paths));
    }

    private function replacePaths(array $paths): void
    {
        DB::table('static_pages')
            ->where('slug', 'like', '%')
            ->orderBy('id')
            ->get(['id', 'content'])
            ->each(function (object $page) use ($paths): void {
                $content = str_replace(
                    array_map(fn (string $path) => '/images/mag/'.$path, array_keys($paths)),
                    array_map(fn (string $path) => '/images/mag/'.$path, array_values($paths)),
                    (string) $page->content,
                );

                if ($content !== $page->content) {
                    DB::table('static_pages')->where('id', $page->id)->update(['content' => $content]);
                }
            });
    }
};
