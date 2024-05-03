<?php

/**
 * @noinspection PhpMissingParamTypeInspection
 */

/**
 * @noinspection PhpMissingParamTypeInspection
 */

/**
 * @noinspection PhpMissingReturnTypeInspection
 */

/**
 * @noinspection PhpMissingFieldTypeInspection
 */

/**
 * @noinspection PhpUndefinedNamespaceInspection
 */

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

namespace PayGate\PayWeb\Model;

use DateInterval;
use DateTime;
use DateTimeZone;
use Exception;
use JetBrains\PhpStorm\Pure;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Area;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\State;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\LocalizedExceptionFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Payment\Model\MethodInterface;
use Magento\Payment\Observer\AbstractDataAssignObserver;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Api\Data\PaymentMethodInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Api\Data\TransactionInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\TransactionRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Api\PaymentTokenManagementInterface;
use Magento\Vault\Api\PaymentTokenRepositoryInterface;
use Magento\Vault\Model\CreditCardTokenFactory;
use Magento\Vault\Model\ResourceModel\PaymentToken as PaymentTokenResourceModel;
use PayGate\PayWeb\Helper\Data;
use Psr\Log\LoggerInterface;
use PayGate\PayWeb\Block\Payment\Info;
use PayGate\PayWeb\Block\Form;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Model\AbstractExtensibleModel;
use Magento\Framework\Event\ManagerInterface;
use Laminas\Uri\Uri;

