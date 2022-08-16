<?php
/** @noinspection PhpMissingFieldTypeInspection */

/** @noinspection PhpUndefinedNamespaceInspection */

/** @noinspection PhpUnused */

/*
 * Copyright (c) 2022 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

namespace PayGate\PayWeb\Helper;

use Magento\Framework\App\Config\BaseFactory;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Psr\Log\LoggerInterface;

/**
 * PayGate Data helper
 */
class Cron extends AbstractHelper
{

    /**
     * @var LoggerInterface
     */
    protected $_logger;

    /**
     * @param Context $context
     */
    public function __construct(
        Context $context
    ) {
        $this->_logger = $context->getLogger();
    }

}
