<?php

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
    protected PayGate $_paymentMethod;

    /**
     * @var OrderFactory
     */
    protected OrderFactory $_orderFactory;

    /**
     * @var Session
     */
    protected Session $_checkoutSession;

    /**
     * @var ReadFactory $readFactory
     */
    protected ReadFactory $readFactory;

    /**
     * @var Reader $reader
     */
    protected Reader $reader;

    /**
     * @var true
     */
    protected $_isScopePrivate;

    /**
     * @param Context $context
     * @param OrderFactory $orderFactory
     * @param Session $checkoutSession
     * @param ReadFactory $readFactory
     * @param Reader $reader
     * @param PayGate $paymentMethod
     * @param array $data
     *
     * @noinspection PhpUnused
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

    /**
     * Builds submit form
     *
     * @noinspection PhpUnused
     * @noinspection PhpUndefinedMethodInspection
     */
    public function _prepareLayout()
    {
        $this->setMessage('Redirecting to Paygate')
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
