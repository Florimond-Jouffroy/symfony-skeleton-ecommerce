<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\RateLimiterService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

class RateLimiterServiceTest extends TestCase
{
    private RateLimiterService $limiter;
    private ArrayAdapter $cache;

    protected function setUp(): void
    {
        $this->cache   = new ArrayAdapter();
        $this->limiter = new RateLimiterService($this->cache);
    }

    public function testIsAllowedWhenNoHits(): void
    {
        self::assertTrue($this->limiter->isAllowed('login', '1.2.3.4', 5));
    }

    public function testIsAllowedBelowLimit(): void
    {
        $this->limiter->hit('login', '1.2.3.4', 900);
        $this->limiter->hit('login', '1.2.3.4', 900);

        self::assertTrue($this->limiter->isAllowed('login', '1.2.3.4', 5));
    }

    public function testIsAllowedReturnsFalseAtLimit(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->limiter->hit('login', '1.2.3.4', 900);
        }

        self::assertFalse($this->limiter->isAllowed('login', '1.2.3.4', 5));
    }

    public function testIsAllowedWhenDisabled(): void
    {
        for ($i = 0; $i < 100; $i++) {
            $this->limiter->hit('login', '1.2.3.4', 900);
        }

        self::assertTrue($this->limiter->isAllowed('login', '1.2.3.4', 0));
    }

    public function testDifferentTypesAreIsolated(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->limiter->hit('login', '1.2.3.4', 900);
        }

        self::assertFalse($this->limiter->isAllowed('login', '1.2.3.4', 5));
        self::assertTrue($this->limiter->isAllowed('2fa', '1.2.3.4', 5));
    }

    public function testDifferentIpsAreIsolated(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->limiter->hit('login', '1.2.3.4', 900);
        }

        self::assertFalse($this->limiter->isAllowed('login', '1.2.3.4', 5));
        self::assertTrue($this->limiter->isAllowed('login', '5.6.7.8', 5));
    }

    public function testResetClearsCounter(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->limiter->hit('login', '1.2.3.4', 900);
        }
        self::assertFalse($this->limiter->isAllowed('login', '1.2.3.4', 5));

        $this->limiter->reset('login', '1.2.3.4');

        self::assertTrue($this->limiter->isAllowed('login', '1.2.3.4', 5));
    }

    public function testGetRetryAfterReturnsZeroWhenNoEntry(): void
    {
        self::assertSame(0, $this->limiter->getRetryAfter('login', '1.2.3.4'));
    }

    public function testGetRetryAfterReturnsPositiveValue(): void
    {
        $this->limiter->hit('login', '1.2.3.4', 900);

        self::assertGreaterThan(0, $this->limiter->getRetryAfter('login', '1.2.3.4'));
        self::assertLessThanOrEqual(900, $this->limiter->getRetryAfter('login', '1.2.3.4'));
    }

    public function testWindowIsNotExtendedOnSubsequentHits(): void
    {
        $this->limiter->hit('login', '1.2.3.4', 60);
        $firstRetryAfter = $this->limiter->getRetryAfter('login', '1.2.3.4');

        sleep(1);

        $this->limiter->hit('login', '1.2.3.4', 60);
        $secondRetryAfter = $this->limiter->getRetryAfter('login', '1.2.3.4');

        // Window should not be extended: second should be <= first - 1 second
        self::assertLessThanOrEqual($firstRetryAfter, $secondRetryAfter + 1);
    }
}
