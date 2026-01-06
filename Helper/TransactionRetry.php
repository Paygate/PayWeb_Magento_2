<?php

/*
 * Copyright (c) 2025 Payfast (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

namespace PayGate\PayWeb\Helper;

use Magento\Framework\DB\Adapter\DeadlockException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;

class TransactionRetry
{
    private const MAX_RETRY_ATTEMPTS = 3;
    private const BASE_RETRY_DELAY = 100000; // 100ms in microseconds

    private LoggerInterface $logger;
    private OrderRepositoryInterface $orderRepository;

    public function __construct(
        LoggerInterface $logger,
        OrderRepositoryInterface $orderRepository
    ) {
        $this->logger = $logger;
        $this->orderRepository = $orderRepository;
    }

    /**
     * Execute order save with deadlock retry logic
     *
     * @param Order $order
     * @param callable|null $preReloadCallback Optional callback to execute before order reload
     * @throws LocalizedException
     */
    public function retryOrderSave(Order $order, ?callable $preReloadCallback = null): void
    {
        $attempt = 0;
        $orderId = $order->getId();

        while ($attempt < self::MAX_RETRY_ATTEMPTS) {
            try {
                $this->orderRepository->save($order);

                if ($attempt > 0) {
                    $this->logger->info("Order {$orderId} saved successfully after {$attempt} retry attempts");
                }

                return; // Success, exit the retry loop

            } catch (DeadlockException $e) {
                $attempt++;

                if ($attempt >= self::MAX_RETRY_ATTEMPTS) {
                    $this->logger->critical(
                        "Max deadlock retry attempts ({$attempt}) exceeded for order: {$orderId}",
                        ['exception' => $e]
                    );
                    throw new LocalizedException(
                        __('Unable to save order after multiple attempts. Order: %1', $orderId)
                    );
                }

                // Exponential backoff with jitter
                $delay = self::BASE_RETRY_DELAY * pow(2, $attempt - 1) + rand(0, 50000);
                usleep($delay);

                $this->logger->info(
                    "Deadlock detected, retrying order save. Attempt: {$attempt} for order: {$orderId}"
                );

                // Reload the order to get fresh data
                $order = $this->orderRepository->get($orderId);

                // Execute pre-reload callback if provided
                if ($preReloadCallback && is_callable($preReloadCallback)) {
                    $preReloadCallback($order);
                }

            } catch (\Exception $e) {
                // For non-deadlock exceptions, don't retry
                $this->logger->critical(
                    "Non-deadlock exception in order processing: {$e->getMessage()}",
                    ['exception' => $e, 'order_id' => $orderId]
                );
                throw $e;
            }
        }
    }

    /**
     * Execute any callback with deadlock retry logic
     *
     * @param callable $callback
     * @param array $args
     * @param string $operation Description of the operation
     * @return mixed
     * @throws LocalizedException
     */
    public function retryOperation(callable $callback, array $args = [], string $operation = 'database operation'): mixed
    {
        $attempt = 0;

        while ($attempt < self::MAX_RETRY_ATTEMPTS) {
            try {
                $result = call_user_func_array($callback, $args);

                if ($attempt > 0) {
                    $this->logger->info("'{$operation}' completed successfully after {$attempt} retry attempts");
                }

                return $result; // Success, return result

            } catch (DeadlockException $e) {
                $attempt++;

                if ($attempt >= self::MAX_RETRY_ATTEMPTS) {
                    $this->logger->critical(
                        "Max deadlock retry attempts ({$attempt}) exceeded for operation: {$operation}",
                        ['exception' => $e]
                    );
                    throw new LocalizedException(
                        __('Unable to complete %1 after multiple attempts.', $operation)
                    );
                }

                // Exponential backoff with jitter
                $delay = self::BASE_RETRY_DELAY * pow(2, $attempt - 1) + rand(0, 50000);
                usleep($delay);

                $this->logger->info("Deadlock detected, retrying '{$operation}'. Attempt: {$attempt}");

            } catch (\Exception $e) {
                // For non-deadlock exceptions, don't retry
                $this->logger->critical(
                    "Non-deadlock exception in '{$operation}': {$e->getMessage()}",
                    ['exception' => $e]
                );
                throw $e;
            }
        }

        return null;
    }
}
