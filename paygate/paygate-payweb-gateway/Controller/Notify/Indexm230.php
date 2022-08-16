<?php
/** @noinspection PhpUndefinedNamespaceInspection */

/** @noinspection PhpUnused */

/*
 * Copyright (c) 2022 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 *
 * Magento v2.3.0+ implement CsrfAwareActionInterface but not earlier versions
 */

namespace PayGate\PayWeb\Controller\Notify;

use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;

class Indexm230 extends Indexm220 implements CsrfAwareActionInterface
{
    /**
     * @inheritDoc
     * @noinspection PhpUnusedParameterInspection
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /**
     * @inheritDoc
     * @noinspection PhpUnusedParameterInspection
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

}
