<?php
/** @noinspection PhpUndefinedNamespaceInspection */

/** @noinspection PhpUnused */

/*
 * Copyright (c) 2022 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

namespace PayGate\PayWeb\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Payment\Model\Method\ConfigInterface;
use Magento\Payment\Model\MethodInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Class AbstractConfig
 */
abstract class AbstractConfig implements ConfigInterface
{
    const PAYMENT_ACTION_SALE = 'Sale';

    const PAYMENT_ACTION_AUTH = 'Authorization';

    const PAYMENT_ACTION_ORDER = 'Order';
    /**#@-*/
    /**
     * Core store config
     *
     * @var ScopeConfigInterface
     */
    public ScopeConfigInterface $_scopeConfig;
    /**
     * Current payment method code
     *
     * @var string
     */
    protected string $_methodCode;
    /**
     * Current store id
     *
     * @var int
     */
    protected int $_storeId;
    /**
     * @var string
     */
    protected string $pathPattern;
    /**
     * @var MethodInterface
     */
    protected MethodInterface $methodInstance;

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig
    ) {
        $this->_scopeConfig = $scopeConfig;
    }

    /**
     * Sets method instance used for retrieving method specific data
     *
     * @param MethodInterface $method
     *
     * @return $this
     */
    public function setMethodInstance(MethodInterface $method): static
    {
        $this->methodInstance = $method;

        return $this;
    }

    /**
     * Method code setter
     *
     * @param string|MethodInterface $method
     *
     * @return $this
     */
    public function setMethod(MethodInterface|string $method): static
    {
        if ($method instanceof MethodInterface) {
            $this->_methodCode = $method->getCode();
        } else {
            $this->_methodCode = $method;
        }

        return $this;
    }

    /**
     * Payment method instance code getter
     *
     * @return string
     */
    public function getMethodCode(): string
    {
        return $this->_methodCode;
    }

    /**
     * Store ID setter
     *
     * @param int $storeId
     *
     * @return $this
     */
    public function setStoreId(int $storeId): static
    {
        $this->_storeId = $storeId;

        return $this;
    }

    /**
     * Returns payment configuration value
     *
     * @param string $key
     * @param null $storeId
     *
     * @return null|string
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @noinspection PhpUnusedParameterInspection
     * @noinspection PhpMissingParamTypeInspection
     */
    public function getValue($key, $storeId = null): ?string
    {
        $underscored = strtolower(preg_replace('/(.)([A-Z])/', "$1_$2", $key));
        $path        = $this->_getSpecificConfigPath($underscored);

        if ($path !== null) {
            $value = $this->_scopeConfig->getValue(
                $path,
                ScopeInterface::SCOPE_STORE,
                $this->_storeId
            );

            return $this->_prepareValue($underscored, $value);
        }

        return null;
    }

    /**
     * Sets method code
     *
     * @param string $methodCode
     *
     * @return void
     * @noinspection PhpMissingParamTypeInspection
     */
    public function setMethodCode($methodCode)
    {
        $this->_methodCode = $methodCode;
    }

    /**
     * Sets path pattern
     *
     * @param string $pathPattern
     *
     * @return void
     * @noinspection PhpMissingParamTypeInspection
     */
    public function setPathPattern($pathPattern)
    {
        $this->pathPattern = $pathPattern;
    }

    /**
     * Check whether method available for checkout or not
     *
     * @param null $methodCode
     *
     * @return bool
     */
    public function isMethodAvailable($methodCode = null): bool
    {
        $methodCode = $methodCode ?: $this->_methodCode;

        return $this->isMethodActive($methodCode);
    }

    /**
     * Check whether method active in configuration and supported for merchant country or not
     *
     * @param string $method Method code
     *
     * @return bool
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function isMethodActive(string $method): bool
    {
        $isEnabled = $this->_scopeConfig->isSetFlag(
            "payment/$method/active",
            ScopeInterface::SCOPE_STORE,
            $this->_storeId
        );

        return $this->isMethodSupportedForCountry($method) && $isEnabled;
    }

    /**
     * Check whether method supported for specified country or not
     *
     * @param string|null $method
     * @param string|null $countryCode
     *
     * @return bool
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function isMethodSupportedForCountry(string $method = null, string $countryCode = null): bool
    {
        return true;
    }

    /**
     * Map any supported payment method into a config path by specified field name
     *
     * @param string $fieldName
     *
     * @return string|null
     */
    protected function _getSpecificConfigPath(string $fieldName): ?string
    {
        if ($this->pathPattern) {
            return sprintf($this->pathPattern, $this->_methodCode, $fieldName);
        }

        return "payment/$this->_methodCode/$fieldName";
    }

    /**
     * Perform additional config value preparation and return new value if needed
     *
     * @param string $key Underscored key
     * @param string $value Old value
     *
     * @return string Modified value or old value
     * @noinspection PhpUnusedParameterInspection
     */
    protected function _prepareValue(string $key, string $value): string
    {
        return $value;
    }
}
