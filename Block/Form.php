<?php
/**
 * @noinspection PhpMissingFieldTypeInspection
 */

/**
 * @noinspection PhpUndefinedNamespaceInspection
 */

/**
 * Copyright (c) 2024 Payfast (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

namespace PayGate\PayWeb\Block;

use Magento\Customer\Helper\Session\CurrentCustomer;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use PayGate\PayWeb\Helper\Data;
use PayGate\PayWeb\Model\Config;
use PayGate\PayWeb\Model\ConfigFactory;
use PayGate\PayWeb\Model\Paygate;

class Form extends \Magento\Payment\Block\Form
{
    /**
     * @var string|Paygate Payment method code
     */
    protected Paygate|string $_methodCode = Config::METHOD_CODE;

    /**
     * @var Data
     */
    protected Data $_paygateData;

    /**
     * @var ConfigFactory
     */
    protected ConfigFactory $paygateConfigFactory;

    /**
     * @var ResolverInterface
     */
    protected ResolverInterface $_localeResolver;

    /**
     * @var Config|null
     */
    protected ?Config $_config;

    /**
     * test
     *
     * @var bool
     */
    protected $_isScopePrivate;

    /**
     * @var CurrentCustomer
     */
    protected CurrentCustomer $currentCustomer;

    /**
     * @var LoggerInterface
     */
    protected $_logger;

    /**
     * @param Context           $context
     * @param ConfigFactory     $paygateConfigFactory
     * @param ResolverInterface $localeResolver
     * @param Data              $paygateData
     * @param CurrentCustomer   $currentCustomer
     * @param array             $data
     */
    public function __construct(
        Context $context,
        ConfigFactory $paygateConfigFactory,
        ResolverInterface $localeResolver,
        Data $paygateData,
        CurrentCustomer $currentCustomer,
        array $data = []
    ) {
        $this->_logger = $context->getLogger();
        $pre           = __METHOD__ . " : ";
        $this->_logger->debug($pre . 'bof');
        $this->_paygateData         = $paygateData;
        $this->paygateConfigFactory = $paygateConfigFactory;
        $this->_localeResolver      = $localeResolver;
        $this->_config              = null;
        $this->_isScopePrivate      = true;
        $this->currentCustomer      = $currentCustomer;
        parent::__construct($context, $data);
        $this->_logger->debug($pre . "eof");
    }

    /**
     * Payment method code getter
     *
     * @return string|Paygate
     */
    public function getMethodCode(): string|Paygate
    {
        $pre = __METHOD__ . " : ";
        $this->_logger->debug($pre . 'bof');

        return $this->_methodCode;
    }

    /**
     * Set template and redirect message
     *
     * @return       null
     * @noinspection PhpUnused
     */
    protected function _construct()
    {
        $pre = __METHOD__ . " : ";
        $this->_logger->debug($pre . 'bof');
        $this->_config = $this->paygateConfigFactory->create()->setMethod($this->getMethodCode());
        parent::_construct();

        return null;
    }
}