/**
 * @SuppressWarnings(PHPMD.TooManyFields)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class PayGate extends AbstractExtensibleModel implements MethodInterface, PaymentMethodInterface
{
    public const CREDIT_CARD                 = 'pw3_credit_card';
    public const BANK_TRANSFER               = 'BT';
    public const ZAPPER                      = 'pw3_e_zapper';
    public const SNAPSCAN                    = 'pw3_e_snapscan';
    public const MOBICRED                    = 'pw3_e_mobicred';
    public const MOMOPAY                     = 'pw3_e_momopay';
    public const SCANTOPAY                   = 'pw3_e_scantopay';
    public const CREDIT_CARD_METHOD          = 'CC';
    public const BANK_TRANSFER_METHOD        = 'BT';
    public const ZAPPER_METHOD               = 'EW-Zapper';
    public const SNAPSCAN_METHOD             = 'EW-SnapScan';
    public const MOBICRED_METHOD             = 'EW-Mobicred';
    public const MOMOPAY_METHOD              = 'EW-Momopay';
    public const SCANTOPAY_METHOD            = 'EW-MasterPass';
    public const PAYPAL_METHOD               = 'EW-PayPal';
    public const SAMSUNG_METHOD              = 'EW-Samsungpay';
    public const APPLE_METHOD                = 'CC-Applepay';
    public const RCS_METHOD                  = 'CC-RCS';
    public const CREDIT_CARD_DESCRIPTION     = 'Card';
    public const BANK_TRANSFER_DESCRIPTION   = 'SiD Secure EFT';
    public const BANK_TRANSFER_METHOD_DETAIL = 'SID';
    public const ZAPPER_DESCRIPTION          = 'Zapper';
    public const SNAPSCAN_DESCRIPTION        = 'SnapScan';
    public const MOBICRED_DESCRIPTION        = 'Mobicred';
    public const MOMOPAY_DESCRIPTION         = 'MoMoPay';
    public const MOMOPAY_METHOD_DETAIL       = 'Momopay';
    public const SCANTOPAY_DESCRIPTION       = 'MasterPass';
    public const SCANTOPAY_DESCRIPTION_LABEL = 'ScanToPay';
    public const PAYPAL_DESCRIPTION          = 'PayPal';
    public const SAMSUNG_DESCRIPTION         = 'Samsung Pay';
    public const SAMSUNG_DESCRIPTION_DETAIL  = 'Samsungpay';
    public const APPLE_DESCRIPTION           = 'ApplePay';
    public const APPLE_DESCRIPTION_DETAIL    = 'Applepay';
    public const RCS_DESCRIPTION             = 'RCS';
    public const SECURE                      = '_secure';
    /**
     * @var string|PayGate
     */
    protected $_code = Config::METHOD_CODE;
    /**
     * @var string
     */
    protected $_formBlockType = Form::class;
    /**
     * @var string
     */
    protected $_infoBlockType = Info::class;
    /**
     * @var string
     */
    protected $_configType = Config::class;
    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_isInitializeNeeded = true;
    /**
     * Availability option
     *
     * @var bool
     */
    protected $_isGateway = false;
    /**
     * Availability option
     *
     * @var bool
     */
    protected $_canOrder = true;
    /**
     * Availability option
     *
     * @var bool
     */
    protected $_canAuthorize = true;
    /**
     * Availability option
     *
     * @var bool
     */
    protected $_canCapture = true;
    /**
     * Availability option
     *
     * @var bool
     */
    protected $_canVoid = false;
    /**
     * Availability option
     *
     * @var bool
     */
    protected $_canUseInternal = true;
    /**
     * Availability option
     *
     * @var bool
     */
    protected $_canUseCheckout = true;
    /**
     * Availability option
     *
     * @var bool
     */
    protected $_canFetchTransactionInfo = true;
    /**
     * Availability option
     *
     * @var bool
     */
    protected $_canReviewPayment = true;
    /**
     * Website Payments Pro instance
     *
     * @var Config $config
     */
    protected $_config;
    /**
     * Payment additional information key for payment action
     *
     * @var string
     */
    protected $_isOrderPaymentActionKey = 'is_order_action';
    /**
     * Payment additional information key for number of used authorizations
     *
     * @var string
     */
    protected $_authorizationCountKey = 'authorization_count';
    /**
     * @var StoreManagerInterface
     */
    protected $_storeManager;
    /**
     * @var UrlInterface
     */
    protected $_urlBuilder;
    /**
     * @var UrlInterface
     */
    protected $_formKey;
    /**
     * @var CheckoutSession
     */
    protected $_checkoutSession;
    /**
     * @var LocalizedExceptionFactory
     */
    protected $_exception;
    /**
     * @var TransactionRepositoryInterface
     */
    protected $transactionRepository;
    /**
     * @var BuilderInterface
     */
    protected $transactionBuilder;
    /**
     * @var CreditCardTokenFactory
     */
    protected $creditCardTokenFactory;
    /**
     * @var PaymentTokenRepositoryInterface
     */
    protected $paymentTokenRepository;
    /**
     * @var PaymentTokenManagementInterface
     */
    protected $paymentTokenManagement;
    /**
     * @var EncryptorInterface
     */
    protected $encryptor;
    /**
     * @var Payment
     */
    protected $payment;
    /**
     * @var Data
     */
    protected $_PaygateHelper;
    /**
     * @var OrderRepositoryInterface $orderRepository
     */
    protected $orderRepository;
    /**
     * @var PaymentTokenResourceModel
     */
    protected $paymentTokenResourceModel;
    /**
     * @var array|string[]
     */
    protected array $paymentTypes;
    /**
     * @var LoggerInterface
     */
    protected $_logger;
    /**
     * @var ScopeConfigInterface
     */
    protected ScopeConfigInterface $_scopeConfig;
    /**
     * @var PaymentTokenManagementInterface
     */
    protected PaymentTokenManagementInterface $paymentTokenManagementInterface;
    /**
     * @var CustomerSession
     */
    protected CustomerSession $customerSession;
    /**
     * @var Curl
     */
    private Curl $curl;

    /**
     * @param ConfigFactory $configFactory
     * @param StoreManagerInterface $storeManager
     * @param UrlInterface $urlBuilder
     * @param FormKey $formKey
     * @param CheckoutSession $checkoutSession
     * @param LocalizedExceptionFactory $exception
     * @param TransactionRepositoryInterface $transactionRepository
     * @param BuilderInterface $transactionBuilder
     * @param CreditCardTokenFactory $CreditCardTokenFactory
     * @param PaymentTokenRepositoryInterface $PaymentTokenRepositoryInterface
     * @param PaymentTokenManagementInterface $paymentTokenManagement
     * @param EncryptorInterface $encryptor
     * @param PaymentTokenResourceModel $paymentTokenResourceModel
     * @param CustomerSession $customerSession
     * @param PaymentTokenManagementInterface $paymentTokenManagementInterface
     * @param Data $PaygateHelper
     * @param OrderRepositoryInterface $orderRepository
     * @param Curl $curl
     * @param ScopeConfigInterface $_scopeConfig
     * @param ManagerInterface $_eventManager
     * @param LoggerInterface $_logger
     * @param Uri $uriHandler
     * @param array $data
     */
    public function __construct(
        ConfigFactory $configFactory,
        StoreManagerInterface $storeManager,
        UrlInterface $urlBuilder,
        FormKey $formKey,
        CheckoutSession $checkoutSession,
        LocalizedExceptionFactory $exception,
        TransactionRepositoryInterface $transactionRepository,
        BuilderInterface $transactionBuilder,
        CreditCardTokenFactory $CreditCardTokenFactory,
        PaymentTokenRepositoryInterface $PaymentTokenRepositoryInterface,
        PaymentTokenManagementInterface $paymentTokenManagement,
        EncryptorInterface $encryptor,
        PaymentTokenResourceModel $paymentTokenResourceModel,
        CustomerSession $customerSession,
        PaymentTokenManagementInterface $paymentTokenManagementInterface,
        Data $PaygateHelper,
        OrderRepositoryInterface $orderRepository,
        Curl $curl,
        ScopeConfigInterface $_scopeConfig,
        ManagerInterface $_eventManager,
        LoggerInterface $_logger,
        Uri $uriHandler,
        array $data = []
    ) {
        $this->_storeManager                   = $storeManager;
        $this->_urlBuilder                     = $urlBuilder;
        $this->_formKey                        = $formKey;
        $this->_checkoutSession                = $checkoutSession;
        $this->_exception                      = $exception;
        $this->transactionRepository           = $transactionRepository;
        $this->transactionBuilder              = $transactionBuilder;
        $this->creditCardTokenFactory          = $CreditCardTokenFactory;
        $this->paymentTokenRepository          = $PaymentTokenRepositoryInterface;
        $this->paymentTokenManagement          = $paymentTokenManagement;
        $this->customerSession                 = $customerSession;
        $this->paymentTokenManagementInterface = $paymentTokenManagementInterface;
        $this->encryptor                       = $encryptor;
        $this->paymentTokenResourceModel       = $paymentTokenResourceModel;
        $this->_PaygateHelper                  = $PaygateHelper;
        $this->orderRepository                 = $orderRepository;
        $this->_scopeConfig                    = $_scopeConfig;
        $this->_eventManager                   = $_eventManager;
        $this->_logger                         = $_logger;
        $this->uriHandler                      = $uriHandler;

        $parameters = ['params' => [$this->_code]];

        $this->paymentTypes = [
            self::CREDIT_CARD_METHOD   => static::CREDIT_CARD_DESCRIPTION,
            self::BANK_TRANSFER_METHOD => static::BANK_TRANSFER_METHOD_DETAIL,
            self::ZAPPER_METHOD        => static::ZAPPER_DESCRIPTION,
            self::SNAPSCAN_METHOD      => static::SNAPSCAN_DESCRIPTION,
            self::MOBICRED_METHOD      => static::MOBICRED_DESCRIPTION,
            self::MOMOPAY_METHOD       => static::MOMOPAY_METHOD_DETAIL,
            self::SCANTOPAY_METHOD     => static::SCANTOPAY_DESCRIPTION,
            self::PAYPAL_METHOD        => static::PAYPAL_DESCRIPTION,
        ];

        $this->_config                         = $configFactory->create($parameters);
        $this->curl                            = $curl;
        $this->initializeData($data);
    }

    /**
     * Initializes injected data
     *
     * @param array $data
     * @return void
     */
    protected function initializeData($data = [])
    {
        if (!empty($data['formBlockType'])) {
            $this->_formBlockType = $data['formBlockType'];
        }
    }

    /**
     * Store setter
     *
     * Also updates store ID in config object
     *
     * @param int|Store $storeId
     *
     * @return $this
     * @noinspection PhpUndefinedMethodInspection
     * @throws NoSuchEntityException
     */
    public function setStore($storeId): static
    {
        if (null === $storeId) {
            $storeId = $this->_storeManager->getStore()->getId();
        }
        $this->_config->setStoreId(is_object($storeId) ? $storeId->getId() : $storeId);

        return $this;
    }

    /**
     * Whether method is available for specified currency
     *
     * @param string $currencyCode
     *
     * @return bool
     */
    public function canUseForCurrency($currencyCode)
    {
        return $this->_config->isCurrencyCodeSupported($currencyCode);
    }

    /**
     * Payment action getter compatible with payment model
     *
     * @return string
     * @see    \Magento\Sales\Model\Payment::place()
     */
    public function getConfigPaymentAction()
    {
        return $this->_config->getPaymentAction();
    }

    /**
     * Check whether payment method can be used
     *
     * @param CartInterface|null $quote
     *
     * @return bool
     */
    public function isAvailable(CartInterface $quote = null)
    {
        return $this->_config->isMethodAvailable();
    }

    /**
     * Get Paygate id from configuration
     **/
    public function getPaygateId()
    {
        return $this->_config->getPaygateId();
    }

    /**
     * Get Encryption key from configuration
     **/
    public function getEncryptionKey()
    {
        return $this->_config->getEncryptionKey();
    }

    /**
     * Check is test mode or live
     **/
    public function isTestMode()
    {
        return $this->_config->isTestMode();
    }

    /**
     * This is where we compile data posted by the form to Paygate
     *
     * @return array
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @noinspection PhpUndefinedMethodInspection
     */
    public function getStandardCheckoutFormFields()
    {
        $pre             = __METHOD__ . ' : ';
        $order           = $this->_checkoutSession->getLastRealOrder();
        $customerSession = $this->customerSession;
        $encryptionKey   = $this->_config->getEncryptionKey();
        if (! $order || $order->getPayment() == null) {
            return ["error" => "invalid order"];
        }
        $orderData = $order->getPayment()->getData();

        $saveCard     = "new-save";
        $dontsaveCard = "new";
        $vaultId      = "";
        $vaultEnabled = 0;
        if ($customerSession->isLoggedIn() && isset($orderData['additional_information']['paygate-payvault-method'])) {
            $vaultEnabled = $orderData['additional_information']['paygate-payvault-method'];

            $vaultoptions = ['0', '1', 'new-save', 'new'];
            if (! in_array($vaultEnabled, $vaultoptions)) {
                $customerId = $customerSession->getCustomer()->getId();
                $cardData   = $this->paymentTokenManagementInterface->getByPublicHash($vaultEnabled, $customerId);
                if ($cardData->getEntityId()) {
                    $vaultId = $cardData->getGatewayToken();
                }
            }
        }

        $this->_logger->debug($pre . 'serverMode : ' . $this->getConfigData('test_mode'));

        $fields = $this->prepareFields($order);

        $this->_logger->debug($pre . 'Paygate order fields : ' . json_encode($fields));

        if (! empty($vaultId) && ($vaultEnabled == 1 || ($vaultEnabled == $saveCard)) && ($vaultEnabled !== 0)) {
            $fields['VAULT']    = 1;
            $fields['VAULT_ID'] = $vaultId;
        } elseif ($vaultEnabled === 0 || ($vaultEnabled == $dontsaveCard)) {
            unset($fields['VAULT']);
            unset($fields['VAULT_ID']);
        } elseif ($vaultEnabled == 1 && empty($vaultId)) {
            $fields['VAULT'] = 1;
        } elseif (! empty($vaultId)) {
            $fields['VAULT']    = 1;
            $fields['VAULT_ID'] = $vaultId;
        }

        //@codingStandardsIgnoreStart
        $fields['CHECKSUM'] = md5(implode('', $fields) . $encryptionKey);
        //@codingStandardsIgnoreEnd

        $response = $this->curlPost('https://secure.paygate.co.za/payweb3/initiate.trans', $fields);

        $this->uriHandler->setQuery($response);

        $result = $this->uriHandler->getQueryAsArray();

        if (isset($result['ERROR'])) {
            $this->_checkoutSession->restoreQuote();
            return ["error" => $result['ERROR']];
        } else {
            $processData             = [];
            $result['PAYMENT_TITLE'] = "PAYGATE_PAYWEB";
            $result["PAYMENT_METHOD_TYPE"] = $fields["PAY_METHOD"] ?? "";
            $this->_PaygateHelper->createTransaction($order, $result);
            if (! str_contains($response, "ERROR")) {
                $processData = [
                    'PAY_REQUEST_ID' => $result['PAY_REQUEST_ID'],
                    'CHECKSUM'       => $result['CHECKSUM'],
                ];
            }
        }

        return $processData;
    }

    /**
     * Prepare payment fields array
     *
     * @noinspection PhpUndefinedMethodInspection
     *
     * @param Order $order
     * @param string $api
     * @return array
     * @throws LocalizedException
     */
    public function prepareFields($order, $api = null): array
    {
        $billing   = $order->getBillingAddress();
        $formKey   = $this->_formKey->getFormKey();
        $reference = $order->getRealOrderId();

        $entityOrderId = $order->getId();

        $country_code2 = $billing->getCountryId();
        $country_code3 = '';
        if ($country_code2 != null || $country_code2 != '') {
            $country_code3 = $this->getCountryDetails($country_code2);
        }
        if ($country_code3 == null || $country_code3 == '') {
            $country_code3 = 'ZAF';
        }

        $currency = $order->getOrderCurrencyCode();

        $DateTime  = new DateTime();
        $paygateId = $this->_config->getPaygateId();

        if (! empty($order->getTotalDue())) {
            $price = number_format($order->getTotalDue(), 2, '', '');
        } else {
            $price = number_format($this->getTotalAmount($order), 2, '', '');
        }

        if ($api) {
            $reference .= "&api=true";
        }
        $orderData = $order->getPayment()->getData();

        $paymentType = $orderData['additional_information']['paygate-payment-type'] ?? '0';

        $fields = [
            'PAYGATE_ID'       => $paygateId,
            'REFERENCE'        => $order->getRealOrderId(),
            'AMOUNT'           => $price,
            'CURRENCY'         => $currency,
            'RETURN_URL'       => $this->_urlBuilder->getUrl(
                'paygate/redirect/success',
                [self::SECURE => true]
            ) . '?form_key=' . $formKey . '&gid=' . $reference,
            'TRANSACTION_DATE' => $DateTime->format('Y-m-d H:i:s'),
            'LOCALE'           => 'en-za',
            'COUNTRY'          => $country_code3,
            'EMAIL'            => $order->getCustomerEmail()
        ];

        if ($paymentType !== '0' && $this->getConfigData('paygate_pay_method_active') != '0') {
            $fields['PAY_METHOD']        = $this->getPaymentType($paymentType);
            $fields['PAY_METHOD_DETAIL'] = $this->getPaymentTypeDetail($paymentType);
        }

        $fields['NOTIFY_URL'] = $this->_urlBuilder->getUrl(
            'paygate/notify',
            ['_secure' => true]
        ) . '?eid=' . $entityOrderId;
        $fields['USER3']      = 'magento2-v2.5.5';

        return $fields;
    }

    /**
     * Gets payment type detail
     *
     * @param string $ptd
     * @return string
     */
    public function getPaymentTypeDetail($ptd): string
    {
        return match ($ptd) {
            self::BANK_TRANSFER => static::BANK_TRANSFER_METHOD_DETAIL,
            self::ZAPPER_METHOD => static::ZAPPER_DESCRIPTION,
            self::SNAPSCAN_METHOD => static::SNAPSCAN_DESCRIPTION,
            self::MOBICRED_METHOD => static::MOBICRED_DESCRIPTION,
            self::MOMOPAY_METHOD => static::MOMOPAY_METHOD_DETAIL,
            self::SCANTOPAY_METHOD => static::SCANTOPAY_DESCRIPTION,
            self::PAYPAL_METHOD => static::PAYPAL_DESCRIPTION,
            self::SAMSUNG_METHOD => static::SAMSUNG_DESCRIPTION_DETAIL,
            self::APPLE_METHOD => static::APPLE_DESCRIPTION_DETAIL,
            self::RCS_METHOD => static::RCS_DESCRIPTION,
            default => static::CREDIT_CARD_DESCRIPTION,
        };
    }

    /**
     * Gets payment type description
     *
     * @param string $ptd
     * @return string
     */
    public function getPaymentTypeDescription($ptd): string
    {
        return match ($ptd) {
            self::BANK_TRANSFER => static::BANK_TRANSFER_DESCRIPTION,
            self::ZAPPER_METHOD => static::ZAPPER_DESCRIPTION,
            self::SNAPSCAN_METHOD => static::SNAPSCAN_DESCRIPTION,
            self::MOBICRED_METHOD => static::MOBICRED_DESCRIPTION,
            self::MOMOPAY_METHOD => static::MOMOPAY_METHOD_DETAIL,
            self::SCANTOPAY_METHOD => static::SCANTOPAY_DESCRIPTION_LABEL,
            self::PAYPAL_METHOD => static::PAYPAL_DESCRIPTION,
            self::SAMSUNG_METHOD => static::SAMSUNG_DESCRIPTION,
            self::APPLE_METHOD => static::APPLE_DESCRIPTION,
            self::RCS_METHOD => static::RCS_DESCRIPTION,
            default => static::CREDIT_CARD_DESCRIPTION,
        };
    }

    /**
     * Gets payment type
     *
     * @param string $pt
     * @return string
     */
    public function getPaymentType($pt): string
    {
        return match ($pt) {
            self::BANK_TRANSFER => self::BANK_TRANSFER,
            self::ZAPPER_METHOD, self::PAYPAL_METHOD, self::SCANTOPAY_METHOD,
            self::MOMOPAY_METHOD, self::MOBICRED_METHOD,
            self::SAMSUNG_METHOD, self::SNAPSCAN_METHOD, => 'EW',
            default => 'CC',
        };
    }

    /**
     * Gets total amount
     *
     * @noinspection PhpUndefinedMethodInspection
     *
     * @param Order $order
     * @return string
     */
    public function getTotalAmount($order)
    {
        if ($this->getConfigData('use_store_currency')) {
            $price = $this->getNumberFormat($order->getGrandTotal());
        } else {
            $price = $this->getNumberFormat($order->getBaseGrandTotal());
        }

        return $price;
    }

    /**
     * Gets number format
     *
     * @param float $number
     * @return string
     */
    public function getNumberFormat($number)
    {
        return number_format($number, 2, '.', '');
    }

    /**
     * Gets payment successful URL
     */
    public function getPaidSuccessUrl()
    {
        return $this->_urlBuilder->getUrl('paygate/redirect/success', [self::SECURE => true]);
    }

    /**
     * Gets redirect URL
     *
     * @return string
     */
    public function getOrderPlaceRedirectUrl()
    {
        return $this->_urlBuilder->getUrl('paygate/redirect');
    }

    /**
     * Checkout redirect URL getter for onepage checkout (hardcode)
     *
     * @return string
     * @see    Quote\Payment::getCheckoutRedirectUrl()
     * @see    \Magento\Checkout\Controller\Onepage::savePaymentAction()
     */
    public function getCheckoutRedirectUrl()
    {
        return $this->getOrderPlaceRedirectUrl();
    }

    /**
     * Initial Order status
     *
     * @param string $paymentAction
     * @param object $stateObject
     *
     * @return $this
     */
    public function initialize($paymentAction, $stateObject): static
    {
        $stateObject->setState(Order::STATE_PENDING_PAYMENT);
        $stateObject->setStatus('pending_payment');
        $stateObject->setIsNotified(false);

        return $this;
    }

    /**
     * Gets Paid Notify URL
     */
    public function getPaidNotifyUrl()
    {
        return $this->_urlBuilder->getUrl('paygate/notify', [self::SECURE => true]);
    }

    /**
     * Post request
     *
     * @param string $url
     * @param array $fields
     * @return string
     */
    public function curlPost($url, $fields)
    {
        $this->curl->post($url, $fields);

        return $this->curl->getBody();
    }

    /**
     * Stores card vault
     *
     * @param Order $order
     * @param array $data
     * @return void
     */
    public function saveVaultData(Order $order, array $data): void
    {
        $paymentToken = $this->creditCardTokenFactory->create();

        $paymentToken->setGatewayToken($data['VAULT_ID']);
        $last4 = substr($data['PAYVAULT_DATA_1'], -4);
        $month = substr($data['PAYVAULT_DATA_2'], 0, 2);
        $year  = substr($data['PAYVAULT_DATA_2'], -4);
        $paymentToken->setTokenDetails(
            json_encode(
                [
                    'type'           => $data['PAY_METHOD_DETAIL'],
                    'maskedCC'       => $last4,
                    'expirationDate' => "$month/$year",
                ]
            )
        );

        $expiry = $this->getExpirationDate($month, $year);
        $paymentToken->setExpiresAt($expiry);

        $paymentToken->setMaskedCC("$last4");
        $paymentToken->setIsActive(true);
        $paymentToken->setIsVisible(true);
        $paymentToken->setPaymentMethodCode('paygate');
        $paymentToken->setCustomerId($order->getCustomerId());
        $paymentToken->setPublicHash($this->generatePublicHash($paymentToken));

        $this->paymentTokenRepository->save($paymentToken);

        /* Retrieve Payment Token */

        $this->creditCardTokenFactory->create();
        $this->addLinkToOrderPayment($paymentToken->getEntityId(), $order->getPayment()->getEntityId());
    }

    /**
     * Add link between payment token and order payment.
     *
     * @param int $paymentTokenId Payment token ID.
     * @param int $orderPaymentId Order payment ID.
     *
     * @return bool
     */
    public function addLinkToOrderPayment(int $paymentTokenId, int $orderPaymentId): bool
    {
        return $this->paymentTokenResourceModel->addLinkToOrderPayment($paymentTokenId, $orderPaymentId);
    }

    /**
     * Gets selected country details
     *
     * @param string $code2
     * @return mixed|string
     */
    #[Pure] public function getCountryDetails(string $code2)
    {
        $countryDataObject = new CountryData();
        $countries = $countryDataObject->getCountries();

        foreach ($countries as $key => $val) {
            if ($key == $code2) {
                return $val[2];
            }
        }

        return '';
    }

    /**
     * Gets order by order id
     *
     * @param int $order_id
     * @return OrderInterface
     */
    public function getOrderByOrderId(int $order_id)
    {
        return $this->orderRepository->get($order_id);
    }

    /**
     * @inheritdoc
     */
    public function fetchTransactionInfo(InfoInterface $payment, $transactionId): array
    {
        $state       = ObjectManager::getInstance()->get(State::class);
        $paymentData = [];
        if ($state->getAreaCode() == Area::AREA_ADMINHTML) {
            $order_id = $payment->getOrder()->getId();
            $order    = $this->getOrderByOrderId($order_id);

            $orderQuery['transaction_id'] = $transactionId;
            $orderQuery['reference']      = $order->getRealOrderId();
            $orderQuery['store_id']       = $order->getStoreId();
            $result                       = $this->_PaygateHelper->getQueryResult($orderQuery);
            $result                       = explode("&", $result);

            foreach ($result as $param) {
                $pr                  = explode("=", $param);
                $paymentData[$pr[0]] = $pr[1];
            }

            $paymentData['PAY_REQUEST_ID'] = $transactionId;
            $paymentData['PAYMENT_TITLE']  = "PAYGATE_PAYWEB";
            $this->_PaygateHelper->updatePaymentStatus($order, $result);
        }

        return $paymentData;
    }

    /**
     * Gets the name of the store
     *
     * @return mixed
     */
    protected function getStoreName()
    {
        return $this->_scopeConfig->getValue(
            'general/store_information/name',
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Place an order with authorization or capture action
     *
     * @param Payment $payment
     * @param float $amount
     *
     * @return void
     * @noinspection PhpUnusedParameterInspection
     */
    protected function _placeOrder(Payment $payment, float $amount)
    {
        $pre = __METHOD__ . " : ";
        $this->_logger->debug($pre . 'bof');
    }

    /**
     * Get transaction with type order
     *
     * @param OrderPaymentInterface $payment
     *
     * @return false|TransactionInterface
     */
    protected function getOrderTransaction(OrderPaymentInterface $payment): bool|TransactionInterface
    {
        return $this->transactionRepository->getByTransactionType(
            Transaction::TYPE_ORDER,
            $payment->getId(),
            $payment->getOrder()->getId()
        );
    }

    /**
     * Generate vault payment public hash
     *
     * @param PaymentTokenInterface $paymentToken
     *
     * @return string
     */
    protected function generatePublicHash(PaymentTokenInterface $paymentToken)
    {
        $hashKey = $paymentToken->getGatewayToken();
        if ($paymentToken->getCustomerId()) {
            $hashKey = $paymentToken->getCustomerId();
        }
        $paymentToken->getTokenDetails();

        $hashKey .= $paymentToken->getPaymentMethodCode() . $paymentToken->getType() . $paymentToken->getGatewayToken(
        ) . $paymentToken->getTokenDetails();

        return $this->encryptor->getHash($hashKey);
    }

    /**
     * Generate formatted expiration date
     *
     * @param int $month
     * @param int $year
     *
     * @return string
     */
    private function getExpirationDate(int $month, int $year)
    {
        $response = '';
        try {
            $expDate = new DateTime(
                $year
                . '-'
                . $month
                . '-'
                . '01'
                . ' '
                . '00:00:00',
                new DateTimeZone('UTC')
            );

            $expDate->add(new DateInterval('P1M'));

            $response = $expDate->format('Y-m-d 00:00:00');
        } catch (Exception $e) {
            $this->_logger->debug($e->getMessage());
        }

        return $response;
    }

    /**
     * Gets gateway code
     *
     * @return Paygate|string
     */
    public function getCode()
    {
        return $this->_code;
    }

    /**
     * Gets gateway form block type
     *
     * @return string
     */
    public function getFormBlockType()
    {
        return $this->_formBlockType;
    }

    /**
     * Gets gateway title
     *
     * @return string
     */
    public function getTitle()
    {
        return $this->getConfigData('title');
    }

    /**
     * Gets gateway store name
     *
     * @return mixed
     */
    public function getStore()
    {
        return $this->getStoreName();
    }

    /**
     * Gateway can order attribute
     *
     * @return bool
     */
    public function canOrder()
    {
        return $this->_canOrder;
    }

    /**
     * Gateway can authorize attribute
     *
     * @return bool
     */
    public function canAuthorize()
    {
        return $this->_canAuthorize;
    }

    /**
     * Gateway can capture attribute
     *
     * @return bool
     */
    public function canCapture()
    {
        return $this->_canCapture;
    }

    /**
     * Gateway can capture partial attribute
     *
     * @return bool
     */
    public function canCapturePartial()
    {
        return $this->_canCapture;
    }

    /**
     * Gateway can capture once attribute
     *
     * @return bool
     */
    public function canCaptureOnce()
    {
        return $this->_canCapture;
    }

    /**
     * Gateway can refund attribute
     *
     * @return false
     */
    public function canRefund()
    {
        return false;
    }

    /**
     * Gateway can refund partial invoice attribute
     *
     * @return false
     */
    public function canRefundPartialPerInvoice()
    {
        return false;
    }

    /**
     * Gateway can void attribute
     *
     * @return bool
     */
    public function canVoid()
    {
        return $this->_canVoid;
    }

    /**
     * Gateway can use internal attribute
     *
     * @return bool
     */
    public function canUseInternal()
    {
        return $this->_canUseInternal;
    }

    /**
     * Gateway can use checkout attribute
     *
     * @return bool
     */
    public function canUseCheckout()
    {
        return $this->_canUseCheckout;
    }

    /**
     * Gateway can edit attribute
     *
     * @return false
     */
    public function canEdit()
    {
        return true;
    }

    /**
     * Gateway can transaction fetch info attribute
     *
     * @return bool
     */
    public function canFetchTransactionInfo()
    {
        return $this->_canFetchTransactionInfo;
    }

    /**
     * Gateway is gateway attribute
     *
     * @return bool
     */
    public function isGateway()
    {
        return $this->_isGateway;
    }

    /**
     * Gateway is offline attribute
     *
     * @return false
     */
    public function isOffline()
    {
        return false;
    }

    /**
     * Gateway is initialisation needed attribute
     *
     * @return bool
     */
    public function isInitializeNeeded()
    {
        return $this->_isInitializeNeeded;
    }

    /**
     * To check billing country is allowed for the payment method
     *
     * @param string $country
     * @return bool
     */
    public function canUseForCountry($country)
    {
        /*
        for specific country, the flag will set up as 1
        */
        if ($this->getConfigData('allowspecific') == 1) {
            $availableCountries = explode(',', $this->getConfigData('specificcountry') ?? '');
            if (!in_array($country, $availableCountries)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Gateway get info block type
     *
     * @return string
     */
    public function getInfoBlockType()
    {
        return $this->_infoBlockType;
    }

    /**
     * Retrieve payment information model object
     *
     * @return InfoInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getInfoInstance()
    {
        $instance = $this->getData('info_instance');
        if (!$instance instanceof InfoInterface) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('We cannot retrieve the payment information object instance.')
            );
        }
        return $instance;
    }

    /**
     * Gateway set info instance
     *
     * @param InfoInterface $info
     * @return false
     */
    public function setInfoInstance(InfoInterface $info)
    {
        $this->setData('info_instance', $info);
    }

    /**
     * Validate payment method information object
     *
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function validate()
    {
        /**
         * to validate payment method is allowed for billing country or not
         */
        $paymentInfo = $this->getInfoInstance();
        if ($paymentInfo instanceof Payment) {
            $billingCountry = $paymentInfo->getOrder()->getBillingAddress()->getCountryId();
        } else {
            $billingCountry = $paymentInfo->getQuote()->getBillingAddress()->getCountryId();
        }
        $billingCountry = $billingCountry ?: $this->directory->getDefaultCountry();

        if (!$this->canUseForCountry($billingCountry)) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('You can\'t use the payment type you selected to make payments to the billing country.')
            );
        }

        return $this;
    }

    /**
     * Gateway order function
     *
     * @param InfoInterface $payment
     * @param float $amount
     * @return PayGate
     */
    public function order(InfoInterface $payment, $amount)
    {
        return $this;
    }

    /**
     * Gateway authorize function
     *
     * @param InfoInterface $payment
     * @param float $amount
     * @return PayGate
     */
    public function authorize(InfoInterface $payment, $amount)
    {
        return $this;
    }

    /**
     * Gateway capture function
     *
     * @param InfoInterface $payment
     * @param float $amount
     * @return PayGate
     */
    public function capture(InfoInterface $payment, $amount)
    {
        return $this;
    }

    /**
     * Gateway refund function
     *
     * @param InfoInterface $payment
     * @param float $amount
     * @return PayGate
     */
    public function refund(InfoInterface $payment, $amount)
    {
        return $this;
    }

    /**
     * Gateway cancel function
     *
     * @param InfoInterface $payment
     * @return PayGate
     */
    public function cancel(InfoInterface $payment)
    {
        return $this;
    }

    /**
     * Gateway void function
     *
     * @param InfoInterface $payment
     * @return PayGate
     */
    public function void(InfoInterface $payment)
    {
        return $this;
    }

    /**
     * Gateway can review attribute
     *
     * @return bool
     */
    public function canReviewPayment()
    {
        return $this->_canReviewPayment;
    }

    /**
     * Gateway accept payment attribute
     *
     * @param InfoInterface $payment
     * @return false
     */
    public function acceptPayment(InfoInterface $payment)
    {
        return false;
    }

    /**
     * Gateway deny payment attribute
     *
     * @param InfoInterface $payment
     * @return PayGate
     */
    public function denyPayment(InfoInterface $payment)
    {
        return $this;
    }

    /**
     * Retrieve information from payment configuration
     *
     * @param string $field
     * @param int|string|null|Store $storeId
     *
     * @return mixed
     * @throws NoSuchEntityException
     */
    public function getConfigData($field, $storeId = null)
    {
        if ('order_place_redirect_url' === $field) {
            return $this->getOrderPlaceRedirectUrl();
        }
        if (null === $storeId) {
            $storeId = $this->_storeManager->getStore()->getId();
        }
        $path = 'payment/' . $this->getCode() . '/' . $field;
        return $this->_scopeConfig->getValue($path, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * Assign data to info model instance
     *
     * @param array|\Magento\Framework\DataObject $data
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function assignData(\Magento\Framework\DataObject $data)
    {
        $this->_eventManager->dispatch(
            'payment_method_assign_data_' . $this->getCode(),
            [
                AbstractDataAssignObserver::METHOD_CODE => $this,
                AbstractDataAssignObserver::MODEL_CODE => $this->getInfoInstance(),
                AbstractDataAssignObserver::DATA_CODE => $data
            ]
        );

        $this->_eventManager->dispatch(
            'payment_method_assign_data',
            [
                AbstractDataAssignObserver::METHOD_CODE => $this,
                AbstractDataAssignObserver::MODEL_CODE => $this->getInfoInstance(),
                AbstractDataAssignObserver::DATA_CODE => $data
            ]
        );

        return $this;
    }

    /**
     * Is active
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isActive($storeId = null)
    {
        return (bool)(int)$this->getConfigData('active', $storeId);
    }
}
