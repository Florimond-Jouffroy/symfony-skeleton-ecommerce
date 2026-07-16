<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Cache\CacheItemPoolInterface;

class RateLimiterService
{
    public function __construct(
        private readonly CacheItemPoolInterface $cache,
    ) {
    }

    /**
     * Returns true if the IP is still allowed to attempt (below the limit).
     * If maxAttempts is 0 or less, rate limiting is disabled.
     */
    public function isAllowed(string $type, string $ip, int $maxAttempts): bool
    {
        if ($maxAttempts <= 0) {
            return true;
        }

        $item = $this->cache->getItem($this->key($type, $ip));

        return !$item->isHit() || ($item->get()['count'] ?? 0) < $maxAttempts;
    }

    /**
     * Records a failed attempt. The window starts on the first hit and is never extended.
     */
    public function hit(string $type, string $ip, int $windowSeconds): void
    {
        $key  = $this->key($type, $ip);
        $item = $this->cache->getItem($key);

        if ($item->isHit()) {
            $data      = $item->get();
            $expiresAt = $data['expires_at'] ?? (time() + $windowSeconds);
            $remaining = max(1, $expiresAt - time());

            $item->set(['count' => ($data['count'] ?? 0) + 1, 'expires_at' => $expiresAt]);
            $item->expiresAfter($remaining);
        } else {
            $expiresAt = time() + $windowSeconds;
            $item->set(['count' => 1, 'expires_at' => $expiresAt]);
            $item->expiresAfter($windowSeconds);
        }

        $this->cache->save($item);
    }

    /**
     * Clears the attempt counter (call on successful authentication).
     */
    public function reset(string $type, string $ip): void
    {
        $this->cache->deleteItem($this->key($type, $ip));
    }

    /**
     * Returns the number of seconds remaining before the window expires.
     */
    public function getRetryAfter(string $type, string $ip): int
    {
        $item = $this->cache->getItem($this->key($type, $ip));

        if (!$item->isHit()) {
            return 0;
        }

        return max(0, ($item->get()['expires_at'] ?? 0) - time());
    }

    private function key(string $type, string $ip): string
    {
        return 'rate_limit_' . $type . '_' . md5($ip);
    }
}
