<?php
/** @noinspection PhpUndefinedNamespaceInspection */

/** @noinspection PhpUnused */

/*
 * Copyright (c) 2022 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

namespace PayGate\PayWeb\Controller;

use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;

/**
 * Checkout Controller
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
abstract class AbstractPaygatem230 extends AbstractPaygatem220 implements CsrfAwareActionInterface
{
    /**
     * @inheritDoc
     * @noinspection PhpUnusedParameterInspection
     */
    public function createCsrfValidationException(
        RequestInterface $request
    ): ?InvalidRequestException {
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
