<?php
/*
 * Copyright (c) 2021 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

namespace PayGate\PayWeb\Helper;

/**
 * PayGate Data helper
 */
class Cron extends \Magento\Framework\App\Helper\AbstractHelper
{

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $_logger;

    /**
     * @param \Magento\Framework\App\Helper\Context $context
     * @param \Magento\Payment\Helper\Data $paymentData
     * @param \Magento\Framework\App\Config\BaseFactory $configFactory
     * @param array $methodCodes
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context
    ) {
        $this->_logger = $context->getLogger();
    }

}
