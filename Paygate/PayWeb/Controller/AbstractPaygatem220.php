<?php
/*
 * Copyright (c) 2020 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

namespace PayGate\PayWeb\Controller;

use Magento\Checkout\Controller\Express\RedirectLoginInterface;
use Magento\Framework\App\Action\Action as AppAction;
use PayGate\PayWeb\Model\PayGate;

/**
 * Checkout Controller
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
abstract class AbstractPaygatem220 extends AppAction implements RedirectLoginInterface
{
    /**
     * Internal cache of checkout models
     *
     * @var array
     */
    protected $_checkoutTypes = [];

    /**
     * @var \PayGate\PayWeb\Model\Config
     */
    protected $_config;

    /**
     * @var \Magento\Quote\Model\Quote
     */
    protected $_quote = false;

    /**
     * Config mode type
     *
     * @var string
     */
    protected $_configType = 'PayGate\PayWeb\Model\Config';

    /** Config method type @var string */
    protected $_configMethod = \PayGate\PayWeb\Model\Config::METHOD_CODE;

    /**
     * Checkout mode type
     *
     * @var string
     */
    protected $_checkoutType;

    /**
     * @var \Magento\Customer\Model\Session
     */
    protected $_customerSession;

    /**
     * @var \Magento\Checkout\Model\Session $_checkoutSession
     */
    protected $_checkoutSession;

    /**
     * @var \Magento\Sales\Model\OrderFactory
     */
    protected $_orderFactory;

    /**
     * @var \Magento\Framework\Session\Generic
     */
    protected $paygateSession;

    /**
     * @var \Magento\Framework\Url\Helper
     */
    protected $_urlHelper;

    /**
     * @var \Magento\Customer\Model\Url
     */
    protected $_customerUrl;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $_logger;

    /**
     * @var  \Magento\Sales\Model\Order $_order
     */
    protected $_order;

    /**
     * @var \Magento\Framework\View\Result\PageFactory
     */
    protected $pageFactory;

    /**
     * @var \Magento\Framework\DB\TransactionFactory
     */
    protected $_transactionFactory;

    /**
     * @var  \Magento\Store\Model\StoreManagerInterface $storeManager
     */
    protected $_storeManager;

    /**
     * @var \PayGate\PayWeb\Model\PayGate $_paymentMethod
     */
    protected $_paymentMethod;

    /**
     * @var \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
     */
    protected $orderRepository;

    /**
     * @var \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $_orderCollectionFactory
     */
    protected $_orderCollectionFactory;

    /**
     * @var Magento\Sales\Model\Order\Payment\Transaction\Builder $_transactionBuilder
     */
    protected $_transactionBuilder;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $pageFactory,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Framework\Session\Generic $paygateSession,
        \Magento\Framework\Url\Helper\Data $urlHelper,
        \Magento\Customer\Model\Url $customerUrl,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\DB\TransactionFactory $transactionFactory,
        \Magento\Sales\Model\Service\InvoiceService $invoiceService,
        \Magento\Sales\Model\Order\Email\Sender\InvoiceSender $invoiceSender,
        \PayGate\PayWeb\Model\PayGate $paymentMethod,
        \Magento\Framework\UrlInterface $urlBuilder,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Sales\Model\Order\Email\Sender\OrderSender $OrderSender,
        \Magento\Framework\Stdlib\DateTime\DateTime $date,
        \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollectionFactory,
        \Magento\Sales\Model\Order\Payment\Transaction\Builder $_transactionBuilder
    ) {
        $pre = __METHOD__ . " : ";

        $this->_logger = $logger;

        $this->_logger->debug( $pre . 'bof' );

        $this->_customerSession        = $customerSession;
        $this->_checkoutSession        = $checkoutSession;
        $this->_orderFactory           = $orderFactory;
        $this->paygateSession          = $paygateSession;
        $this->_urlHelper              = $urlHelper;
        $this->_customerUrl            = $customerUrl;
        $this->pageFactory             = $pageFactory;
        $this->_invoiceService         = $invoiceService;
        $this->invoiceSender           = $invoiceSender;
        $this->OrderSender             = $OrderSender;
        $this->_transactionFactory     = $transactionFactory;
        $this->_paymentMethod          = $paymentMethod;
        $this->_urlBuilder             = $urlBuilder;
        $this->orderRepository         = $orderRepository;
        $this->_storeManager           = $storeManager;
        $this->_date                   = $date;
        $this->_orderCollectionFactory = $orderCollectionFactory;
        $this->_transactionBuilder     = $_transactionBuilder;

        parent::__construct( $context );

        $parameters    = ['params' => [$this->_configMethod]];
        $this->_config = $this->_objectManager->create( $this->_configType, $parameters );

        $this->_logger->debug( $pre . 'eof' );
    }

    /**
     * Custom getter for payment configuration
     *
     * @param string $field i.e paygate_id, test_mode
     *
     * @return mixed
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getConfigData( $field )
    {
        return $this->_paymentMethod->getConfigData( $field );
    }

    /**
     * Instantiate
     *
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function _initCheckout()
    {

        $pre = __METHOD__ . " : ";
        $this->_logger->debug( $pre . 'bof' );
        $this->_order = $this->_checkoutSession->getLastRealOrder();

        if ( !$this->_order->getId() ) {
            $this->getResponse()->setStatusHeader( 404, '1.1', 'Not found' );
            throw new \Magento\Framework\Exception\LocalizedException( __( 'We could not find "Order" for processing' ) );
        }

        if ( $this->_order->getState() != \Magento\Sales\Model\Order::STATE_PENDING_PAYMENT ) {
            $this->_order->setState(
                \Magento\Sales\Model\Order::STATE_PENDING_PAYMENT
            )->save();
        }

        if ( $this->_order->getQuoteId() ) {
            $this->_checkoutSession->setPaygateQuoteId( $this->_checkoutSession->getQuoteId() );
            $this->_checkoutSession->setPaygateSuccessQuoteId( $this->_checkoutSession->getLastSuccessQuoteId() );
            $this->_checkoutSession->setPaygateRealOrderId( $this->_checkoutSession->getLastRealOrderId() );
            $this->_checkoutSession->getQuote()->setIsActive( false )->save();
        }

        $this->_logger->debug( $pre . 'eof' );

    }

    /**
     * PayGate session instance getter
     *
     * @return \Magento\Framework\Session\Generic
     */
    protected function _getSession()
    {
        return $this->paygateSession;
    }

    /**
     * Return checkout session object
     *
     * @return \Magento\Checkout\Model\Session
     */
    protected function _getCheckoutSession()
    {
        return $this->_checkoutSession;
    }

    /**
     * Return checkout quote object
     *
     * @return \Magento\Quote\Model\Quote
     */
    protected function _getQuote()
    {
        if ( !$this->_quote ) {
            $this->_quote = $this->_getCheckoutSession()->getQuote();
        }

        return $this->_quote;
    }

    /**
     * @return null
     */

    // Returns before_auth_url redirect parameter for customer session

    public function getCustomerBeforeAuthUrl()
    {}

    /**
     * Returns a list of action flags [flag_key] => boolean
     * @return array
     */
    public function getActionFlagList()
    {
        return [];
    }

    /**
     * Returns login url parameter for redirect
     * @return string
     */
    public function getLoginUrl()
    {
        return $this->_customerUrl->getLoginUrl();
    }

    /**
     * Returns action name which requires redirect
     * @return string
     */
    public function getRedirectActionName()
    {
        return 'index';
    }

    /**
     * Redirect to login page
     *
     * @return void
     */
    public function redirectLogin()
    {
        $this->_actionFlag->set( '', 'no-dispatch', true );
        $this->_customerSession->setBeforeAuthUrl( $this->_redirect->getRefererUrl() );
        $this->getResponse()->setRedirect(
            $this->_urlHelper->addRequestParam( $this->_customerUrl->getLoginUrl(), ['context' => 'checkout'] )
        );
    }

    public function createTransaction( $order = null, $paymentData = array() )
    {
        if ( is_null( $order ) ) {
            $order = $this->_order;
        }

        try {
            // Get payment object from order object
            $payment = $order->getPayment();
            $payment->setLastTransId( $paymentData['PAY_REQUEST_ID'] )
                ->setTransactionId( $paymentData['PAY_REQUEST_ID'] )
                ->setAdditionalInformation( [\Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS => (array) $paymentData] );
            $formatedPrice = $order->getBaseCurrency()->formatTxt(
                $order->getGrandTotal()
            );

            $ipnOrRedirect = $this->_paymentMethod->getConfigData( 'ipn_method' ) == '0' ? 'IPN' : 'Redirect';

            $message = __( $ipnOrRedirect . ': The authorized amount is %1.', $formatedPrice );
            // Get the object of builder class
            $trans       = $this->_transactionBuilder;
            $transaction = $trans->setPayment( $payment )
                ->setOrder( $order )
                ->setTransactionId( $paymentData['PAY_REQUEST_ID'] )
                ->setAdditionalInformation(
                    [\Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS => (array) $paymentData]
                )
                ->setFailSafe( true )
            // Build method creates the transaction and returns the object
                ->build( \Magento\Sales\Model\Order\Payment\Transaction::TYPE_CAPTURE );

            $payment->addTransactionCommentsToOrder(
                $transaction,
                $message
            );
            $payment->setParentTransactionId( null );
            $payment->save();
            $order->save();

            return $transaction->save()->getTransactionId();
        } catch ( \Exception $e ) {
            $this->_logger->error( $e->getMessage() );
        }
    }

}
