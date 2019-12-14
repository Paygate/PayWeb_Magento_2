<?php
/*
 * Copyright (c) 2019 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 *
 * Magento v2.3.0+ implement CsrfAwareActionInterface but not earlier versions
 */

namespace Paygate\Paygate\Controller\Notify;

/**
 * Check for existence of CsrfAwareActionInterface - only v2.3.0+
 */
if ( interface_exists( "Magento\Framework\App\CsrfAwareActionInterface" ) ) {
    class_alias( 'Paygate\Paygate\Controller\Notify\Indexm230', 'Paygate\Paygate\Controller\Notify\Index' );
} else {
    class_alias( 'Paygate\Paygate\Controller\Notify\Indexm220', 'Paygate\Paygate\Controller\Notify\Index' );
}
