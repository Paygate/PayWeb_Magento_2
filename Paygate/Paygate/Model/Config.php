<?php
/*
 * Copyright (c) 2020 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 * 
 * Released under the GNU General Public License
 */

// @codingStandardsIgnoreFile

namespace Paygate\Paygate\Model;

/**
 * Config model that is aware of all \Paygate\Paygate payment methods
 * Works with PayGate-specific system configuration
 * @SuppressWarnings(PHPMD.ExcesivePublicCount)
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
class Config extends AbstractConfig
{

    /**
     * @var \Paygate\Paygate\Model\Paygate this is a model which we will use.
     */
    const METHOD_CODE = 'paygate';

    /**
     * Core
     * data @var \Magento\Directory\Helper\Data
     */
    protected $directoryHelper;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $_storeManager;

    protected $_supportedBuyerCountryCodes = ['ZA'];

    /**
     * Currency codes supported by PayGate methods
     * @var string[]
     */
    protected $_supportedCurrencyCodes = ['USD', 'EUR', 'GPD', 'ZAR'];

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $_logger;

    /**
     * @var \Magento\Framework\UrlInterface
     */
    protected $_urlBuilder;

    /**
     * @var \Magento\Framework\View\Asset\Repository
     */
    protected $_assetRepo;

    /**
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Directory\Helper\Data $directoryHelper
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param array $params
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Framework\View\Asset\Repository
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Directory\Helper\Data $directoryHelper,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\View\Asset\Repository $assetRepo
    ) {
        $this->_logger = $logger;
        parent::__construct( $scopeConfig );
        $this->directoryHelper = $directoryHelper;
        $this->_storeManager   = $storeManager;
        $this->_assetRepo      = $assetRepo;

            $this->setMethod('paygate');
			$currentStoreId = $this->_storeManager->getStore()->getStoreId();
            $this->setStoreId($currentStoreId);
    }

    /**
     * Check whether method available for checkout or not
     * Logic based on merchant country, methods dependence
     *
     * @param string|null $methodCode
     * @return bool
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function isMethodAvailable( $methodCode = null )
    {
        return parent::isMethodAvailable( $methodCode );
    }

    /**
     * Return buyer country codes supported by PayGate
     *
     * @return string[]
     */
    public function getSupportedBuyerCountryCodes()
    {
        return $this->_supportedBuyerCountryCodes;
    }

    /**
     * Return merchant country code, use default country if it not specified in General settings
     *
     * @return string
     */
    public function getMerchantCountry()
    {
        return $this->directoryHelper->getDefaultCountry( $this->_storeId );
    }

    /**
     * Check whether method supported for specified country or not
     * Use $_methodCode and merchant country by default
     *
     * @param string|null $method
     * @param string|null $countryCode
     * @return bool
     */
    public function isMethodSupportedForCountry( $method = null, $countryCode = null )
    {
        if ( $method === null ) {
            $method = $this->getMethodCode();
        }

        if ( $countryCode === null ) {
            $countryCode = $this->getMerchantCountry();
        }

        return in_array( $method, $this->getCountryMethods( $countryCode ) );
    }

    /**
     * Return list of allowed methods for specified country iso code
     *
     * @param string|null $countryCode 2-letters iso code
     * @return array
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function getCountryMethods( $countryCode = null )
    {
        $countryMethods = [
            'other' => [
                self::METHOD_CODE,
            ],

        ];
        if ( $countryCode === null ) {
            return $countryMethods;
        }
        return isset( $countryMethods[$countryCode] ) ? $countryMethods[$countryCode] : $countryMethods['other'];
    }

    /**
     * Get PayGate "mark" image URL
     * TODO - Maybe this can be placed in the config xml
     *
     * @return string
     */
    public function getPaymentMarkImageUrl()
    {
        return $this->_assetRepo->getUrl( 'Paygate_Paygate::images/logo.png' );
    }

    /**
     * Get "What Is PayGate" localized URL
     * Supposed to be used with "mark" as popup window
     *
     * @return string
     */
    public function getPaymentMarkWhatIsPaygate()
    {
        return 'PayGate Payment gateway';
    }

    /**
     * Mapper from PayGate-specific payment actions to Magento payment actions
     *
     * @return string|null
     */
    public function getPaymentAction()
    {
        $paymentAction = null;
        $pre           = __METHOD__ . ' : ';
        $this->_logger->debug( $pre . 'bof' );

        $action = $this->getValue( 'paymentAction' );

        switch ( $action ) {
            case self::PAYMENT_ACTION_AUTH:
                $paymentAction = \Magento\Payment\Model\Method\AbstractMethod::ACTION_AUTHORIZE;
                break;
            case self::PAYMENT_ACTION_SALE:
                $paymentAction = \Magento\Payment\Model\Method\AbstractMethod::ACTION_AUTHORIZE_CAPTURE;
                break;
            case self::PAYMENT_ACTION_ORDER:
                $paymentAction = \Magento\Payment\Model\Method\AbstractMethod::ACTION_ORDER;
                break;
        }

        $this->_logger->debug( $pre . 'eof : paymentAction is ' . $paymentAction );

        return $paymentAction;
    }

    /**
     * Check whether specified currency code is supported
     *
     * @param string $code
     * @return bool
     */
    public function isCurrencyCodeSupported( $code )
    {
        $supported = false;
        $pre       = __METHOD__ . ' : ';

        $this->_logger->debug( $pre . "bof and code: {$code}" );

        if ( in_array( $code, $this->_supportedCurrencyCodes ) ) {
            $supported = true;
        }

        $this->_logger->debug( $pre . "eof and supported : {$supported}" );

        return $supported;
    }

    /**
     * Check whether specified locale code is supported. Fallback to en_US
     *
     * @param string|null $localeCode
     * @return string
     */
    protected function _getSupportedLocaleCode( $localeCode = null )
    {
        if ( !$localeCode || !in_array( $localeCode, $this->_supportedImageLocales ) ) {
            return 'en_US';
        }
        return $localeCode;
    }

    /**
     * _mapPayGateFieldset
     * Map PayGate config fields
     *
     * @param string $fieldName
     * @return string|null
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    protected function _mapPayGateFieldset( $fieldName )
    {
        return "payment/{$this->_methodCode}/{$fieldName}";
    }

    /**
     * Map any supported payment method into a config path by specified field name
     *
     * @param string $fieldName
     * @return string|null
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    protected function _getSpecificConfigPath( $fieldName )
    {
        return $this->_mapPayGateFieldset( $fieldName );
    }
}