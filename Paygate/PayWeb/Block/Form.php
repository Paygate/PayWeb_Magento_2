<?php
/*
 * Copyright (c) 2021 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */
namespace PayGate\PayWeb\Block\PayGate;

use Magento\Customer\Helper\Session\CurrentCustomer;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use PayGate\PayWeb\Model\Config;

class Form extends \Magento\Payment\Block\Form
{
    /**
     * @var string Payment method code
     */
    protected $_methodCode = Config::METHOD_CODE;

    /**
     * @var \PayGate\PayWeb\Helper\Data
     */
    protected $_paygateData;

    /**
     * @var \PayGate\PayWeb\Model\ConfigFactory
     */
    protected $paygateConfigFactory;

    /**
     * @var ResolverInterface
     */
    protected $_localeResolver;

    /**
     * @var \PayGate\PayWeb\Model\Config
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
     * @param \PayGate\PayWeb\Model\ConfigFactory $paygateConfigFactory
     * @param ResolverInterface $localeResolver
     * @param \PayGate\PayWeb\Helper\Data $paygateData
     * @param CurrentCustomer $currentCustomer
     * @param array $data
     */
    public function __construct(
        Context $context,
        \PayGate\PayWeb\Model\ConfigFactory $paygateConfigFactory,
        ResolverInterface $localeResolver,
        \PayGate\PayWeb\Helper\Data $paygateData,
        CurrentCustomer $currentCustomer,
        array $data = []
    ) {
        $pre = __METHOD__ . " : ";
        $this->_logger->debug( $pre . 'bof' );
        $this->_paygateData         = $paygateData;
        $this->paygateConfigFactory = $paygateConfigFactory;
        $this->_localeResolver      = $localeResolver;
        $this->_config              = null;
        $this->_isScopePrivate      = true;
        $this->currentCustomer      = $currentCustomer;
        parent::__construct( $context, $data );
        $this->_logger->debug( $pre . "eof" );
    }

    /**
     * Set template and redirect message
     *
     * @return null
     */
    protected function _construct()
    {
        $pre = __METHOD__ . " : ";
        $this->_logger->debug( $pre . 'bof' );
        $this->_config = $this->paygateConfigFactory->create()->setMethod( $this->getMethodCode() );
        parent::_construct();
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
