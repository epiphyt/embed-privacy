<?php

declare(strict_types=1);

namespace Tests\Unit\integration;

use epiphyt\Embed_Privacy\integration\Amp;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

use function Brain\Monkey\Functions\when;
use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;

#[CoversClass(Amp::class)]
final class AmpTest extends MockeryTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        setUp();
    }

    // separate process so is_amp_endpoint() is genuinely undefined regardless of
    // other tests that define it via Brain Monkey (function definitions leak per-process)
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testIsAmpReturnsFalseWithoutAmpEndpointFunction(): void
    {
        // is_amp_endpoint() is not defined, so the guard short-circuits to false
        $this->assertFalse(Amp::is_amp());
    }

    public function testIsAmpReturnsTrueOnAmpEndpoint(): void
    {
        when('is_amp_endpoint')->justReturn(true);

        $this->assertTrue(Amp::is_amp());
    }

    public function testIsAmpReturnsFalseWhenNotAmpEndpoint(): void
    {
        when('is_amp_endpoint')->justReturn(false);

        $this->assertFalse(Amp::is_amp());
    }

    protected function tearDown(): void
    {
        tearDown();
        parent::tearDown();
    }
}
