<?php
/** @noinspection PhpUnused */

/** @noinspection PhpUndefinedNamespaceInspection */

/** @noinspection PhpUndefinedNamespaceInspection */

/*
 * Copyright (c) 2022 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

namespace PayGate\PayWeb\Block\Customer;

use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Block\AbstractCardRenderer;

class CardRenderer extends AbstractCardRenderer
{
    /**
     * Can render specified token
     *
     * @param PaymentTokenInterface $token
     *
     * @return boolean
     * @since 100.1.0
     * @noinspection PhpUnused
     */
    public function canRender(PaymentTokenInterface $token): bool
    {
        return $token->getPaymentMethodCode() === "paygate";
    }

    /**
     * @return string
     * @since 100.1.0
     * @noinspection PhpUnused
     * @noinspection PhpUndefinedMethodInspection
     */
    public function getNumberLast4Digits(): string
    {
        return $this->getTokenDetails()['maskedCC'];
    }

    /**
     * @return string
     * @since 100.1.0
     * @noinspection PhpUnused
     * @noinspection PhpUndefinedMethodInspection
     */
    public function getExpDate(): string
    {
        return $this->getTokenDetails()['expirationDate'];
    }

    /**
     * @return string
     * @since 100.1.0
     * @noinspection PhpUnused
     * @noinspection PhpUndefinedMethodInspection
     */
    public function getIconUrl(): string
    {
        return $this->getIconForType($this->getTokenDetails()['type'])['url'];
    }

    /**
     * @return int
     * @since 100.1.0
     * @noinspection PhpUnused
     * @noinspection PhpUndefinedMethodInspection
     */
    public function getIconHeight(): int
    {
        return $this->getIconForType($this->getTokenDetails()['type'])['height'];
    }

    /**
     * @return int
     * @since 100.1.0
     * @noinspection PhpUnused
     * @noinspection PhpUndefinedMethodInspection
     */
    public function getIconWidth(): int
    {
        return $this->getIconForType($this->getTokenDetails()['type'])['width'];
    }
}
