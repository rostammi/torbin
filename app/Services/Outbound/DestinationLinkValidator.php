<?php

namespace App\Services\Outbound;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class DestinationLinkValidator
{
    public const VALID = 'valid';

    public const BROKEN = 'broken';

    public const UNKNOWN = 'unknown';

    public function check(string $url): string
    {
        try {
            $this->assertPublicUrl($url);
            $response = $this->request($url);

            if (in_array($response->status(), [404, 410], true)) {
                // Some marketplaces intermittently return a false 404 while
                // rotating inventory. Only quarantine after confirmation.
                $confirmation = $this->request($url);

                return in_array($confirmation->status(), [404, 410], true)
                    ? self::BROKEN
                    : self::UNKNOWN;
            }

            return $response->status() >= 200 && $response->status() < 400
                ? self::VALID
                : self::UNKNOWN;
        } catch (Throwable) {
            // A timeout, DNS problem, rate limit or bot protection is not
            // proof that a customer-facing link is dead.
            return self::UNKNOWN;
        }
    }

    private function request(string $url)
    {
        return Http::timeout(8)
            ->connectTimeout(3)
            ->withUserAgent(config('crawler.user_agent'))
            ->withOptions(['allow_redirects' => false, 'stream' => true])
            ->get($url);
    }

    private function assertPublicUrl(string $url): void
    {
        $parts = parse_url($url);
        $host = $parts['host'] ?? '';
        if (! in_array($parts['scheme'] ?? '', ['http', 'https'], true) || $host === '') {
            throw new RuntimeException('آدرس مقصد معتبر نیست.');
        }

        $addresses = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);
        if ($addresses === []) {
            throw new RuntimeException('دامنه مقصد قابل دسترسی نیست.');
        }
        foreach ($addresses as $address) {
            if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                throw new RuntimeException('بررسی آدرس‌های داخلی مجاز نیست.');
            }
        }
    }
}
