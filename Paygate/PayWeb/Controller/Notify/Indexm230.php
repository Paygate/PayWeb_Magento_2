<?php
/*
 * Copyright (c) 2021 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 *
 * Magento v2.3.0+ implement CsrfAwareActionInterface but not earlier versions
 */

namespace PayGate\PayWeb\Controller\Notify;

use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\InvalidRequestException;

class Indexm230 extends Indexm220 implements CsrfAwareActionInterface
{
    /**
     * @inheritDoc
     */
    public function createCsrfValidationException( RequestInterface $request ):  ? InvalidRequestException
    {
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
