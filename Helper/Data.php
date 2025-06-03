<?php

/**
 * @noinspection PhpUndefinedNamespaceInspection
 */

/**
 * @noinspection PhpUnused
 */
/**
 * @noinspection PhpPropertyOnlyWrittenInspection
 */

/**
 * Copyright (c) 2025 Payfast (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

namespace PayGate\PayWeb\Helper;

use Exception;
use Magento\Framework\App\Config\BaseFactory;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\DB\Transaction as DBTransaction;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\Data\TransactionSearchResultInterfaceFactory;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Sales\Model\Order\Payment\Transaction\Builder;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Quote\Api\Data\CartInterface;
use PayGate\PayWeb\Model\Config as PayGateConfig;
use PayGate\PayWeb\Model\ConfigFactory;
use Psr\Log\LoggerInterface;
use GuzzleHttp\Client;
use Magento\Payment\Api\PaymentMethodListInterface;
use Magento\Sales\Api\OrderItemRepositoryInterface;
use Magento\Sales\Api\OrderStatusHistoryRepositoryInterface;
use Magento\Sales\Api\Data\OrderStatusHistoryInterfaceFactory;

/**
 * PayGate Data helper
 */
class Data extends AbstractHelper
{
    /**
     * Cache for shouldAskToCreateBillingAgreement()
     *
     * @var bool
     */
    protected static bool $_shouldAskToCreateBillingAgreement = false;

    /**
     * @var \Magento\Payment\Helper\Data
     */
    protected \Magento\Payment\Helper\Data $_paymentData;
    /**
     * @var LoggerInterface
     */
    protected $_logger;
    /**
     * @var Magento\Sales\Model\Order\Payment\Transaction\Builder|Builder $_transactionBuilder
     */
    protected Magento\Sales\Model\Order\Payment\Transaction\Builder|Builder $_transactionBuilder;
    /**
     * @var TransactionSearchResultInterfaceFactory
     */
    protected TransactionSearchResultInterfaceFactory $transactionSearchResultInterfaceFactory;
    /**
     * @var OrderSender
     */
    protected OrderSender $OrderSender;
    /**
     * @var InvoiceService
     */
    protected InvoiceService $_invoiceService;
    /**
     * @var InvoiceSender
     */
    protected InvoiceSender $invoiceSender;
    /**
     * @var DBTransaction
     */
    protected DBTransaction $dbTransaction;
    /**
     * @var array
     */
    private array $methodCodes;
    /**
     * @var BaseFactory|ConfigFactory
     */
    private ConfigFactory|BaseFactory $configFactory;
    /**
     * @var ConfigFactory|PayGateConfig
     */
    private ConfigFactory|PayGateConfig $_paygateconfig;
    /**
     * @var Client
     */
    protected Client $httpClient;
    protected PaymentMethodListInterface $paymentMethodList;
    private OrderRepositoryInterface $orderRepository;
    /**
     * @var OrderItemRepositoryInterface
     */
    private OrderItemRepositoryInterface $orderItemRepository;
    /**
     * @var OrderStatusHistoryRepositoryInterface
     */
    private OrderStatusHistoryRepositoryInterface $orderStatusHistoryRepository;
    /**
     * @var OrderStatusHistoryInterfaceFactory
     */
    private OrderStatusHistoryInterfaceFactory $historyFactory;

