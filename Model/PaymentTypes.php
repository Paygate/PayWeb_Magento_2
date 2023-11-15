<?php
/**
 * @noinspection PhpUndefinedNamespaceInspection
 */

/**
 * @noinspection PhpUnused
 */

/**
 * Copyright (c) 2023 Payfast (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

namespace PayGate\PayWeb\Model;

use Magento\Framework\Option\ArrayInterface;

class PaymentTypes implements ArrayInterface
{
    /**
     * @var \PayGate\PayWeb\Model\PayGate
     */
    private PayGate $paymentMethod;

    /**
     * @param \PayGate\PayWeb\Model\PayGate $paymentMethod
     */
    public function __construct(PayGate $paymentMethod)
    {
        $this->paymentMethod = $paymentMethod;
    }

    /**
     * Payment type list with descriptions
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            [
                'value' => 'CC',
                'label' => $this->paymentMethod->getPaymentTypeDescription('CC'),
            ],
            [
                'value' => 'BT',
                'label' => $this->paymentMethod->getPaymentTypeDescription('BT'),
            ],
            [
                'value' => 'EW-ZAPPER',
                'label' => $this->paymentMethod->getPaymentTypeDescription('EW-ZAPPER'),
            ],
            [
                'value' => 'EW-SNAPSCAN',
                'label' => $this->paymentMethod->getPaymentTypeDescription('EW-SNAPSCAN'),
            ],
            [
                'value' => 'EW-MOBICRED',
                'label' => $this->paymentMethod->getPaymentTypeDescription('EW-MOBICRED'),
            ],
            [
                'value' => 'EW-MOMOPAY',
                'label' => $this->paymentMethod->getPaymentTypeDescription('EW-MOMOPAY'),
            ],
            [
                'value' => 'EW-SCANTOPAY',
                'label' => $this->paymentMethod->getPaymentTypeDescription('EW-SCANTOPAY'),
            ],
            [
                'value' => 'EW-PAYPAL',
                'label' => $this->paymentMethod->getPaymentTypeDescription('EW-PAYPAL'),
            ],
            [
                'value' => 'EW-SAMSUNGPAY',
                'label' => $this->paymentMethod->getPaymentTypeDescription('EW-SAMSUNGPAY'),
            ],
            [
                'value' => 'CC-APPLEPAY',
                'label' => $this->paymentMethod->getPaymentTypeDescription('CC-APPLEPAY'),
            ],
            [
                'value' => 'EW-RCS',
                'label' => $this->paymentMethod->getPaymentTypeDescription('EW-RCS'),
            ]
        ];
    }
}
