<?php

/**
 * @noinspection PhpUnused
 */

namespace PayGate\PayWeb\Logger;

use Magento\Framework\Logger\Handler\Base;
use Psr\Log\LogLevel;

class Handler extends Base
{
    /**
     * Logging level
     *
     * @var int
     */
    protected $loggerType = LogLevel::INFO;

    /**
     * Naming convection
     *
     * @var string
     */
    protected $fileName = '/var/log/paygate-payweb.log';
}
