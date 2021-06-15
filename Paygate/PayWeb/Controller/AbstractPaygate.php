<?php
/*
 * Copyright (c) 2021 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

namespace PayGate\PayWeb\Controller;

if (interface_exists("Magento\Framework\App\CsrfAwareActionInterface")) {
    class_alias('PayGate\PayWeb\Controller\AbstractPaygatem230', 'PayGate\PayWeb\Controller\AbstractPaygate');
} else {
    class_alias('PayGate\PayWeb\Controller\AbstractPaygatem220', 'PayGate\PayWeb\Controller\AbstractPaygate');
}
