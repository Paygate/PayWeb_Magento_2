<?php
/*
 * Copyright (c) 2021 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

namespace PayGate\PayWeb\Controller\Redirect;

use Magento\Framework\App\Action\Action as AppAction;
use Magento\Framework\App\Action\Context;
use Magento\Framework\UrlInterface;
use PayGate\PayWeb\Model\PayGate;


/**
 * Responsible for loading page content.
 *
 * This is a basic controller that only loads the corresponding layout file. It may duplicate other such
 * controllers, and thus it is considered tech debt. This code duplication will be resolved in future releases.
 */
class Api extends AppAction
{

    /**
     * @var PayGate $_paymentMethod
     */
    protected $_paymentMethod;
    /**
     * @var Context
     */
    private $context;
    private $response;
    private $redirect;
    /**
     * @var UrlInterface
     */
    private $url;

    public function __construct(
        Context $context,
        PayGate $paymentMethod,
        UrlInterface $url
    ) {
        $this->_paymentMethod = $paymentMethod;

        $this->context  = $context;
        $this->response = $context->getResponse();
        $this->redirect = $context->getRedirect();

        $this->url = $url;

        parent::__construct($context);
    }

    /**
     * Execute
     */
    public function execute()
    {
        if ($this->_paymentMethod->isTestMode()) {
            echo '
				<form class="checkout" name="paygate_checkout" id="paygate_checkout" action="https://secure.paygate.co.za/payweb3/process.trans" method="post">
					PAY_REQUEST_ID: <input type="text" name="PAY_REQUEST_ID" value="" size="200">
					CHECKSUM: <input type="text" name="CHECKSUM" value="" size="200">
					<input type="submit" name="Submit" size="200">
				</form>';
        } else {
            $norouteUrl = $this->url->getUrl('noroute');
            $this->getResponse()->setRedirect($norouteUrl);

            return;
        }
    }

}
