<?php

namespace App\Services\Images;

use App\Models\Advertisement;
use App\Models\Tour;
use Illuminate\Support\Facades\Storage;
use Throwable;

class UnreferencedPublicImageWebpMigrator
{
    public function __construct(private readonly WebpImageConverter $converter) {}

    public function migrate(): array
    {
        $disk = Storage::disk('public');
        $references = $this->referencedPaths();
        $result = ['converted' => 0, 'deleted' => 0, 'skipped_referenced' => 0, 'failures' => []];

        foreach ($disk->allFiles() as $path) {
            if (! in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['jpg', 'jpeg'], true)) {
                continue;
            }

            if ($references->contains($path)) {
                $result['skipped_referenced']++;

                continue;
            }

            try {
                $newPath = $this->converter->convertStored($path);
                if ($newPath !== $path) {
                    $result['converted']++;
                    if ($disk->delete($path)) {
                        $result['deleted']++;
                    }
                }
            } catch (Throwable $exception) {
                if (count($result['failures']) < 25) {
                    $result['failures'][] = [
                        'path' => $path,
                        'error' => mb_substr($exception->getMessage(), 0, 500),
                    ];
                }
                report($exception);
            }
        }

        return $result;
    }

    private function referencedPaths()
    {
        $tourPaths = Tour::query()
            ->get(['cover_image', 'gallery', 'image_sources'])
            ->flatMap(fn (Tour $tour) => collect([$tour->cover_image])
                ->concat($tour->gallery ?? [])
                ->concat(collect($tour->image_sources ?? [])->pluck('path')));

        return $tourPaths
            ->concat(Advertisement::query()->pluck('image_path'))
            ->filter()
            ->unique()
            ->values();
    }
}
