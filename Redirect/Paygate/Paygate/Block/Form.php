<?php
/*
 * Copyright (c) 2019 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 * 
 * Released under the GNU General Public License
 */
namespace Paygate\Paygate\Block;

use Magento\Customer\Helper\Session\CurrentCustomer;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Paygate\Paygate\Model\Config;
use Paygate\Paygate\Model\Paygate\Checkout;

class Form extends \Magento\Payment\Block\Form
{
    /**
     * @var string Payment method code
     */
    protected $_methodCode = Config::METHOD_CODE;

    /**
     * @var \Paygate\Paygate\Helper\Data
     */
    protected $_paygateData;

    /**
     * @var \Paygate\Paygate\Model\ConfigFactory
     */
    protected $paygateConfigFactory;

    /**
     * @var ResolverInterface
     */
    protected $_localeResolver;

    /**
     * @var \Paygate\Paygate\Model\Config
     */
    protected $_config;

    /**
     * @var bool
     */
    protected $_isScopePrivate;

    /**
     * @var CurrentCustomer
     */
    protected $currentCustomer;

    /**
     * @param Context $context
     * @param \Paygate\Paygate\Model\ConfigFactory $paygateConfigFactory
     * @param ResolverInterface $localeResolver
     * @param \Paygate\Paygate\Helper\Data $paygateData
     * @param CurrentCustomer $currentCustomer
     * @param array $data
     */
    public function __construct(
        Context $context,
        \Paygate\Paygate\Model\ConfigFactory $paygateConfigFactory,
        ResolverInterface $localeResolver,
        \Paygate\Paygate\Helper\Data $paygateData,
        CurrentCustomer $currentCustomer,
        array $data = []
    ) {
        parent::__construct($context, $data);

        $pre = __METHOD__ . " : ";
        $this->_logger->debug( $pre . 'bof' );
        $this->_paygateData         = $paygateData;
        $this->paygateConfigFactory = $paygateConfigFactory;
        $this->_localeResolver      = $localeResolver;
        $this->_config              = null;
        $this->_isScopePrivate      = true;
        $this->currentCustomer      = $currentCustomer;
        $this->_logger->debug( $pre . "eof" );

        $this->_config = $this->paygateConfigFactory->create()->setMethod( $this->getMethodCode() );
    }

    /**
     * Payment method code getter
     *
     * @return string
     */
    public function getMethodCode()
    {
        $pre = __METHOD__ . " : ";
        $this->_logger->debug( $pre . 'bof' );

        return $this->_methodCode;
    }

}
