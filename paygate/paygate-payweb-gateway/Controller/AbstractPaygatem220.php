<?php
/** @noinspection PhpMissingFieldTypeInspection */

/** @noinspection PhpUndefinedNamespaceInspection */

/** @noinspection PhpPropertyOnlyWrittenInspection */

/** @noinspection PhpUnused */

/*
 * Copyright (c) 2022 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

namespace PayGate\PayWeb\Controller;

use Exception;
use Magento\Checkout\Controller\Express\RedirectLoginInterface;
use Magento\Checkout\Model\Session;
use Magento\Customer\Model\Url;
use Magento\Framework\App\Action\Action as AppAction;
use Magento\Framework\App\Action\Context;
use Magento\Framework\DB\TransactionFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Session\Generic;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\Url\Helper;
use Magento\Framework\Url\Helper\Data;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Result\PageFactory;
use Magento\Quote\Model\Quote;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Sales\Model\Order\Payment\Transaction\Builder;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Store\Model\StoreManagerInterface;
use PayGate\PayWeb\Model\Config;
use PayGate\PayWeb\Model\PayGate;
use Psr\Log\LoggerInterface;

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
    protected array $_checkoutTypes = [];

    /**
     * @var Config
     */
    protected Config $_config;

    /**
     * @var bool|Quote
     */
    protected Quote|bool $_quote = false;

    /**
     * Config mode type
     *
     * @var string
     */
    protected string $_configType = 'PayGate\PayWeb\Model\Config';

    /** Config method type @var string|PayGate */
    protected Paygate|string $_configMethod = Config::METHOD_CODE;

    /**
     * Checkout mode type
     *
     * @var string
     */
    protected string $_checkoutType;

    /**
     * @var \Magento\Customer\Model\Session
     */
    protected \Magento\Customer\Model\Session $_customerSession;

    /**
     * @var Session $_checkoutSession
     */
    protected Session $_checkoutSession;

    /**
     * @var OrderFactory
     */
    protected OrderFactory $_orderFactory;

    /**
     * @var Generic
     */
    protected Generic $paygateSession;

    /**
     * @var Data|Helper
     */
    protected Helper|Data $_urlHelper;

    /**
     * @var Url
     */
    protected Url $_customerUrl;

    /**
     * @var LoggerInterface
     */
    protected LoggerInterface $_logger;

    /**
     * @var  Order $_order
     */
    protected Order $_order;

    /**
     * @var PageFactory
     */
    protected PageFactory $pageFactory;

    /**
     * @var TransactionFactory
     */
    protected TransactionFactory $_transactionFactory;

    /**
     * @var  StoreManagerInterface $storeManager
     */
    protected StoreManagerInterface $_storeManager;

    /**
     * @var PayGate $_paymentMethod
     */
    protected PayGate $_paymentMethod;

    /**
     * @var OrderRepositoryInterface $orderRepository
     */
    protected OrderRepositoryInterface $orderRepository;

    /**
     * @var CollectionFactory $_orderCollectionFactory
     */
    protected CollectionFactory $_orderCollectionFactory;

    /**
     * @var Magento\Sales\Model\Order\Payment\Transaction\Builder|Builder $_transactionBuilder
     */
    protected Magento\Sales\Model\Order\Payment\Transaction\Builder|Builder $_transactionBuilder;
    protected InvoiceService $_invoiceService;
    protected InvoiceSender $invoiceSender;
    protected OrderSender $OrderSender;
    protected $_objectManager;
    protected $_actionFlag;
    protected $_redirect;
    private UrlInterface $_urlBuilder;
    private DateTime $_date;

    public function __construct(
        Context $context,
        PageFactory $pageFactory,
        \Magento\Customer\Model\Session $customerSession,
        Session $checkoutSession,
        OrderFactory $orderFactory,
        Generic $paygateSession,
        Data $urlHelper,
        Url $customerUrl,
        LoggerInterface $logger,
        TransactionFactory $transactionFactory,
        InvoiceService $invoiceService,
        InvoiceSender $invoiceSender,
        PayGate $paymentMethod,
        UrlInterface $urlBuilder,
        OrderRepositoryInterface $orderRepository,
        StoreManagerInterface $storeManager,
        OrderSender $OrderSender,
        DateTime $date,
        CollectionFactory $orderCollectionFactory,
        Builder $_transactionBuilder
    ) {
        $pre = __METHOD__ . " : ";

        $this->_logger = $logger;

        $this->_logger->debug($pre . 'bof');

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

        parent::__construct($context);

        $parameters    = ['params' => [$this->_configMethod]];
        $this->_config = $this->_objectManager->create($this->_configType, $parameters);

        $this->_logger->debug($pre . 'eof');
    }

    /**
     * Custom getter for payment configuration
     *
     * @param string $field i.e paygate_id, test_mode
     *
     * @return mixed
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @noinspection PhpUndefinedMethodInspection
     */
    public function getConfigData(string $field): mixed
    {
        return $this->_paymentMethod->getConfigData($field);
    }


    public function getCustomerBeforeAuthUrl()
    {
    }

    /**
     * Returns a list of action flags [flag_key] => boolean
     * @return array
     */
    public function getActionFlagList(): array
    {
        return [];
    }

    /**
     * Returns login url parameter for redirect
     * @return string
     */
    public function getLoginUrl(): string
    {
        return $this->_customerUrl->getLoginUrl();
    }

    /**
     * Returns action name which requires redirect
     * @return string
     */
    public function getRedirectActionName(): string
    {
        return 'index';
    }

    /**
     * @return null
     */

    // Returns before_auth_url redirect parameter for customer session
    /**
     * Redirect to login page
     *
     * @return void
     * @noinspection PhpUndefinedMethodInspection
     */
    public function redirectLogin()
    {
        $this->_actionFlag->set('', 'no-dispatch', true);
        $this->_customerSession->setBeforeAuthUrl($this->_redirect->getRefererUrl());
        $this->getResponse()->setRedirect(
            $this->_urlHelper->addRequestParam($this->_customerUrl->getLoginUrl(), ['context' => 'checkout'])
        );
    }

    /** @noinspection PhpUndefinedMethodInspection */
    public function createTransaction($order = null, $paymentData = array()): string
    {
        $response = '';

        if (is_null($order)) {
            $order = $this->_order;
        }

        try {
            // Get payment object from order object
            $payment = $order->getPayment();
            $payment->setLastTransId($paymentData['PAY_REQUEST_ID'])
                    ->setTransactionId($paymentData['PAY_REQUEST_ID'])
                    ->setAdditionalInformation(
                        [Transaction::RAW_DETAILS => (array)$paymentData]
                    );
            $formatedPrice = $order->getBaseCurrency()->formatTxt(
                $order->getGrandTotal()
            );

            $ipnOrRedirect = $this->_paymentMethod->getConfigData('ipn_method') == '0' ? 'IPN' : 'Redirect';

            $message = __($ipnOrRedirect . ': The authorized amount is %1.', $formatedPrice);
            // Get the object of builder class
            $trans       = $this->_transactionBuilder;
            $transaction = $trans->setPayment($payment)
                                 ->setOrder($order)
                                 ->setTransactionId($paymentData['PAY_REQUEST_ID'])
                                 ->setAdditionalInformation(
                                     [Transaction::RAW_DETAILS => (array)$paymentData]
                                 )
                                 ->setFailSafe(true)
                // Build method creates the transaction and returns the object
                                 ->build(Transaction::TYPE_CAPTURE);

            $payment->addTransactionCommentsToOrder(
                $transaction,
                $message
            );
            $payment->setParentTransactionId(null);
            $payment->save();
            $order->save();

            $response = $transaction->save()->getTransactionId();
        } catch (Exception $e) {
            $this->_logger->error($e->getMessage());
        }

        return $response;
    }

    /**
     * Instantiate
     *
     * @return void
     * @throws LocalizedException
     * @noinspection PhpUndefinedMethodInspection
     */
    protected function _initCheckout()
    {
        $pre = __METHOD__ . " : ";
        $this->_logger->debug($pre . 'bof');
        $this->_order = $this->_checkoutSession->getLastRealOrder();

        if ( ! $this->_order->getId()) {
            $this->getResponse()->setStatusHeader(404, '1.1', 'Not found');
            throw new LocalizedException(__('We could not find "Order" for processing'));
        }

        if ($this->_order->getState() != Order::STATE_PENDING_PAYMENT) {
            $this->_order->setState(
                Order::STATE_PENDING_PAYMENT
            )->save();
        }

        if ($this->_order->getQuoteId()) {
            $this->_checkoutSession->setPaygateQuoteId($this->_checkoutSession->getQuoteId());
            $this->_checkoutSession->setPaygateSuccessQuoteId($this->_checkoutSession->getLastSuccessQuoteId());
            $this->_checkoutSession->setPaygateRealOrderId($this->_checkoutSession->getLastRealOrderId());
            $this->_checkoutSession->getQuote()->setIsActive(false)->save();
        }

        $this->_logger->debug($pre . 'eof');
    }

    /**
     * PayGate session instance getter
     *
     * @return Generic
     */
    protected function _getSession(): Generic
    {
        return $this->paygateSession;
    }

    /**
     * Return checkout session object
     *
     * @return Session
     */
    protected function _getCheckoutSession(): Session
    {
        return $this->_checkoutSession;
    }

    /**
     * Return checkout quote object
     *
     * @return bool|Quote
     */
    protected function _getQuote(): bool|Quote
    {
        if ( ! $this->_quote) {
            $this->_quote = $this->_getCheckoutSession()->getQuote();
        }

        return $this->_quote;
    }

}
