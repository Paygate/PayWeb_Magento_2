<?php
/*
 * Copyright (c) 2021 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

namespace PayGate\PayWeb\Model;

use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Api\PaymentTokenManagementInterface;
use Magento\Vault\Api\PaymentTokenRepositoryInterface;
use Magento\Vault\Model\CreditCardTokenFactory;
use Magento\Vault\Model\ResourceModel\PaymentToken as PaymentTokenResourceModel;
use PayGate\PayWeb\Model\CountryData;

/**
 * @SuppressWarnings(PHPMD.TooManyFields)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class PayGate extends \Magento\Payment\Model\Method\AbstractMethod
{
    /**
     * @var string
     */
    protected $_code = Config::METHOD_CODE;

    /**
     * @var string
     */
    protected $_formBlockType = 'PayGate\PayWeb\Block\Form';

    /**
     * @var string
     */
    protected $_infoBlockType = 'PayGate\PayWeb\Block\Payment\Info';

    /**
     * @var string
     */
    protected $_configType = 'PayGate\PayWeb\Model\Config';

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
     * @var \PayGate\PayWeb\Model\Config $config
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
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @var \Magento\Framework\UrlInterface
     */
    protected $_urlBuilder;

    /**
     * @var \Magento\Framework\UrlInterface
     */
    protected $_formKey;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $_checkoutSession;

    /**
     * @var \Magento\Framework\Exception\LocalizedExceptionFactory
     */
    protected $_exception;

    /**
     * @var \Magento\Sales\Api\TransactionRepositoryInterface
     */
    protected $transactionRepository;

    /**
     * @var Transaction\BuilderInterface
     */
    protected $transactionBuilder;
    protected $creditCardTokenFactory;
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
	
    protected $_PaygateHelper;

    /**
     * @var PaymentTokenResourceModel
     */
    protected $paymentTokenResourceModel;

    const CREDIT_CARD                 = 'pw3_credit_card';
    const BANK_TRANSFER               = 'BT';
    const ZAPPER                      = 'pw3_e_zapper';
    const SNAPSCAN                    = 'pw3_e_snapscan';
    const MOBICRED                    = 'pw3_e_mobicred';
    const MOMOPAY                     = 'pw3_e_momopay';
    const MASTERPASS                  = 'pw3_e_masterpass';
    const CREDIT_CARD_METHOD          = 'CC';
    const BANK_TRANSFER_METHOD        = 'BT';
    const ZAPPER_METHOD               = 'EW-ZAPPER';
    const SNAPSCAN_METHOD             = 'EW-SNAPSCAN';
    const MOBICRED_METHOD             = 'EW-MOBICRED';
    const MOMOPAY_METHOD              = 'EW-MOMOPAY';
    const MASTERPASS_METHOD           = 'EW-MASTERPASS';
    const CREDIT_CARD_DESCRIPTION     = 'Card';
    const BANK_TRANSFER_DESCRIPTION   = 'SiD Secure EFT';
    const BANK_TRANSFER_METHOD_DETAIL = 'SID';
    const ZAPPER_DESCRIPTION          = 'Zapper';
    const SNAPSCAN_DESCRIPTION        = 'SnapScan';
    const MOBICRED_DESCRIPTION        = 'Mobicred';
    const MOMOPAY_DESCRIPTION         = 'MoMoPay';
    const MOMOPAY_METHOD_DETAIL       = 'Momopay';
    const MASTERPASS_DESCRIPTION      = 'MasterPass';
    const SECURE                      = '_secure';

    protected $paymentTypes = [
        self::CREDIT_CARD_METHOD   => self::CREDIT_CARD_DESCRIPTION,
        self::BANK_TRANSFER_METHOD => self::BANK_TRANSFER_METHOD_DETAIL,
        self::ZAPPER_METHOD        => self::ZAPPER_DESCRIPTION,
        self::SNAPSCAN_METHOD      => self::SNAPSCAN_DESCRIPTION,
        self::MOBICRED_METHOD      => self::MOBICRED_DESCRIPTION,
        self::MOMOPAY_METHOD       => self::MOMOPAY_METHOD_DETAIL,
        self::MASTERPASS_METHOD    => self::MASTERPASS_DESCRIPTION,
    ];

    public function __construct( \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Payment\Model\Method\Logger $logger,
        ConfigFactory $configFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\UrlInterface $urlBuilder,
        \Magento\Framework\Data\Form\FormKey $formKey,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Framework\Exception\LocalizedExceptionFactory $exception,
        \Magento\Sales\Api\TransactionRepositoryInterface $transactionRepository,
        \Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface $transactionBuilder,
        CreditCardTokenFactory $CreditCardTokenFactory,
        PaymentTokenRepositoryInterface $PaymentTokenRepositoryInterface,
        PaymentTokenManagementInterface $paymentTokenManagement,
        EncryptorInterface $encryptor,
        PaymentTokenResourceModel $paymentTokenResourceModel,
        \Magento\Customer\Model\Session $session,
        \Magento\Vault\Api\PaymentTokenManagementInterface $paymentTokenManagementInterface,
		\PayGate\PayWeb\Helper\Data  $PaygateHelper,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = [] ) {
        parent::__construct( $context, $registry, $extensionFactory, $customAttributeFactory, $paymentData, $scopeConfig, $logger, $resource, $resourceCollection, $data );
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
        $this->session                         = $session;
        $this->paymentTokenManagementInterface = $paymentTokenManagementInterface;
        $this->encryptor                       = $encryptor;
        $this->paymentTokenResourceModel       = $paymentTokenResourceModel;
        $this->_PaygateHelper       = $PaygateHelper;

        $parameters = ['params' => [$this->_code]];

        $this->_config = $configFactory->create( $parameters );

    }

    /**
     * Store setter
     * Also updates store ID in config object
     *
     * @param \Magento\Store\Model\Store|int $store
     *
     * @return $this
     */
    public function setStore( $store )
    {
        $this->setData( 'store', $store );

        if ( null === $store ) {
            $store = $this->_storeManager->getStore()->getId();
        }
        $this->_config->setStoreId( is_object( $store ) ? $store->getId() : $store );

        return $this;
    }

    /**
     * Whether method is available for specified currency
     *
     * @param string $currencyCode
     *
     * @return bool
     */
    public function canUseForCurrency( $currencyCode )
    {
        return $this->_config->isCurrencyCodeSupported( $currencyCode );
    }

    /**
     * Payment action getter compatible with payment model
     *
     * @see \Magento\Sales\Model\Payment::place()
     * @return string
     */
    public function getConfigPaymentAction()
    {
        return $this->_config->getPaymentAction();
    }

    /**
     * Check whether payment method can be used
     *
     * @param \Magento\Quote\Api\Data\CartInterface|Quote|null $quote
     *
     * @return bool
     */
    public function isAvailable( \Magento\Quote\Api\Data\CartInterface $quote = null )
    {
        return parent::isAvailable( $quote ) && $this->_config->isMethodAvailable();
    }

    /**
     * @return mixed
     */
    protected function getStoreName()
    {

        return $this->_scopeConfig->getValue(
            'general/store_information/name',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

    }

    /**
     * Place an order with authorization or capture action
     *
     * @param Payment $payment
     * @param float $amount
     *
     * @return $this
     */
    protected function _placeOrder( Payment $payment, $amount )
    {

        $pre = __METHOD__ . " : ";
        $this->_logger->debug( $pre . 'bof' );

    }

    /**
     * This is where we compile data posted by the form to PayGate
     * @return array
     */
    public function getStandardCheckoutFormFields()
    {
        $pre = __METHOD__ . ' : ';
        // Variable initialization

        $order           = $this->_checkoutSession->getLastRealOrder();
        $customerSession = $this->session;
        $baseurl         = $this->_storeManager->getStore()->getBaseUrl();
        if ( !$order || $order->getPayment() == null ) {
            echo '<script>window.top.location.href="' . $baseurl . 'checkout/cart/";</script>';
            exit();
        }
        $orderData    = $order->getPayment()->getData();
        $saveCard     = "new-save";
        $dontsaveCard = "new";
        $paymentType  = isset($orderData['additional_information']) && isset($orderData['additional_information']['paygate-payment-type']) ? $orderData['additional_information']['paygate-payment-type'] : '0';
        $vaultId      = "";
        $vaultEnabled = 0;
        if ( $customerSession->isLoggedIn() && isset( $orderData['additional_information']['paygate-payvault-method'] ) ) {

            $vaultEnabled = $orderData['additional_information']['paygate-payvault-method'];

            $vaultoptions = array( '0', '1', 'new-save', 'new' );
            if ( !in_array( $vaultEnabled, $vaultoptions ) ) {

                $customerId = $customerSession->getCustomer()->getId();
                $cardData   = $this->paymentTokenManagementInterface->getByPublicHash( $vaultEnabled, $customerId );
                if ( $cardData->getEntityId() ) {
                    $vaultId = $cardData->getGatewayToken();
                }
            }
        }

        $this->_logger->debug( $pre . 'serverMode : ' . $this->getConfigData( 'test_mode' ) );

        // If NOT test mode, use normal credentials
        if ( $this->getConfigData( 'test_mode' ) != '1' ) {
            $paygateId     = trim( $this->getConfigData( 'paygate_id' ) );
            $encryptionKey = $this->getConfigData( 'encryption_key' );
        } else {
            $paygateId     = '10011072130';
            $encryptionKey = 'secret';
        }

        $billing       = $order->getBillingAddress();
        $country_code2 = $billing->getCountryId();

        $country_code3 = '';
        if ( $country_code2 != null || $country_code2 != '' ) {
            $country_code3 = $this->getCountryDetails( $country_code2 );
        }
        if ( $country_code3 == null || $country_code3 == '' ) {
            $country_code3 = 'ZAF';
        }
        $DateTime = new \DateTime();

        $fields = array(
            'PAYGATE_ID'       => $paygateId,
            'REFERENCE'        => $order->getRealOrderId(),
            'AMOUNT'           => number_format( $this->getTotalAmount( $order ), 2, '', '' ),
            'CURRENCY'         => $order->getOrderCurrencyCode(),
            'RETURN_URL'       => $this->_urlBuilder->getUrl( 'paygate/redirect/success', array( self::SECURE => true ) ) . '?form_key=' . $this->_formKey->getFormKey() . '&gid=' . $order->getRealOrderId(),
            'TRANSACTION_DATE' => $DateTime->format( 'Y-m-d H:i:s' ),
            'LOCALE'           => 'en-za',
            'COUNTRY'          => $country_code3,
            'EMAIL'            => $order->getData( 'customer_email' ),
        );
        
        if ( $paymentType !== '0' && $this->getConfigData( 'paygate_pay_method_active' ) != '0' ) {
            $fields['PAY_METHOD']        = $this->getPaymentType( $paymentType );
            $fields['PAY_METHOD_DETAIL'] = $this->getPaymentTypeDetail( $paymentType );
        }

        $fields['NOTIFY_URL'] = $this->_urlBuilder->getUrl( 'paygate/notify', array( '_secure' => true ) );
        $fields['USER3']      = 'magento2-v2.4.0';

        if ( !empty( $vaultId ) && ( $vaultEnabled == 1 || ( $vaultEnabled == $saveCard ) ) && ( $vaultEnabled !== 0 ) ) {
            $fields['VAULT']    = 1;
            $fields['VAULT_ID'] = $vaultId;
        } elseif ( $vaultEnabled === 0 || ( $vaultEnabled == $dontsaveCard ) ) {
            unset( $fields['VAULT'] );
            unset( $fields['VAULT_ID'] );
        }elseif($vaultEnabled == 1 && empty($vaultId)){
			$fields['VAULT']    = 1;
		}elseif(!empty($vaultId)){
			$fields['VAULT']    = 1;
            $fields['VAULT_ID'] = $vaultId;
		}

        $fields['CHECKSUM'] = md5( implode( '', $fields ) . $encryptionKey );
		

        $response = $this->curlPost( 'https://secure.paygate.co.za/payweb3/initiate.trans', $fields );

        parse_str( $response, $result );
		
        if ( isset( $result['ERROR'] ) ) {
            echo "Error Code: " . $result['ERROR'];
            $this->_checkoutSession->restoreQuote();
            $baseurl = $this->_storeManager->getStore()->getBaseUrl();
            echo '<br/><br/><a href="' . $baseurl . 'checkout/cart/">Go Back</a>';
            exit( 0 );

        } else {
			
            $processData = array();
			$this->_PaygateHelper->createTransaction($order,$result);
            if ( strpos( $response, "ERROR" ) === false ) {
                $processData = array(
                    'PAY_REQUEST_ID' => $result['PAY_REQUEST_ID'],
                    'CHECKSUM'       => $result['CHECKSUM'],
                );
            }

        }

        return ( $processData );
    }

    public function getPaymentTypeDetail( $ptd )
    {
        switch ( $ptd ) {
            case self::BANK_TRANSFER:
                return self::BANK_TRANSFER_METHOD_DETAIL;
                break;
            case self::ZAPPER_METHOD:
                return self::ZAPPER_DESCRIPTION;
                break;
            case self::SNAPSCAN_METHOD:
                return self::SNAPSCAN_DESCRIPTION;
                break;
            case self::MOBICRED_METHOD:
                return self::MOBICRED_DESCRIPTION;
                break;
            case self::MOMOPAY_METHOD:
                return self::MOMOPAY_METHOD_DETAIL;
                break;
            case self::MASTERPASS_METHOD:
                return self::MASTERPASS_DESCRIPTION;
                break;
            default:
                return self::CREDIT_CARD_DESCRIPTION;
                break;
        }
    }

    public function getPaymentType( $pt )
    {
        switch ( $pt ) {
            case self::BANK_TRANSFER:
                return self::BANK_TRANSFER;
                break;
            case self::ZAPPER_METHOD:
                return 'EW';
                break;
            case self::SNAPSCAN_METHOD:
                return 'EW';
                break;
            case self::MOBICRED_METHOD:
                return 'EW';
                break;
            case self::MOMOPAY_METHOD:
                return 'EW';
                break;
            case self::MASTERPASS_METHOD:
                return 'EW';
                break;
            default:
                return 'CC';
                break;
        }
    }

    /**
     * getTotalAmount
     */
    public function getTotalAmount( $order )
    {
        if ( $this->getConfigData( 'use_store_currency' ) ) {
            $price = $this->getNumberFormat( $order->getGrandTotal() );
        } else {
            $price = $this->getNumberFormat( $order->getBaseGrandTotal() );
        }

        return $price;
    }

    /**
     * getNumberFormat
     */
    public function getNumberFormat( $number )
    {
        return number_format( $number, 2, '.', '' );
    }

    /**
     * getPaidSuccessUrl
     */
    public function getPaidSuccessUrl()
    {
        return $this->_urlBuilder->getUrl( 'paygate/redirect/success', array( self::SECURE => true ) );
    }

    /**
     * Get transaction with type order
     *
     * @param OrderPaymentInterface $payment
     *
     * @return false|\Magento\Sales\Api\Data\TransactionInterface
     */
    protected function getOrderTransaction( $payment )
    {
        return $this->transactionRepository->getByTransactionType( Transaction::TYPE_ORDER, $payment->getId(), $payment->getOrder()->getId() );
    }

    /*
     * called dynamically by checkout's framework.
     */
    public function getOrderPlaceRedirectUrl()
    {
        return $this->_urlBuilder->getUrl( 'paygate/redirect' );

    }
    /**
     * Checkout redirect URL getter for onepage checkout (hardcode)
     *
     * @see \Magento\Checkout\Controller\Onepage::savePaymentAction()
     * @see Quote\Payment::getCheckoutRedirectUrl()
     * @return string
     */
    public function getCheckoutRedirectUrl()
    {
        return $this->getOrderPlaceRedirectUrl();
    }

    /**
     *
     * @param string $paymentAction
     * @param object $stateObject
     *
     * @return $this
     */
    public function initialize( $paymentAction, $stateObject )
    {
        $stateObject->setState( \Magento\Sales\Model\Order::STATE_PENDING_PAYMENT );
        $stateObject->setStatus( 'pending_payment' );
        $stateObject->setIsNotified( false );

        return parent::initialize( $paymentAction, $stateObject );

    }

    /**
     * getPaidNotifyUrl
     */
    public function getPaidNotifyUrl()
    {
        return $this->_urlBuilder->getUrl( 'paygate/notify', array( self::SECURE => true ) );
    }

    public function curlPost( $url, $fields )
    {
        $curl = curl_init( $url );
        curl_setopt( $curl, CURLOPT_POST, count( $fields ) );
        curl_setopt( $curl, CURLOPT_POSTFIELDS, $fields );
        curl_setopt( $curl, CURLOPT_RETURNTRANSFER, 1 );
        $response = curl_exec( $curl );
        curl_close( $curl );
        return $response;
    }

    public function saveVaultData( $order, $data )
    {
        $paymentToken = $this->creditCardTokenFactory->create();

        $paymentToken->setGatewayToken( $data['VAULT_ID'] );
        $last4 = substr( $data['PAYVAULT_DATA_1'], -4 );
        $month = substr( $data['PAYVAULT_DATA_2'], 0, 2 );
        $year  = substr( $data['PAYVAULT_DATA_2'], -4 );
        $paymentToken->setTokenDetails( json_encode( [
            'type'           => $data['PAY_METHOD_DETAIL'],
            'maskedCC'       => $last4,
            'expirationDate' => "$month/$year",
        ] ) );

        $expiry = $this->getExpirationDate( $month, $year );
        $paymentToken->setExpiresAt( $expiry );

        $paymentToken->setMaskedCC( "$last4" );
        $paymentToken->setIsActive( true );
        $paymentToken->setIsVisible( true );
        $paymentToken->setPaymentMethodCode( 'paygate' );
        $paymentToken->setCustomerId( $order->getCustomerId() );
        $paymentToken->setPublicHash( $this->generatePublicHash( $paymentToken ) );

        $this->paymentTokenRepository->save( $paymentToken );

        /* Retrieve Payment Token */

        $this->creditCardTokenFactory->create();
        $this->addLinkToOrderPayment( $paymentToken->getEntityId(), $order->getPayment()->getEntityId() );

    }

    /**
     * Add link between payment token and order payment.
     *
     * @param int $paymentTokenId Payment token ID.
     * @param int $orderPaymentId Order payment ID.
     * @return bool
     */
    public function addLinkToOrderPayment( $paymentTokenId, $orderPaymentId )
    {
        return $this->paymentTokenResourceModel->addLinkToOrderPayment( $paymentTokenId, $orderPaymentId );
    }

    /**
     * @param Payment $payment
     * @return string
     */
    private function getExpirationDate( $month, $year )
    {
        $expDate = new \DateTime(
            $year
            . '-'
            . $month
            . '-'
            . '01'
            . ' '
            . '00:00:00',
            new \DateTimeZone( 'UTC' )
        );
        $expDate->add( new \DateInterval( 'P1M' ) );
        return $expDate->format( 'Y-m-d 00:00:00' );
    }

    /**
     * Generate vault payment public hash
     *
     * @param PaymentTokenInterface $paymentToken
     * @return string
     */
    protected function generatePublicHash( PaymentTokenInterface $paymentToken )
    {
        $hashKey = $paymentToken->getGatewayToken();
        if ( $paymentToken->getCustomerId() ) {
            $hashKey = $paymentToken->getCustomerId();
        }
        $paymentToken->getTokenDetails();

        $hashKey .= $paymentToken->getPaymentMethodCode() . $paymentToken->getType() . $paymentToken->getGatewayToken() . $paymentToken->getTokenDetails();

        return $this->encryptor->getHash( $hashKey );
    }

    public function getCountryDetails( $code2 )
    {
        $countries = CountryData::getCountries();

        foreach ( $countries as $key => $val ) {

            if ( $key == $code2 ) {
                return $val[2];
            }

        }
    }
}
