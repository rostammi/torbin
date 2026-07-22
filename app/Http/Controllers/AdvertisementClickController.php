<?php

namespace App\Http\Controllers;

use App\Models\Advertisement;
use Illuminate\Http\RedirectResponse;

class AdvertisementClickController extends Controller
{
    public function __invoke(Advertisement $advertisement): RedirectResponse
    {
        $advertisement->increment('clicks');

        return redirect()->away($advertisement->destination_url);
    }
}
