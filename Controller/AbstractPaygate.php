<?php
/**
 * @noinspection PhpMissingFieldTypeInspection
 */

/**
 * @noinspection PhpUndefinedNamespaceInspection
 */

/**
 * @noinspection PhpPropertyOnlyWrittenInspection
 */

/**
 * @noinspection PhpUnused
 */

/*
 * Copyright (c) 2023 Payfast (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

namespace PayGate\PayWeb\Controller;

use Exception;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Customer\Model\Url;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\DB\TransactionFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Session\Generic;
use Magento\Framework\Stdlib\DateTime\DateTime;
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
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\Webapi\Rest\Request;
use Magento\Framework\Message\ManagerInterface;
use Magento\Checkout\Controller\Express\RedirectLoginInterface;

/**
 * Checkout Controller
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
abstract class AbstractPaygate implements
    HttpPostActionInterface,
    HttpGetActionInterface,
    CsrfAwareActionInterface
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
    protected string $_configType = Config::class;

    /**
     * Config method type
     *
     * @var string|PayGate
     */
    protected Paygate|string $_configMethod = Config::METHOD_CODE;

    /**
     * Checkout mode type
     *
     * @var string
     */
    protected string $_checkoutType;

    /**
     * @var CustomerSession
     */
    protected CustomerSession $_customerSession;

    /**
     * @var CheckoutSession $_checkoutSession
     */
    protected CheckoutSession $_checkoutSession;

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
     * @var Order $_order
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
     * @var StoreManagerInterface $storeManager
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

    /**
     * @var InvoiceService
     */
    protected InvoiceService $_invoiceService;

    /**
     * @var InvoiceSender
     */
    protected InvoiceSender $invoiceSender;

    /**
     * @var OrderSender
     */
    protected OrderSender $OrderSender;

    /**
     * @var UrlInterface
     */
    private UrlInterface $_urlBuilder;

    /**
     * @var DateTime
     */
    private DateTime $_date;
    /**
     * @var ObjectManagerInterface
     */
    protected ObjectManagerInterface $objectManager;
    /**
     * @var ResultFactory
     */
    protected ResultFactory $resultFactory;
    /**
     * @var Request
     */
    protected Request $request;
    /**
     * @var ManagerInterface
     */
    protected ManagerInterface $messageManager;
    /**
     * @var Response
     */
    protected Response $response;

    /**
     * @param PageFactory $pageFactory
     * @param CustomerSession $customerSession
     * @param CheckoutSession $checkoutSession
     * @param OrderFactory $orderFactory
     * @param Generic $paygateSession
     * @param Data $urlHelper
     * @param Url $customerUrl
     * @param LoggerInterface $logger
     * @param TransactionFactory $transactionFactory
     * @param InvoiceService $invoiceService
     * @param InvoiceSender $invoiceSender
     * @param PayGate $paymentMethod
     * @param UrlInterface $urlBuilder
     * @param OrderRepositoryInterface $orderRepository
     * @param StoreManagerInterface $storeManager
     * @param OrderSender $OrderSender
     * @param DateTime $date
     * @param CollectionFactory $orderCollectionFactory
     * @param Builder $_transactionBuilder
     * @param ObjectManagerInterface $objectManager
     * @param Request $request
     * @param ManagerInterface $messageManager
     * @param ResultFactory $resultFactory
     */
    public function __construct(
        PageFactory                     $pageFactory,
        CustomerSession                 $customerSession,
        CheckoutSession                 $checkoutSession,
        OrderFactory                    $orderFactory,
        Generic                         $paygateSession,
        Data                            $urlHelper,
        Url                             $customerUrl,
        LoggerInterface                 $logger,
        TransactionFactory              $transactionFactory,
        InvoiceService                  $invoiceService,
        InvoiceSender                   $invoiceSender,
        PayGate                         $paymentMethod,
        UrlInterface                    $urlBuilder,
        OrderRepositoryInterface        $orderRepository,
        StoreManagerInterface           $storeManager,
        OrderSender                     $OrderSender,
        DateTime                        $date,
        CollectionFactory               $orderCollectionFactory,
        Builder                         $_transactionBuilder,
        ObjectManagerInterface          $objectManager,
        Request                         $request,
        ManagerInterface                $messageManager,
        ResultFactory                   $resultFactory
    ) {
        $pre = __METHOD__ . " : ";

        $this->_logger = $logger;

        $this->_logger->debug($pre . 'bof');

        $this->_customerSession = $customerSession;
        $this->_checkoutSession = $checkoutSession;
        $this->_orderFactory = $orderFactory;
        $this->paygateSession = $paygateSession;
        $this->_urlHelper = $urlHelper;
        $this->_customerUrl = $customerUrl;
        $this->pageFactory = $pageFactory;
        $this->_invoiceService = $invoiceService;
        $this->invoiceSender = $invoiceSender;
        $this->OrderSender = $OrderSender;
        $this->_transactionFactory = $transactionFactory;
        $this->_paymentMethod = $paymentMethod;
        $this->_urlBuilder = $urlBuilder;
        $this->orderRepository = $orderRepository;
        $this->_storeManager = $storeManager;
        $this->_date = $date;
        $this->_orderCollectionFactory = $orderCollectionFactory;
        $this->_transactionBuilder = $_transactionBuilder;
        $this->objectManager = $objectManager;
        $this->request = $request;
        $this->messageManager = $messageManager;

        $parameters = ['params' => [$this->_configMethod]];
        $this->_config = $this->objectManager->create($this->_configType, $parameters);

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

    /**
     * Returns a list of action flags [flag_key] => boolean
     *
     * @return array
     */
    public function getActionFlagList(): array
    {
        return [];
    }

    /**
     * Returns login url parameter for redirect
     *
     * @return string
     */
    public function getLoginUrl(): string
    {
        return $this->_customerUrl->getLoginUrl();
    }

    /**
     * Returns action name which requires redirect
     *
     * @return string
     */
    public function getRedirectActionName(): string
    {
        return 'index';
    }

    /**
     * Redirect to login page
     *
     * @return       void
     * @noinspection PhpUndefinedMethodInspection
     */
    public function redirectLogin()
    {
        $this->_actionFlag->set('', 'no-dispatch', true);
        $this->_customerSession->setBeforeAuthUrl($this->_redirect->getRefererUrl());
        $this->request->setRedirect(
            $this->_urlHelper->addRequestParam($this->_customerUrl->getLoginUrl(), ['context' => 'checkout'])
        );
    }

    /**
     * Creates a transaction
     *
     * @param Order|null $order
     * @param array $paymentData
     * @return string
     */
    public function createTransaction(Order $order = null, array $paymentData = []): string
    {
        $response = '';

        if ($order === null) {
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
            $formattedPrice = $order->getBaseCurrency()->formatTxt(
                $order->getGrandTotal()
            );

            $ipnOrRedirect = $this->_paymentMethod->getConfigData('ipn_method') == '0' ? 'IPN' : 'Redirect';

            $message = __($ipnOrRedirect . ': The authorized amount is %1.', $formattedPrice);
            // Get the object of builder class
            $trans = $this->_transactionBuilder;
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
     * Paygate session instance getter
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
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    protected function _getQuote(): bool|Quote
    {
        if (!$this->_quote) {
            $this->_quote = $this->_getCheckoutSession()->getQuote();
        }

        return $this->_quote;
    }

    /**
     * Csrf null exception
     *
     * @param RequestInterface $request
     * @return InvalidRequestException|null
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /**
     * Csrf validation
     *
     * @param RequestInterface $request
     * @return bool|null
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
