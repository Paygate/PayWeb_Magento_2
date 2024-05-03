<?php
/**
 * @noinspection PhpUndefinedNamespaceInspection
 */

/**
 * @noinspection PhpUnused
 */

/**
 * Copyright (c) 2024 Payfast (Pty) Ltd
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
                'value' => 'EW-Zapper',
                'label' => $this->paymentMethod->getPaymentTypeDescription('EW-Zapper'),
            ],
            [
                'value' => 'EW-SnapScan',
                'label' => $this->paymentMethod->getPaymentTypeDescription('EW-SnapScan'),
            ],
            [
                'value' => 'EW-Mobicred',
                'label' => $this->paymentMethod->getPaymentTypeDescription('EW-Mobicred'),
            ],
            [
                'value' => 'EW-Momopay',
                'label' => $this->paymentMethod->getPaymentTypeDescription('EW-Momopay'),
            ],
            [
                'value' => 'EW-MasterPass',
                'label' => $this->paymentMethod->getPaymentTypeDescription('EW-MasterPass'),
            ],
            [
                'value' => 'EW-PayPal',
                'label' => $this->paymentMethod->getPaymentTypeDescription('EW-PayPal'),
            ],
            [
                'value' => 'EW-Samsungpay',
                'label' => $this->paymentMethod->getPaymentTypeDescription('EW-Samsungpay'),
            ],
            [
                'value' => 'CC-Applepay',
                'label' => $this->paymentMethod->getPaymentTypeDescription('CC-Applepay'),
            ],
            [
                'value' => 'CC-RCS',
                'label' => $this->paymentMethod->getPaymentTypeDescription('CC-RCS'),
            ]
        ];
    }
}
