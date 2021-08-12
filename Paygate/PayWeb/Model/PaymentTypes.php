<?php
/**
 * Copyright (c) 2021 PayGate (Pty) Ltd
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
     * @return array
     */
    public function toOptionArray()
    {
        return array(
            [
                'value' => 'CC',
                'label' => "Card",
            ],
            [
                'value' => 'BT',
                'label' => "SiD Secure EFT",
            ],
            [
                'value' => 'EW-ZAPPER',
                'label' => "Zapper",
            ],
            [
                'value' => 'EW-SNAPSCAN',
                'label' => "SnapScan",
            ],
            [
                'value' => 'EW-MOBICRED',
                'label' => "Mobicred",
            ],
            [
                'value' => 'EW-MOMOPAY',
                'label' => "MoMoPay",
            ],
            [
                'value' => 'EW-MASTERPASS',
                'label' => "MasterPass",
            ],
            [
                'value' => 'EW-PAYPAL',
                'label' => "PayPal",
            ]
        );
    }
}
