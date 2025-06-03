<?php

/**
 * @noinspection PhpUnused
 */

/**
 * @noinspection PhpPropertyOnlyWrittenInspection
 */

/*
 * Copyright (c) 2025 Payfast (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

namespace PayGate\PayWeb\Controller\Redirect;

use Exception;
use Laminas\Uri\Uri;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Customer\Model\Url;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\DB\Transaction;
use Magento\Framework\DB\TransactionFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use GuzzleHttp\Client;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Session\Generic;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\Url\Helper\Data;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Result\PageFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\OrderStatusHistoryRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Payment\Transaction\Builder;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Store\Model\StoreManagerInterface;
use PayGate\PayWeb\Controller\AbstractPaygate;
use PayGate\PayWeb\Model\Config as PayGateConfig;
use PayGate\PayWeb\Model\ConfigFactory;
use PayGate\PayWeb\Model\PayGate;
use Psr\Log\LoggerInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Framework\Webapi\Rest\Request;
use Magento\Checkout\Controller\Express\RedirectLoginInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Customer;
use Magento\Framework\App\ObjectManager;
use PayGate\PayWeb\Model\Config;

/**
 * Responsible for loading page content.
 *
 * This is a basic controller that only loads the corresponding layout file. It may duplicate other such
 * controllers, and thus it is considered tech debt. This code duplication will be resolved in future releases.
 */
class Success extends AbstractPaygate implements RedirectLoginInterface
{
    /**
     * @var PayGate $_paymentMethod
     */
    protected PayGate $_paymentMethod;

    /**
     * @var Transaction
     */
    private Transaction $transactionModel;

    /**
     * @var ScopeConfigInterface
     */
    private ScopeConfigInterface $scopeConfigInterface;

    /**
     * @var Order
     */
    private Order $orderModel;

    /**
     * @var Client
     */
    protected Client $httpClient;

    /**
     * @var ResultFactory
     */
    protected ResultFactory $resultFactory;
    /**
     * @var Uri
     */
    private Uri $uriHandler;
    /**
     * @var Request
     */
    protected Request $request;
    /**
     * @var Url
     */
    protected Url $customerUrl;
    /**
     * @var CustomerSession
     */
    private CustomerSession $customerSession;
    /**
     * @var CustomerRepositoryInterface
     */
    private CustomerRepositoryInterface $customerRepository;
    /**
     * @var ConfigFactory|PayGateConfig
     */
    private ConfigFactory|PayGateConfig $_paygateconfig;
    /**
     * @var string
     */
    private string $enableLogging;
    /**
     * @var OrderStatusHistoryRepositoryInterface
     */
    private OrderStatusHistoryRepositoryInterface $orderStatusHistoryRepository;

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
     * @param OrderSender $orderSender
     * @param DateTime $date
     * @param CollectionFactory $orderCollectionFactory
     * @param Builder $_transactionBuilder
     * @param Order $orderModel
     * @param ScopeConfigInterface $scopeConfigInterface
     * @param Transaction $transactionModel
     * @param Client $httpClient
     * @param ObjectManagerInterface $objectManager
     * @param Uri $uriHandler
     * @param Request $request
     * @param ManagerInterface $messageManager
     * @param ResultFactory $resultFactory
     * @param CustomerRepositoryInterface $customerRepository
     * @param PayGateConfig $paygateconfig
     * @param OrderStatusHistoryRepositoryInterface $orderStatusHistoryRepository
     */
    public function __construct(
        PageFactory $pageFactory,
        CustomerSession $customerSession,
        CheckoutSession $checkoutSession,
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
        OrderSender $orderSender,
        DateTime $date,
        CollectionFactory $orderCollectionFactory,
        Builder $_transactionBuilder,
        Order $orderModel,
        ScopeConfigInterface $scopeConfigInterface,
        Transaction $transactionModel,
        Client $httpClient,
        ObjectManagerInterface $objectManager,
        Uri $uriHandler,
        Request $request,
        ManagerInterface $messageManager,
        ResultFactory $resultFactory,
        CustomerRepositoryInterface $customerRepository,
        PayGateConfig $paygateconfig,
        OrderStatusHistoryRepositoryInterface $orderStatusHistoryRepository
    ) {
        $this->orderModel                   = $orderModel;
        $this->scopeConfigInterface         = $scopeConfigInterface;
        $this->transactionModel             = $transactionModel;
        $this->httpClient                   = $httpClient;
        $this->uriHandler                   = $uriHandler;
        $this->resultFactory                = $resultFactory;
        $this->customerUrl                  = $customerUrl;
        $this->customerSession              = $customerSession;
        $this->customerRepository           = $customerRepository;
        $this->_paygateconfig               = $paygateconfig;
        $this->enableLogging                = $this->_paygateconfig->getEnableLogging();
        $this->orderStatusHistoryRepository = $orderStatusHistoryRepository;

        parent::__construct(
            $pageFactory,
            $customerSession,
            $checkoutSession,
            $orderFactory,
            $paygateSession,
            $urlHelper,
            $customerUrl,
            $logger,
            $transactionFactory,
            $invoiceService,
            $invoiceSender,
            $paymentMethod,
            $urlBuilder,
            $orderRepository,
            $storeManager,
            $orderSender,
            $date,
            $orderCollectionFactory,
            $_transactionBuilder,
            $objectManager,
            $request,
            $messageManager,
            $resultFactory
        );
    }

