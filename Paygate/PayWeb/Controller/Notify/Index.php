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

/**
 * Check for existence of CsrfAwareActionInterface - only v2.3.0+
 */
if (interface_exists("Magento\Framework\App\CsrfAwareActionInterface")) {
    class_alias('PayGate\PayWeb\Controller\Notify\Indexm230', 'PayGate\PayWeb\Controller\Notify\Index');
} else {
    class_alias('PayGate\PayWeb\Controller\Notify\Indexm220', 'PayGate\PayWeb\Controller\Notify\Index');
}
