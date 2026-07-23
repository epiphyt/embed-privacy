<?php

declare(strict_types=1);

namespace Tests\Unit\integration;

use epiphyt\Embed_Privacy\integration\Twitter;
use epiphyt\Embed_Privacy\integration\X;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;

/**
 * The deprecated Twitter integration is a thin, behaviour-free subclass of X kept
 * for backwards-compatibility. Its own body is empty, so these tests characterize
 * the inheritance contract via reflection rather than executing X's logic (which
 * would be attributed to X, not Twitter, under strict coverage metadata). The
 * transform logic itself is covered by XTest.
 */
#[CoversClass(Twitter::class)]
final class TwitterTest extends MockeryTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        setUp();
    }

    public function testTwitterExtendsX(): void
    {
        $this->assertTrue(\is_subclass_of(Twitter::class, X::class));
    }

    public function testTwitterAddsNoOwnMethods(): void
    {
        // the class only exists for backwards-compatibility and defines no own members
        $ownMethods = \array_filter(
            (new \ReflectionClass(Twitter::class))->getMethods(),
            static function (\ReflectionMethod $method): bool {
                return $method->getDeclaringClass()->getName() === Twitter::class;
            }
        );

        $this->assertSame([], $ownMethods);
    }

    public function testTwitterInheritsTransformMethodsFromX(): void
    {
        foreach (['init', 'get_local_tweet', 'set_local_tweet'] as $method) {
            $declaring = (new \ReflectionMethod(Twitter::class, $method))->getDeclaringClass()->getName();

            $this->assertSame(X::class, $declaring);
        }
    }

    protected function tearDown(): void
    {
        tearDown();
        parent::tearDown();
    }
}