    /**
     * Execute on paygate/redirect/success
     *
     * @noinspection PhpUndefinedMethodInspection
     */
    public function execute()
    {
        $pre = __METHOD__ . " : ";
        $this->_logger->debug($pre . 'bof');
        $data         = $this->request->getPostValue();

        $this->_order = $this->_checkoutSession->getLastRealOrder();
        $order        = $this->_order;
        $resultRedirectFactory = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        if (!$this->_order->getId()) {
            $this->setlastOrderDetails();
            $order = $this->_order;
        }

        $cartPath    = 'checkout/cart/';
        $successPath = 'checkout/onepage/success/';

        if (!($this->_order->getId()) || !isset($data['PAY_REQUEST_ID'])) {
            $resultRedirect = $resultRedirectFactory->setPath($cartPath);
        } else {
            try {
                $this->notify($data);
            } catch (Exception $ex) {
                $this->_logger->error($ex->getMessage());
            }

            $this->pageFactory->create();
            try {
                //Get Payment
                $payment           = $order->getPayment();
                $paymentMethodType = $payment->getAdditionalInformation()
                                     ['raw_details_info']["PAYMENT_METHOD_TYPE"] ?? "0";

                $paygateId     = $this->_paymentMethod->getPaygateId();
                $encryptionKey = $this->_paymentMethod->getEncryptionKey();

                $pay_request_id              = $data['PAY_REQUEST_ID'];
                $status                      = $data['TRANSACTION_STATUS'] ?? "";
                $reference                   = $order->getRealOrderId();
                $checksum                    = $data['CHECKSUM'] ?? "";
                $data['PAYMENT_TITLE']       = "PAYGATE_PAYWEB";
                $data["PAYMENT_METHOD_TYPE"] = $paymentMethodType;

                $checksum_source = $paygateId . $pay_request_id . $status . $reference . $encryptionKey;
                //@codingStandardsIgnoreStart
                $test_checksum = md5($checksum_source);
                //@codingStandardsIgnoreEnd

                $validateChecksum = hash_equals($checksum, $test_checksum);

                if ($this->enableLogging === '1') {
                    $this->_logger->info('Reference @ Success.php: ' . json_encode($reference));
                    $this->_logger->info('Checksum @ Success.php: ' . json_encode($checksum));
                }

                if (!empty($status) && $validateChecksum && $this->processOrder($order, $status, $data)) {
                    $this->loginCustomer($order);
                    $resultRedirect = $resultRedirectFactory->setPath($successPath);
                } elseif ($validateChecksum) {
                    $this->loginCustomer($order);
                    $resultRedirect = $resultRedirectFactory->setPath($cartPath);
                } else {
                    $resultRedirect = $resultRedirectFactory->setPath($cartPath);
                }
            } catch (Exception $e) {
                // Save Transaction Response
                $this->createTransaction($order, $data);
                $this->_logger->error($pre . $e->getMessage());
                $this->messageManager->addExceptionMessage($e, __('We can\'t start Paygate Checkout.'));
                $resultRedirect = $resultRedirectFactory->setPath($cartPath);
            }
        }

        return $resultRedirect;
    }

