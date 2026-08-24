<?php

namespace App\Jobs\SyncCenter;

class DownloadMissingStayImagesJob extends MissingImagesJob
{
    protected function category(): ?string
    {
        return 'stay';
    }
}
