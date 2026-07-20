<?php

namespace App\Services\Alerts;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class SmsSender
{
    public function send(string $phone, string $message): void
    {
        match (config('sms.driver')) {
            'kavenegar' => $this->sendWithKavenegar($phone, $message),
            'webhook' => $this->sendWithWebhook($phone, $message),
            'log' => Log::info('Price alert SMS', ['phone' => $phone, 'message' => $message]),
            default => throw new RuntimeException('درایور پیامک معتبر نیست.'),
        };
    }

    private function sendWithKavenegar(string $phone, string $message): void
    {
        $apiKey = config('sms.kavenegar.api_key');
        if (! $apiKey) {
            throw new RuntimeException('کلید API کاوه‌نگار تنظیم نشده است.');
        }

        Http::asForm()->timeout(20)->retry(2, 500)
            ->post("https://api.kavenegar.com/v1/{$apiKey}/sms/send.json", array_filter([
                'receptor' => $phone,
                'message' => $message,
                'sender' => config('sms.kavenegar.sender'),
            ]))
            ->throw();
    }

    private function sendWithWebhook(string $phone, string $message): void
    {
        $url = config('sms.webhook.url');
        if (! $url) {
            throw new RuntimeException('آدرس webhook پیامک تنظیم نشده است.');
        }

        $request = Http::acceptJson()->timeout(20)->retry(2, 500);
        if ($token = config('sms.webhook.token')) {
            $request = $request->withToken($token);
        }

        $request->post($url, ['to' => $phone, 'message' => $message])->throw();
    }
}
