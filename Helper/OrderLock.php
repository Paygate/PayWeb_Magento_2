<?php

/*
 * Copyright (c) 2025 Payfast (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

namespace PayGate\PayWeb\Helper;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;

class OrderLock
{
    private const LOCK_PREFIX = 'paygate_order_lock_';
    private const LOCK_TIMEOUT = 30; // 30 seconds

    private CacheInterface $cache;
    private DateTime $dateTime;

    public function __construct(
        CacheInterface $cache,
        DateTime $dateTime
    ) {
        $this->cache = $cache;
        $this->dateTime = $dateTime;
    }

    /**
     * Acquire lock for order processing
     *
     * @param int|string $orderId
     * @return bool
     */
    public function acquireLock($orderId): bool
    {
        $lockKey = self::LOCK_PREFIX . $orderId;
        $lockValue = $this->dateTime->gmtTimestamp() + self::LOCK_TIMEOUT;

        // Try to set lock, return false if already exists
        if ($this->cache->load($lockKey)) {
            return false;
        }

        return $this->cache->save($lockValue, $lockKey, [], self::LOCK_TIMEOUT);
    }

    /**
     * Release lock for order processing
     *
     * @param int|string $orderId
     * @return void
     */
    public function releaseLock($orderId): void
    {
        $lockKey = self::LOCK_PREFIX . $orderId;
        $this->cache->remove($lockKey);
    }

    /**
     * Check if order is currently locked
     *
     * @param int|string $orderId
     * @return bool
     */
    public function isLocked($orderId): bool
    {
        $lockKey = self::LOCK_PREFIX . $orderId;
        $lockValue = $this->cache->load($lockKey);

        if (!$lockValue) {
            return false;
        }

        // Check if lock has expired
        if ($lockValue < $this->dateTime->gmtTimestamp()) {
            $this->cache->remove($lockKey);
            return false;
        }

        return true;
    }

    /**
     * Wait for lock to be released (with timeout)
     *
     * @param int|string $orderId
     * @param int $maxWaitTime Maximum wait time in seconds
     * @return bool True if lock was released, false if timeout
     */
    public function waitForLockRelease($orderId, int $maxWaitTime = 10): bool
    {
        $startTime = time();

        while ($this->isLocked($orderId)) {
            if (time() - $startTime >= $maxWaitTime) {
                return false;
            }
            usleep(100000); // Wait 100ms
        }

        return true;
    }
}
