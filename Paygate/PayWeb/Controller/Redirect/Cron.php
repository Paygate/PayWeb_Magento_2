<?php
/*
 * Copyright (c) 2021 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

namespace PayGate\PayWeb\Controller\Redirect;

use Magento\Framework\View\Result\PageFactory;
use PayGate\PayWeb\Controller\AbstractPaygate;

/**
 * Responsible for loading page content.
 *
 * This is a basic controller that only loads the corresponding layout file. It may duplicate other such
 * controllers, and thus it is considered tech debt. This code duplication will be resolved in future releases.
 */
class Cron extends AbstractPaygate
{
    /**
     * @var PageFactory
     */
    protected $resultPageFactory;

    /**
     * Execute
     */
    public function execute()
    {
        // Do nothing
    }

}
