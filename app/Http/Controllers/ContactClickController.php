<?php

namespace App\Http\Controllers;

use App\Models\ContactClick;
use App\Models\PriceSource;
use App\Models\SiteSetting;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ContactClickController extends Controller
{
    public function __invoke(Request $request, PriceSource $source): Response
    {
        $source->loadMissing('agency');
        abort_unless($source->is_active && ($source->is_contact_only || $source->latest_price <= 0), 404);

        $agencyPhone = trim((string) $source->contact_phone);
        $contactType = $agencyPhone !== '' ? ContactClick::TYPE_AGENCY : ContactClick::TYPE_GENERAL;
        $phone = $agencyPhone !== '' ? $agencyPhone : SiteSetting::comparisonContactPhone();

        ContactClick::create([
            'agency_id' => $source->agency_id,
            'price_source_id' => $source->id,
            'tour_id' => $source->tour_id,
            'contact_type' => $contactType,
            'phone' => $phone,
            'ip_hash' => $request->ip() ? hash_hmac('sha256', $request->ip(), config('app.key')) : null,
            'user_agent_hash' => $request->userAgent() ? hash('sha256', $request->userAgent()) : null,
            'clicked_at' => now(),
        ]);

        return response()->noContent();
    }
}
