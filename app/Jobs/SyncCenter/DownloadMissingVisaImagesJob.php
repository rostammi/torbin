<?php

namespace App\Jobs\SyncCenter;

class DownloadMissingVisaImagesJob extends MissingImagesJob
{
    protected function category(): ?string
    {
        return 'visa';
    }
}
