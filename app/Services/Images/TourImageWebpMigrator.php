<?php

namespace App\Services\Images;

use App\Models\Tour;
use Illuminate\Support\Facades\Storage;
use Throwable;

class TourImageWebpMigrator
{
    public function __construct(private readonly WebpImageConverter $converter) {}

    public function migrate(Tour $tour): array
    {
        $paths = collect([$tour->cover_image])
            ->concat($tour->gallery ?? [])
            ->filter()
            ->unique()
            ->values();
        $mapping = [];
        $created = [];
        $skipped = 0;

        try {
            foreach ($paths as $path) {
                $mapping[$path] = $this->converter->convertStored($path);
                if ($mapping[$path] === $path) {
                    $skipped++;
                    continue;
                }

                $created[] = $mapping[$path];
            }

            $sources = collect($tour->image_sources ?? [])->map(function (array $source) use ($mapping) {
                $path = $source['path'] ?? null;
                if ($path && isset($mapping[$path])) {
                    $source['path'] = $mapping[$path];
                }

                return $source;
            })->all();

            $tour->update([
                'cover_image' => $tour->cover_image ? ($mapping[$tour->cover_image] ?? $tour->cover_image) : null,
                'gallery' => collect($tour->gallery ?? [])->map(fn (string $path) => $mapping[$path] ?? $path)->all(),
                'image_sources' => $sources,
            ]);
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($created);

            throw $exception;
        }

        $deleted = 0;
        foreach (array_keys(array_filter($mapping, fn (string $new, string $old) => $new !== $old, ARRAY_FILTER_USE_BOTH)) as $oldPath) {
            $stillReferenced = Tour::query()
                ->where('cover_image', $oldPath)
                ->orWhereJsonContains('gallery', $oldPath)
                ->exists();
            if (! $stillReferenced && Storage::disk('public')->delete($oldPath)) {
                $deleted++;
            }
        }

        return ['converted' => count($created), 'skipped' => $skipped, 'deleted' => $deleted];
    }
}
