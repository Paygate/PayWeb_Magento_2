<?php
/*
 * Copyright (c) 2021 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

namespace PayGate\PayWeb\Controller;

use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\InvalidRequestException;

/**
 * Checkout Controller
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
abstract class AbstractPaygatem230 extends AbstractPaygatem220 implements CsrfAwareActionInterface
{
    /**
     * @inheritDoc
     */
    public function createCsrfValidationException(
        RequestInterface $request
    ):  ? InvalidRequestException {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function validateForCsrf( RequestInterface $request ) :  ? bool
    {
        return true;
    }
}
