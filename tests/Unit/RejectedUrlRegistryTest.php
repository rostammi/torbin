<?php

namespace Tests\Unit;

use App\Services\Outbound\RejectedUrlRegistry;
use PHPUnit\Framework\TestCase;

class RejectedUrlRegistryTest extends TestCase
{
    public function test_tracking_parameters_fragments_and_trailing_slashes_cannot_revive_a_rejected_url(): void
    {
        $registry = new RejectedUrlRegistry;
        $rejected = ['https://Example.com/tour/muscat/?b=2&a=1&utm_source=old#offer'];

        $this->assertTrue($registry->contains($rejected, 'https://example.com/tour/muscat?a=1&b=2'));
        $this->assertSame($rejected, $registry->add($rejected, 'https://example.com/tour/muscat?a=1&b=2'));
    }
}
