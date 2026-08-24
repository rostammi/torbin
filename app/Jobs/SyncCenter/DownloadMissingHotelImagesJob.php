<?php

namespace App\Jobs\SyncCenter;

class DownloadMissingHotelImagesJob extends MissingImagesJob
{
    protected function category(): ?string
    {
        return 'hotel';
    }
}
