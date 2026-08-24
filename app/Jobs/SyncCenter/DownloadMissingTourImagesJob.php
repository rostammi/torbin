<?php

namespace App\Jobs\SyncCenter;

class DownloadMissingTourImagesJob extends MissingImagesJob
{
    protected function category(): ?string
    {
        return 'tour';
    }
}
