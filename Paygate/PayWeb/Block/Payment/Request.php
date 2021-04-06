<?php
/*
 * Copyright (c) 2021 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

namespace PayGate\PayWeb\Block\Payment;

class Request extends \Magento\Framework\View\Element\Template
{

    /**
     * @var \PayGate\PayWeb\Model\PayGate $_paymentMethod
     */
    protected $_paymentMethod;

    /**
     * @var \Magento\Sales\Model\OrderFactory
     */
    protected $_orderFactory;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $_checkoutSession;

    /**
     * @var \Magento\Framework\Filesystem\Directory\ReadFactory  $readFactory
     */
    protected $readFactory;

    /**
     * @var \Magento\Framework\Module\Dir\Reader $reader
     */
    protected $reader;

    /**
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \Magento\Sales\Model\OrderFactory $orderFactory
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\Framework\Filesystem\Directory\ReadFactory $readFactory
     * @param \Magento\Framework\Module\Dir\Reader $reader
     * @param \PayGate\PayWeb\Model\PayGate $paymentMethod
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Framework\Filesystem\Directory\ReadFactory $readFactory,
        \Magento\Framework\Module\Dir\Reader $reader,
        \PayGate\PayWeb\Model\PayGate $paymentMethod,
        array $data = []
    ) {
        $this->_orderFactory    = $orderFactory;
        $this->_checkoutSession = $checkoutSession;
        parent::__construct( $context, $data );
        $this->_isScopePrivate = true;
        $this->readFactory     = $readFactory;
        $this->reader          = $reader;
        $this->_paymentMethod  = $paymentMethod;
    }

    public function _prepareLayout()
    {
        $this->setMessage( 'Redirecting to PayGate' )
            ->setId( 'paygate_checkout' )
            ->setName( 'paygate_checkout' )
            ->setFormMethod( 'POST' )
            ->setFormAction( 'https://secure.paygate.co.za/payweb3/process.trans' )
            ->setFormData( $this->_paymentMethod->getStandardCheckoutFormFields() )
            ->setSubmitForm( '<script type="text/javascript">document.getElementById( "paygate_checkout" ).submit();</script>' );

        return parent::_prepareLayout();
    }

}
