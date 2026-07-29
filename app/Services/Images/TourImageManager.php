<?php

namespace App\Services\Images;

use App\Models\Tour;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class TourImageManager
{
    public function orderedPaths(Tour $tour): Collection
    {
        return collect([$tour->cover_image])
            ->concat($tour->gallery ?? [])
            ->filter()
            ->unique()
            ->values();
    }

    public function prependUploads(Tour $tour, array $uploads): array
    {
        $newPaths = collect();

        try {
            foreach ($uploads as $upload) {
                $newPaths->push($upload->store('tours/manual/'.$tour->id, 'public'));
            }

            $orderedPaths = $newPaths
                ->concat($this->orderedPaths($tour))
                ->unique()
                ->values();
            $sources = collect($tour->image_sources ?? [])
                ->concat($newPaths->map(fn (string $path) => [
                    'path' => $path,
                    'page_url' => null,
                    'artist' => 'آپلود دستی',
                    'license' => 'تصویر اختصاصی',
                    'license_url' => null,
                ]));

            $this->persistOrder($tour, $orderedPaths, $sources);
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($newPaths->all());

            throw $exception;
        }

        return $newPaths->all();
    }

    public function reorder(Tour $tour, array $paths): void
    {
        $current = $this->orderedPaths($tour);
        $requested = collect($paths)->filter()->values();

        if ($requested->count() !== $current->count()
            || $requested->unique()->count() !== $requested->count()
            || $requested->diff($current)->isNotEmpty()
            || $current->diff($requested)->isNotEmpty()) {
            throw ValidationException::withMessages([
                'images' => 'فهرست تصاویر تغییر کرده است؛ صفحه را تازه‌سازی و دوباره مرتب کنید.',
            ]);
        }

        $this->persistOrder($tour, $requested, collect($tour->image_sources ?? []));
    }

    private function persistOrder(Tour $tour, Collection $paths, Collection $sources): void
    {
        $sourcesByPath = $sources
            ->filter(fn (array $source) => filled($source['path'] ?? null))
            ->keyBy('path');
        $orderedSources = $paths
            ->map(fn (string $path) => $sourcesByPath->get($path))
            ->filter()
            ->values()
            ->all();

        $tour->update([
            'cover_image' => $paths->first(),
            'gallery' => $paths->skip(1)->values()->all(),
            'image_sources' => $orderedSources,
        ]);
    }
}
