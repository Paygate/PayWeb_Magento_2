<?php

/**
 * @noinspection PhpUnused
 */

/*
 * Copyright (c) 2025 Payfast (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

namespace PayGate\PayWeb\Controller\Notify;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Customer\Model\Url;
use Magento\Framework\DB\Transaction;
use Magento\Framework\DB\TransactionFactory;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Session\Generic;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\Url\Helper\Data;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Result\PageFactory;
use Magento\Sales\Api\Data\OrderStatusHistoryInterfaceFactory;
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
use Magento\Framework\Controller\ResultFactory;
use Magento\Store\Model\StoreManagerInterface;
use PayGate\PayWeb\Controller\AbstractPaygate;
use PayGate\PayWeb\Model\Config as PayGateConfig;
use PayGate\PayWeb\Model\ConfigFactory;
use PayGate\PayWeb\Model\PayGate;
use Psr\Log\LoggerInterface;
use Magento\Framework\Webapi\Rest\Request;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\App\CacheInterface;
use PayGate\PayWeb\Helper\OrderLock;
use PayGate\PayWeb\Helper\TransactionRetry;

class Index extends AbstractPaygate
{
    /**
     * @var Transaction
     */
    private Transaction $transactionModel;
    /**
     * @var ConfigFactory|PayGateConfig
     */
    private ConfigFactory|PayGateConfig $_paygateconfig;
    /**
     * @var string
     */
    private string $enableLogging;
    /**
     * @var EncryptorInterface
     */
    protected $encryptor;
    /**
     * @var OrderStatusHistoryRepositoryInterface
     */
    private OrderStatusHistoryRepositoryInterface $orderStatusHistoryRepository;
    /**
     * @var OrderStatusHistoryInterfaceFactory
     */
    private OrderStatusHistoryInterfaceFactory $historyFactory;
    /**
     * @var OrderFactory
     */
    protected $orderFactory;
    /**
     * @var CacheInterface
     */
    protected $cache;

    /**
     * @var OrderLock
     */
    private OrderLock $orderLock;

    /**
     * @var TransactionRetry
     */
    private TransactionRetry $transactionRetry;

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
     * @param Transaction $transactionModel
     * @param ObjectManagerInterface $objectManager
     * @param Request $request
     * @param ManagerInterface $messageManager
     * @param ResultFactory $resultFactory
     * @param ResponseInterface $responseInterface
     * @param PayGateConfig $paygateconfig
     * @param EncryptorInterface $encryptor
     * @param OrderStatusHistoryRepositoryInterface $orderStatusHistoryRepository
     * @param OrderStatusHistoryInterfaceFactory $historyFactory
     * @param CacheInterface $cache
     * @param OrderLock $orderLock
     * @param TransactionRetry $transactionRetry
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
        Transaction $transactionModel,
        ObjectManagerInterface $objectManager,
        Request $request,
        ManagerInterface $messageManager,
        ResultFactory $resultFactory,
        PayGateConfig $paygateconfig,
        EncryptorInterface $encryptor,
        OrderStatusHistoryRepositoryInterface $orderStatusHistoryRepository,
        OrderStatusHistoryInterfaceFactory $historyFactory,
        CacheInterface $cache,
        OrderLock $orderLock,
        TransactionRetry $transactionRetry
    ) {
        $this->transactionModel             = $transactionModel;
        $this->resultFactory                = $resultFactory;
        $this->_paygateconfig               = $paygateconfig;
        $this->enableLogging                = $this->_paygateconfig->getEnableLogging();
        $this->encryptor                    = $encryptor;
        $this->orderStatusHistoryRepository = $orderStatusHistoryRepository;
        $this->historyFactory               = $historyFactory;
        $this->orderSender                  = $orderSender;
        $this->orderFactory                 = $orderFactory;
        $this->cache                        = $cache;
        $this->orderLock                    = $orderLock;
        $this->transactionRetry             = $transactionRetry;

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
            $resultFactory,
            $encryptor,
            $orderStatusHistoryRepository,
            $historyFactory
        );
    }

    /**
     * Notify controller execution
     *
     * @noinspection PhpUndefinedMethodInspection
     */
    public function execute()
    {
        $resultRaw = $this->resultFactory->create(ResultFactory::TYPE_RAW);
        $resultRaw->setHttpResponseCode(200);
        $resultRaw->setContents('OK');

        // Retrieve PayGate data
        $paygate_data = $this->getPostData();
        $status       = isset($paygate_data['TRANSACTION_STATUS']) ? (int)$paygate_data['TRANSACTION_STATUS'] : null;
        $orderId      = $this->request->getParam('eid');
        if ($orderId && $status !== null) {
            // Double-check with cache to prevent race conditions
            $cacheKey       = 'payweb_ipn_processed_' . $orderId;
            $cacheProcessed = $this->cache->load($cacheKey);

            if ($cacheProcessed) {
                $this->_logger->info('OrderID ' . $orderId . ' - ALREADY PROCESSED (CACHE CHECK) - SKIPPING');

                return $resultRaw;
            }

            $order = $this->orderRepository->get($orderId);
            if (!$order || !$order->getId()) {
                $this->_logger->error('Order not found for ID: ' . $orderId);

                return $resultRaw;
            }

            $currentFlag = $order->getData('payweb_payment_processed');

            if ($currentFlag == 1) {
                $this->_logger->info('OrderID ' . $orderId . ' - IPN ORDER ALREADY BEING PROCESSED - SKIPPING');
                $this->cache->save('1', $cacheKey, [], 3600);

                return $resultRaw;
            } else {
                // Set cache immediately to prevent race conditions
                $this->cache->save('1', $cacheKey, [], 3600);

                // Set flag immediately to prevent race conditions
                $order->setData('payweb_payment_processed', 1);
                try {
                    // Use transaction retry helper for order save
                    $this->transactionRetry->retryOrderSave($order, function($reloadedOrder) {
                        $reloadedOrder->setData('payweb_payment_processed', 1);
                    });
                    $order = $this->orderRepository->get($orderId);

                    $this->processOrder($order, $status, $paygate_data);
                } catch (\Exception $e) {
                    $this->_logger->error('OrderID ' . $orderId . ' - Error saving order: ' . $e->getMessage());
                    $this->processOrder($order, $status, $paygate_data);
                }
            }
        }

        return $resultRaw;
    }

    /**
     * Process order
     *
     * @param Order $order
     * @param int $status
     * @param array $paygate_data
     *
     * @return bool
     * @throws LocalizedException
     */
    public function processOrder(Order $order, int $status, array $paygate_data): bool
    {
        $success = false;

        switch ($status) {
            case 1:
                $orderState = $order->getState();
                if ($orderState != Order::STATE_COMPLETE && $orderState != Order::STATE_PROCESSING) {
                    $newOrderStatus = Order::STATE_PROCESSING;
                    $newOrderState  = Order::STATE_PROCESSING;

                    if ($this->getConfigData('successful_order_status') != "") {
                        $newOrderStatus = $this->getConfigData('successful_order_status');
                    }

                    if ($this->getConfigData('successful_order_state') != "") {
                        $newOrderState = $this->getConfigData('successful_order_state');
                    }


                    $invoice = $this->_createAndCaptureInvoice($order);

                    if ($this->_sendInvoiceEmail($invoice, $order)) {
                        // Optionally, handle the comment logging for invoice notification
                    }

                    if ($this->_sendOrderEmail($order)) {
                        // Optionally, handle the comment logging for order notification
                    }

                    $this->createTransaction($order, $paygate_data);
                    $order->setState($newOrderState)->setStatus($newOrderStatus);

                    // Use transaction retry helper for order save
                    $this->transactionRetry->retryOrderSave($order, function($reloadedOrder) use ($newOrderState, $newOrderStatus) {
                        $reloadedOrder->setState($newOrderState)->setStatus($newOrderStatus);
                    });

                    $success = true;
                }
                break;

            // no break
            case 0:
            default:
                $this->_checkoutSession->restoreQuote();
                // Save Transaction Response
                $this->createTransaction($order, $paygate_data);
                $order->cancel();
                $history = $order->addCommentToStatusHistory(
                    __(
                        'Notify Response, update order.'
                    )
                );
                $this->orderStatusHistoryRepository->save($history);

                // Use transaction retry helper for order save
                $this->transactionRetry->retryOrderSave($order);
                break;
        }

        return $success;
    }

    private function _sendOrderEmail(Order $order): bool
    {
        $order_successful_email = $this->_paymentMethod->getConfigData('order_email');
        if ($order_successful_email != '0') {
            // Add status history comment
            $paywebEmailSent = $order->getData('payweb_order_email_sent');
            if (!$order->getEmailSent() && !$paywebEmailSent) {
                try {
                    $this->orderSender->send($order, true);
                } catch (\Throwable $e) {
                    $this->_logger->error(
                        'Order email failed for order #' . $order->getId() . ': ' . $e->getMessage()
                    );
                }

                // Set custom flag to prevent duplicate emails
                $order->setData('payweb_order_email_sent', 1);

                $history = $order->addCommentToStatusHistory(
                    __('Notified customer about order #%1.', $order->getId())
                );
                $history->setIsCustomerNotified(true);

                try {
                    // Save the status history
                    $this->orderStatusHistoryRepository->save($history);

                    // Save the order with retry logic
                    $this->transactionRetry->retryOrderSave($order, function($reloadedOrder) {
                        $reloadedOrder->setData('payweb_order_email_sent', 1);
                    });

                    if ($this->enableLogging === '1') {
                        $this->_logger->info(
                            'Order email sent for order #' . $order->getId() . ' from Notify/Index.php'
                        );
                    }
                } catch (LocalizedException $e) {
                    // Handle any exceptions during the save process
                    $this->_logger->error('Order save error: ' . $e->getMessage());
                }

                return true;
            } else {
                if ($this->enableLogging === '1') {
                    $this->_logger->info(
                        'Order email already sent for order #' . $order->getId(
                        ) . ', skipping in Notify/Index.php (EmailSent: ' . ($order->getEmailSent(
                        ) ? 'YES' : 'NO') . ', PaywebEmailSent: ' . ($paywebEmailSent ? 'YES' : 'NO') . ')'
                    );
                }
            }
        }

        return false;
    }

    private function _createAndCaptureInvoice(Order $order)
    {
        $invoice = $this->_invoiceService->prepareInvoice($order);
        $invoice->setRequestedCaptureCase(Invoice::CAPTURE_ONLINE);
        $invoice->register();

        // Save the invoice to the order
        $transaction = $this->transactionModel
            ->addObject($invoice)
            ->addObject($invoice->getOrder());

        $transaction->save();

        return $invoice;
    }

    private function _sendInvoiceEmail($invoice, Order $order): bool
    {
        $send_invoice_email = $this->_paymentMethod->getConfigData('invoice_email');
        if ($send_invoice_email != '0') {
            $this->invoiceSender->send($invoice);
            // Create a status history comment
            $history = $this->historyFactory->create()
                                            ->setStatus($order->getStatus())
                                            ->setEntityName('order')
                                            ->setComment(__('Notified customer about invoice #%1.', $invoice->getId()))
                                            ->setIsCustomerNotified(true);

            // Add the history to the order
            $order->addStatusHistory($history);

            // Save the order using the repository
            $this->transactionRetry->retryOrderSave($order);

            return true;
        }

        return false;
    }


    // Retrieve post data

    /**
     * Returns processed, validated post data
     *
     * @return bool|array
     */
    public function getPostData(): bool|array
    {
        // Posted variables from ITN
        $nData = $this->request->getPostValue();

        // Strip any slashes in data
        foreach ($nData as $key => $val) {
            $nData[$key] = $val;
        }

        // Return "false" if no data was received
        if (empty($nData) || !isset($nData['CHECKSUM'])) {
            return (false);
        } else {
            return ($nData);
        }
    }

    /**
     * Saves invoice
     *
     * @throws LocalizedException
     */
    protected function saveInvoice()
    {
        // Check for mail msg
        $invoice = $this->_order->prepareInvoice();

        $invoice->register()->capture();

        /**
         * @var Transaction $transaction
         */
        $transaction = $this->_transactionFactory->create();
        $transaction->addObject($invoice)
                    ->addObject($invoice->getOrder())
                    ->save();

        // Add status history comment
        // Create new status history entry
        $statusHistory = $this->historyFactory->create();
        $statusHistory->setOrder($this->_order)
                      ->setStatus($this->_order->getStatus())
                      ->setEntityName(\Magento\Sales\Model\Order::ENTITY)
                      ->setComment(__('Notified customer about invoice #%1.', $invoice->getIncrementId()))
                      ->setIsCustomerNotified(true);

        // Save status history using repository
        $this->orderStatusHistoryRepository->save($statusHistory);

        // Save the order using the repository
        $this->orderRepository->save($this->_order);
    }
}
