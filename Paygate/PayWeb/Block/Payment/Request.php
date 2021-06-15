<?php
/*
 * Copyright (c) 2021 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

namespace PayGate\PayWeb\Block\Payment;

use Magento\Checkout\Model\Session;
use Magento\Framework\Filesystem\Directory\ReadFactory;
use Magento\Framework\Module\Dir\Reader;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Sales\Model\OrderFactory;
use PayGate\PayWeb\Model\PayGate;

class Request extends Template
{

    /**
     * @var PayGate $_paymentMethod
     */
    protected $_paymentMethod;

    /**
     * @var OrderFactory
     */
    protected $_orderFactory;

    /**
     * @var Session
     */
    protected $_checkoutSession;

    /**
     * @var ReadFactory $readFactory
     */
    protected $readFactory;

    /**
     * @var Reader $reader
     */
    protected $reader;

    /**
     * @param Context $context
     * @param OrderFactory $orderFactory
     * @param Session $checkoutSession
     * @param ReadFactory $readFactory
     * @param Reader $reader
     * @param PayGate $paymentMethod
     * @param array $data
     */
    public function __construct(
        Context $context,
        OrderFactory $orderFactory,
        Session $checkoutSession,
        ReadFactory $readFactory,
        Reader $reader,
        PayGate $paymentMethod,
        array $data = []
    ) {
        $this->_orderFactory    = $orderFactory;
        $this->_checkoutSession = $checkoutSession;
        parent::__construct($context, $data);
        $this->_isScopePrivate = true;
        $this->readFactory     = $readFactory;
        $this->reader          = $reader;
        $this->_paymentMethod  = $paymentMethod;
    }

    public function _prepareLayout()
    {
        $this->setMessage('Redirecting to PayGate')
             ->setId('paygate_checkout')
             ->setName('paygate_checkout')
             ->setFormMethod('POST')
             ->setFormAction('https://secure.paygate.co.za/payweb3/process.trans')
             ->setFormData($this->_paymentMethod->getStandardCheckoutFormFields())
             ->setSubmitForm(
                 '<script type="text/javascript">document.getElementById( "paygate_checkout" ).submit();</script>'
             );

        return parent::_prepareLayout();
    }

}
