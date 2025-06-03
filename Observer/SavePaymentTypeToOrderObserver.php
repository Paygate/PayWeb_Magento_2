<?php

/**
 * @noinspection PhpUnused
 */

/*
 * Copyright (c) 2025 Payfast (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

namespace PayGate\PayWeb\Observer;

use Magento\Framework\Event\Observer;
use Magento\Payment\Observer\AbstractDataAssignObserver;
use Magento\Quote\Api\Data\PaymentInterface;

class SavePaymentTypeToOrderObserver extends AbstractDataAssignObserver
{

    public const PAYGATE_PAYMENT_TYPE = 'paygate-payment-type';

    /**
     * Assign Data Observer Execution for payment type
     *
     * @param Observer $observer
     *
     * @return void
     */
    public function execute(Observer $observer)
    {
        $data = $this->readDataArgument($observer);

        $additionalData = $data->getData(PaymentInterface::KEY_ADDITIONAL_DATA);
        if (!is_array($additionalData) || !isset($additionalData[self::PAYGATE_PAYMENT_TYPE])) {
            return;
        }

        $paymentInfo = $this->readPaymentModelArgument($observer);

        $paymentInfo->setAdditionalInformation(
            self::PAYGATE_PAYMENT_TYPE,
            $additionalData[self::PAYGATE_PAYMENT_TYPE]
        );
    }
}
