<?php

namespace App\Services\Images;

use App\Models\Advertisement;
use App\Models\Tour;
use Illuminate\Support\Facades\Storage;
use Throwable;

class AdvertisementImageWebpMigrator
{
    public function __construct(private readonly WebpImageConverter $converter) {}

    public function migrate(Advertisement $advertisement): array
    {
        $oldPath = $advertisement->image_path;
        if (! $oldPath) {
            return ['converted' => 0, 'skipped' => 0, 'deleted' => 0];
        }

        $newPath = $this->converter->convertStored($oldPath);
        if ($newPath === $oldPath) {
            return ['converted' => 0, 'skipped' => 1, 'deleted' => 0];
        }
        try {
            $advertisement->update(['image_path' => $newPath]);
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($newPath);

            throw $exception;
        }

        $stillReferenced = Advertisement::query()->where('image_path', $oldPath)->exists()
            || Tour::query()->where('cover_image', $oldPath)->orWhereJsonContains('gallery', $oldPath)->exists();
        $deleted = ! $stillReferenced && Storage::disk('public')->delete($oldPath) ? 1 : 0;

        return ['converted' => 1, 'skipped' => 0, 'deleted' => $deleted];
    }
}
