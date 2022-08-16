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

namespace PayGate\PayWeb\Observer;

use Magento\Framework\Event\Observer;
use Magento\Payment\Observer\AbstractDataAssignObserver;
use Magento\Quote\Api\Data\PaymentInterface;

class SavePayvaultInfoToOrderObserver extends AbstractDataAssignObserver
{

    const PAYVAULT_NAME_INDEX = 'paygate-payvault-method';

    /**
     * @param Observer $observer
     *
     * @return void
     * @noinspection PhpUndefinedMethodInspection
     */
    public function execute(Observer $observer)
    {
        $data = $this->readDataArgument($observer);

        $additionalData = $data->getData(PaymentInterface::KEY_ADDITIONAL_DATA);
        if ( ! is_array($additionalData) || ! isset($additionalData[self::PAYVAULT_NAME_INDEX])) {
            return;
        }

        $paymentInfo = $this->readPaymentModelArgument($observer);

        $paymentInfo->setAdditionalInformation(
            self::PAYVAULT_NAME_INDEX,
            $additionalData[self::PAYVAULT_NAME_INDEX]
        );
    }
}