    /**
     * Securely log in the customer
     *
     * @param $order
     *
     * @return void
     */
    private function loginCustomer($order): void
    {
        $objectManager = ObjectManager::getInstance();

        $customerId = $order->getCustomerId();

        if ($customerId !== null) {
            $customerData = $objectManager->create(Customer::class)
                                          ->load($customerId);

            $this->customerSession->setCustomerAsLoggedIn($customerData);
        }
    }

    /**
     * Process order after redirect from pay page
     *
     * @param OrderInterface $order
     * @param int $status
     * @param array $data
     *
     * @return bool
     * @throws LocalizedException
     */
    public function processOrder(Order $order, int $status, array $data): bool
    {
        $success = false;

        $canProcessThisOrder = $this->_paymentMethod->getConfigData(
            'ipn_method'
        ) != '0' && $order->getPaywebPaymentProcessed() != 1;

        switch ($status) {
            case 1:
                // Check if order process by IPN or Redirect
                if ($canProcessThisOrder) {
                    $order->setPaywebPaymentProcessed(1)->save();
                    $status = Order::STATE_PROCESSING;
                    $state  = Order::STATE_PROCESSING;
                    if ($this->getConfigData('successful_order_status') != "") {
                        $status = $this->getConfigData('successful_order_status');
                    }

                    if ($this->getConfigData('successful_order_state') != "") {
                        $state = $this->getConfigData('successful_order_state');
                    }

                    // Capture invoice when payment is successful
                    $invoice = $this->_invoiceService->prepareInvoice($order);
                    $invoice->setRequestedCaptureCase(Invoice::CAPTURE_ONLINE);
                    $invoice->register();

                    // Save the invoice to the order
                    $transaction = $this->transactionModel
                        ->addObject($invoice)
                        ->addObject($invoice->getOrder());

                    $transaction->save();

                    // Magento\Sales\Model\Order\Email\Sender\InvoiceSender
                    $send_invoice_email = $this->getConfigData('invoice_email');
                    if ($send_invoice_email != '0') {
                        $this->invoiceSender->send($invoice);
                        // Add status history comment
                        $history = $order->addCommentToStatusHistory(
                            __('Notified customer about order #%1.', $order->getId())
                        );
                        $history->setIsCustomerNotified(true);

                        try {
                            // Save the status history
                            $this->orderStatusHistoryRepository->save($history);

                            // Save the order
                            $this->orderRepository->save($order);
                        } catch (LocalizedException $e) {
                            // Handle any exceptions during the save process
                            $this->_logger->error('Order save error: ' . $e->getMessage());
                        }
                    }

                    // Save Transaction Response
                    $this->createTransaction($order, $data);
                    $order->setState($state)->setStatus($status);
                    $this->orderRepository->save($order);

                    if ($this->enableLogging === '1') {
                        $this->_logger->info('Order #' . $order->getId() . ' Saved @ Success.php');
                    }
                }

                $order_successful_email = $this->getConfigData('order_email');

                if ($order_successful_email != '0') {
                    $this->orderSender->send($order);
                    // Add status history comment
                    $history = $order->addCommentToStatusHistory(
                        __('Notified customer about order #%1.', $order->getId())
                    );
                    $history->setIsCustomerNotified(true);

                    try {
                        // Save the status history
                        $this->orderStatusHistoryRepository->save($history);

                        // Save the order
                        $this->orderRepository->save($order);
                    } catch (LocalizedException $e) {
                        // Handle any exceptions during the save process
                        $this->_logger->error('Order save error: ' . $e->getMessage());
                    }
                }

                $success = true;
                break;
            case 2:
                // Save Transaction Response
                $this->messageManager->addNoticeMessage('Transaction has been declined.');
                $this->_checkoutSession->restoreQuote();
                if ($canProcessThisOrder) {
                    $order->setPaywebPaymentProcessed(1)->save();
                    $this->createTransaction($order, $data);
                    $order->cancel();
                    $history = $order->addCommentToStatusHistory(
                        __(
                            'Redirect Response, update order.'
                        )
                    );
                    $this->orderStatusHistoryRepository->save($history);
                    $this->orderRepository->save($order);

                    if ($this->enableLogging === '1') {
                        $this->_logger->info('Order #' . $order->getId() . ' Declined @ Success.php');
                    }
                }
                break;
            default:
                $this->messageManager->addNoticeMessage('Transaction has been cancelled');
                $this->_checkoutSession->restoreQuote();
                if ($canProcessThisOrder) {
                    $order->setPaywebPaymentProcessed(1)->save();
                    $this->createTransaction($order, $data);
                    $order->cancel();
                    $history = $order->addCommentToStatusHistory(
                        __(
                            'Redirect Response, update order.'
                        )
                    );
                    $this->orderStatusHistoryRepository->save($history);
                    $this->orderRepository->save($order);

                    if ($this->enableLogging === '1') {
                        $this->_logger->info('Order #' . $order->getId() . ' Cancelled @ Success.php');
                    }
                }
                break;
        }

        return $success;
    }