    /**
     * @param Context $context
     * @param \Magento\Payment\Helper\Data $paymentData
     * @param BaseFactory $configFactory
     * @param Builder $_transactionBuilder
     * @param PayGateConfig $paygateconfig
     * @param TransactionSearchResultInterfaceFactory $transactionSearchResultInterfaceFactory
     * @param OrderSender $OrderSender
     * @param DBTransaction $dbTransaction
     * @param InvoiceService $invoiceService
     * @param InvoiceSender $invoiceSender
     * @param array $methodCodes
     * @param Client $httpClient
     * @param PaymentMethodListInterface $paymentMethodList
     * @param OrderRepositoryInterface $orderRepository
     * @param OrderItemRepositoryInterface $orderItemRepository
     * @param OrderStatusHistoryRepositoryInterface $orderStatusHistoryRepository
     * @param OrderStatusHistoryInterfaceFactory $historyFactory
     */
    public function __construct(
        Context $context,
        \Magento\Payment\Helper\Data $paymentData,
        BaseFactory $configFactory,
        Builder $_transactionBuilder,
        PayGateConfig $paygateconfig,
        TransactionSearchResultInterfaceFactory $transactionSearchResultInterfaceFactory,
        OrderSender $OrderSender,
        DBTransaction $dbTransaction,
        InvoiceService $invoiceService,
        InvoiceSender $invoiceSender,
        array $methodCodes,
        Client $httpClient,
        PaymentMethodListInterface $paymentMethodList,
        OrderRepositoryInterface $orderRepository,
        OrderItemRepositoryInterface $orderItemRepository,
        OrderStatusHistoryRepositoryInterface $orderStatusHistoryRepository,
        OrderStatusHistoryInterfaceFactory $historyFactory
    ) {
        $this->_logger = $context->getLogger();

        $pre = __METHOD__ . " : ";
        $this->_logger->debug($pre . 'bof, methodCodes is : ', $methodCodes);

        $this->_paymentData   = $paymentData;
        $this->methodCodes    = $methodCodes;
        $this->configFactory  = $configFactory;
        $this->_paygateconfig = $paygateconfig;

        parent::__construct($context);
        $this->_logger->debug($pre . 'eof');
        $this->_transactionBuilder = $_transactionBuilder;

        $this->transactionSearchResultInterfaceFactory = $transactionSearchResultInterfaceFactory;
        $this->OrderSender                             = $OrderSender;
        $this->_invoiceService                         = $invoiceService;
        $this->invoiceSender                           = $invoiceSender;
        $this->dbTransaction                           = $dbTransaction;
        $this->httpClient                              = $httpClient;
        $this->paymentMethodList                       = $paymentMethodList;
        $this->orderRepository                         = $orderRepository;
        $this->orderItemRepository                     = $orderItemRepository;
        $this->orderStatusHistoryRepository            = $orderStatusHistoryRepository;
        $this->historyFactory                          = $historyFactory;
    }

    /**
     * Whether customer should be asked to sign billing agreement
     *
     * @return bool
     */
    public function shouldAskToCreateBillingAgreement(): bool
    {
        $pre = __METHOD__ . " : ";
        $this->_logger->debug($pre . "bof");
        $this->_logger->debug($pre . "eof");

        return self::$_shouldAskToCreateBillingAgreement;
    }

    /**
     * Retrieve available billing agreement methods
     *
     * @param CartInterface $quote
     *
     * @return MethodInterface[]
     */
    public function getBillingAgreementMethods(CartInterface $quote): array
    {
        $pre = __METHOD__ . " : ";
        $this->_logger->debug($pre . 'bof');
        $result           = [];
        $availableMethods = $this->paymentMethodList->getActiveList($quote->getId());

        foreach ($availableMethods as $method) {
            if ($method instanceof MethodInterface) {
                $result[] = $method;
            }
        }
        $this->_logger->debug($pre . 'eof | result : ', $result);

        return $result;
    }

    /**
     * Gets config data for field
     *
     * @param string $field
     *
     * @return mixed
     */
    public function getConfigData(string $field): mixed
    {
        return $this->_paygateconfig->getConfig($field);
    }

    /**
     * Fetch Paygate transaction
     *
     * @param array $orderQuery
     *
     * @return bool|string
     * @noinspection PhpUndefinedMethodInspection
     */
    public function getQueryResult(array $orderQuery): bool|string
    {
        $store_id = $orderQuery['store_id'] ?? "";

        $encryption_key = $this->_paygateconfig->getEncryptionKey($store_id);
        $paygate_id     = $this->_paygateconfig->getPaygateId($store_id);

        // Encryption key set in the Merchant Access Portal
        $encryptionKey  = "$encryption_key";
        $reference      = $orderQuery['reference'];
        $transaction_id = $orderQuery['transaction_id'];
        $data           = [
            'PAYGATE_ID'     => $paygate_id,
            'PAY_REQUEST_ID' => "$transaction_id",
            'REFERENCE'      => "$reference",
        ];

        $enableLogging = $this->_paygateconfig->getEnableLogging();

        //@codingStandardsIgnoreStart
        $checksum = md5(implode('', $data) . $encryptionKey);
        //@codingStandardsIgnoreEnd

        $data['CHECKSUM'] = $checksum;

        $fieldsString = http_build_query($data);
        $response     = $this->httpClient->post('https://secure.paygate.co.za/payweb3/query.trans', [
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'body'    => $fieldsString,
        ]);

        // Execute post
        $responseBody = $response->getBody()->getContents();

        if ($enableLogging) {
            $this->_logger->info('Fetch Transaction Data: ' . json_encode($data));
            $this->_logger->info('Fetch Transaction Result: ' . json_encode($responseBody));
        }

        return $responseBody;
    }

