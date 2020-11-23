<?php
/*
 * Copyright (c) 2020 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

if ( interface_exists( "Magento\Framework\App\CsrfAwareActionInterface" ) ) {
    class_alias( 'Paygate\Paygate\Controller\AbstractPaygatem230', 'Paygate\Paygate\Controller\AbstractPaygate' );
} else {
    class_alias( 'Paygate\Paygate\Controller\AbstractPaygatem220', 'Paygate\Paygate\Controller\AbstractPaygate' );
}