    /**
     * Notify Paygate
     *
     * @param array $data
     *
     * @return void
     */
    public function notify(array $data): void
    {
        $order = $this->_order;

        $paygateId     = $this->_paymentMethod->getPaygateId();
        $encryptionKey = $this->_paymentMethod->getEncryptionKey();

        $data = [
            'PAYGATE_ID'     => $paygateId,
            'PAY_REQUEST_ID' => $data['PAY_REQUEST_ID'],
            'REFERENCE'      => $order->getRealOrderId(),
        ];

        //@codingStandardsIgnoreStart
        $checksum = md5(implode('', $data) . $encryptionKey);
        //@codingStandardsIgnoreEnd

        $data['CHECKSUM'] = $checksum;
        $fieldsString     = http_build_query($data);

        // Open connection
        $response = $this->httpClient->post('https://secure.paygate.co.za/payweb3/query.trans', [
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'body'    => $fieldsString,
        ]);

        $responseBody = $response->getBody()->getContents();
        $this->uriHandler->setQuery($responseBody);

        $responseArray = $this->uriHandler->getQueryAsArray();

        if (isset($responseArray['VAULT_ID'])) {
            $model = $this->_paymentMethod;
            $model->saveVaultData($order, $responseArray);
        }
    }

    /**
     * Gets order by increment ID
     *
     * @param int $incrementId
     *
     * @return Order
     */
    public function getOrderByIncrementId(int $incrementId): Order
    {
        return $this->orderModel->loadByIncrementId($incrementId);
    }

    /**
     * Sets order data
     *
     * @noinspection PhpUndefinedMethodInspection
     */
    public function setlastOrderDetails(): void
    {
        $orderId      = $this->request->getParam('gid');
        $this->_order = $this->getOrderByIncrementId($orderId);
        $order        = $this->_order;
        $this->_checkoutSession->setData('last_order_id', $order->getId());
        $this->_checkoutSession->setData('last_success_quote_id', $order->getQuoteId());
        $this->_checkoutSession->setData('last_quote_id', $order->getQuoteId());
        $this->_checkoutSession->setData('last_real_order_id', $orderId);
    }

    /**
     * Customer auth url
     *
     * @return string|null
     */
    public function getCustomerBeforeAuthUrl()
    {
        return $this->objectManager->create(
            \Magento\Framework\UrlInterface::class
        )->getUrl('*/*', ['_secure' => true]);
    }

    /**
     * Gets response
     *
     * @return null
     */
    public function getResponse()
    {
        return null;
    }
}
