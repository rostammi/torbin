<?php

namespace App\Services\Billing;

use App\Models\Agency;
use App\Models\OutboundClick;
use App\Models\PriceSource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AgencyBillingService
{
    public function registerClick(PriceSource $source, Request $request): OutboundClick
    {
        return DB::transaction(function () use ($source, $request) {
            $agency = Agency::query()->lockForUpdate()->findOrFail($source->agency_id);
            $cost = $agency->cost_per_click;
            $status = $agency->balance <= 0
                ? 'insufficient_credit'
                : ($cost === 0 ? 'free' : ($agency->balance >= $cost ? 'charged' : 'insufficient_credit'));

            $click = OutboundClick::create([
                'agency_id' => $agency->id,
                'price_source_id' => $source->id,
                'tour_id' => $source->tour_id,
                'charged_amount' => $status === 'charged' ? $cost : 0,
                'currency' => $agency->currency,
                'status' => $status,
                'ip_hash' => $request->ip() ? hash_hmac('sha256', $request->ip(), config('app.key')) : null,
                'user_agent_hash' => $request->userAgent() ? hash('sha256', $request->userAgent()) : null,
                'destination_url' => $source->buy_url ?: $source->source_url,
                'clicked_at' => now(),
            ]);

            if ($status === 'charged') {
                $agency->decrement('balance', $cost);
                $agency->refresh();
                $agency->creditTransactions()->create([
                    'outbound_click_id' => $click->id,
                    'amount' => -$cost,
                    'balance_after' => $agency->balance,
                    'type' => 'click_charge',
                    'note' => "هزینه کلیک روی {$source->tour->title}",
                ]);
            }

            return $click;
        }, 3);
    }

    public function adjustBalance(Agency $agency, int $amount, string $type, ?string $note, User $admin): void
    {
        DB::transaction(function () use ($agency, $amount, $type, $note, $admin) {
            $locked = Agency::query()->lockForUpdate()->findOrFail($agency->id);
            $signedAmount = $type === 'credit' ? $amount : -$amount;
            $newBalance = $locked->balance + $signedAmount;

            if ($newBalance < 0) {
                throw ValidationException::withMessages([
                    'amount' => 'موجودی آژانس برای این میزان کاهش کافی نیست.',
                ]);
            }

            $locked->update(['balance' => $newBalance]);
            $locked->creditTransactions()->create([
                'user_id' => $admin->id,
                'amount' => $signedAmount,
                'balance_after' => $newBalance,
                'type' => $type === 'credit' ? 'manual_credit' : 'manual_debit',
                'note' => $note,
            ]);
        }, 3);
    }
}
