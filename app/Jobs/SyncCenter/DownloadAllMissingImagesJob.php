<?php

namespace App\Jobs\SyncCenter;

class DownloadAllMissingImagesJob extends MissingImagesJob
{
    protected function category(): ?string
    {
        return null;
    }
}