    /**
     * Update order payment status
     *
     * @param Order $order
     * @param array $resp
     *
     * @return bool
     * @throws Exception
     */
    public function updatePaymentStatus(Order $order, array $resp): bool
    {
        $response = false;

        if (!empty($resp)) {
            $paymentData = [];
            foreach ($resp as $param) {
                $pr = explode("=", $param);
                if (isset($pr[1])) {
                    $paymentData[$pr[0]] = $pr[1];
                } else {
                    $this->_logger->error("Empty Response " . json_encode($param));
                }
            }
            if (isset($paymentData['ERROR'])) {
                $status = Order::STATE_CANCELED;
                $order->setStatus($status);
                $order->setState($status);
                $this->orderRepository->save($order);

                return false;
            }

            if ($paymentData['TRANSACTION_STATUS'] == 1) {
                try {
                    $status_canceled = Order::STATE_CANCELED;
                    if ($order->getStatus() == $status_canceled) {
                        $this->restoreOrder($order);
                    }

                    $status = Order::STATE_COMPLETE;

                    if ($order->getInvoiceCollection()->count() <= 0) {
                        $status = Order::STATE_PROCESSING;
                        $this->generateInvoice($order);
                    }

                    $order->setStatus($status);
                    $order->setState($status);
                    $this->orderRepository->save($order);

                    $response = true;
                } catch (Exception $ex) {
                    $this->_logger->error($ex->getMessage());
                }
            } else {
                $status = Order::STATE_CANCELED;
                $order->setStatus($status);
                $order->setState($status);
                $this->orderRepository->save($order);
            }
        }

        return $response;
    }

    /**
     * Restores the order
     *
     * @param Order $order
     *
     * @return void
     * @throws Exception
     */
    public function restoreOrder(Order $order): void
    {
        $orderItems = $order->getAllItems();
        foreach ($orderItems as $item) {
            $item->setData("qty_canceled", 0);
            $this->orderItemRepository->save($item);
        }
    }

    /**
     * Generates the invoice
     *
     * @param Order $order
     *
     * @return void
     * @throws LocalizedException
     */
    public function generateInvoice(Order $order): void
    {
        $this->_sendOrderEmail($order);

        // Capture invoice when payment is successful
        $this->_createAndCaptureInvoice($order);
    }

    private function _sendOrderEmail(Order $order): void
    {
        $order_successful_email = $this->getConfigData('order_email');

        if ($order_successful_email != '0') {
            $this->OrderSender->send($order);
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
    }

    private function _createAndCaptureInvoice(Order $order): void
    {
        $invoice = $this->_invoiceService->prepareInvoice($order);
        $invoice->setRequestedCaptureCase(Invoice::CAPTURE_ONLINE);
        $invoice->register();

        // Save the invoice to the order
        $transaction = $this->dbTransaction
            ->addObject($invoice)
            ->addObject($invoice->getOrder());

        $transaction->save();

        // Magento\Sales\Model\Order\Email\Sender\InvoiceSender
        $send_invoice_email = $this->getConfigData('invoice_email');
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
            $this->orderRepository->save($order);
        }
    }

    /**
     * Creates the transaction
     *
     * @param Order|null $order
     * @param array $paymentData
     *
     * @return string
     */
    public function createTransaction(Order $order = null, array $paymentData = []): string
    {
        $response = '';
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

            $message = __('The authorized amount is %1.', $formattedPrice);
            // Get the object of builder class
            $trans       = $this->_transactionBuilder;
            $transaction = $trans->setPayment($payment)
                                 ->setOrder($order)
                                 ->setTransactionId($paymentData['PAY_REQUEST_ID'])
                                 ->setAdditionalInformation(
                                     [Transaction::RAW_DETAILS => $paymentData]
                                 )
                                 ->setFailSafe(true)
                // Build method creates the transaction and returns the object
                                 ->build(Transaction::TYPE_CAPTURE);

            $payment->addTransactionCommentsToOrder(
                $transaction,
                $message
            );
            $payment->setParentTransactionId(null);
            $this->orderRepository->save($order);

            $response = $transaction->getTransactionId();
        } catch (Exception $e) {
            $this->_logger->error($e->getMessage());
        }

        return $response;
    }
}
