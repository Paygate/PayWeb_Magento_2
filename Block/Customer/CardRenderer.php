<?php
/**
 * @noinspection PhpUnused
 */

/*
 * Copyright (c) 2024 Payfast (Pty) Ltd
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
     * @return       boolean
     * @since        100.1.0
     * @noinspection PhpUnused
     */
    public function canRender(PaymentTokenInterface $token): bool
    {
        return $token->getPaymentMethodCode() === "paygate";
    }

    /**
     * Returns Token Masked CC
     *
     * @return       string
     * @since        100.1.0
     * @noinspection PhpUnused
     */
    public function getNumberLast4Digits(): string
    {
        return $this->getTokenDetails()['maskedCC'];
    }

    /**
     * Gets Token Expiration Date
     *
     * @return       string
     * @since        100.1.0
     * @noinspection PhpUnused
     */
    public function getExpDate(): string
    {
        return $this->getTokenDetails()['expirationDate'];
    }

    /**
     * Gets the token icon URL
     *
     * @return       string
     * @since        100.1.0
     * @noinspection PhpUnused
     */
    public function getIconUrl(): string
    {
        return $this->getIconForType($this->getTokenDetails()['type'])['url'];
    }

    /**
     * Gets the token icon height
     *
     * @return       int
     * @since        100.1.0
     * @noinspection PhpUnused
     */
    public function getIconHeight(): int
    {
        return $this->getIconForType($this->getTokenDetails()['type'])['height'];
    }

    /**
     * Gets the token icon width
     *
     * @return       int
     * @since        100.1.0
     * @noinspection PhpUnused
     */
    public function getIconWidth(): int
    {
        return $this->getIconForType($this->getTokenDetails()['type'])['width'];
    }
}
