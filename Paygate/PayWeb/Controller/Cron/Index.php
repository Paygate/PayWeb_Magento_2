<?php
/*
 * Copyright (c) 2021 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

namespace PayGate\PayWeb\Controller\Cron;

use DateInterval;
use DateTime;
use Exception;
use Magento\Checkout\Model\Session;
use Magento\Customer\Model\Url;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\DB\TransactionFactory;
use Magento\Framework\Session\Generic;
use Magento\Framework\Url\Helper\Data;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Result\PageFactory;
use Magento\Sales\Api\Data\TransactionSearchResultInterfaceFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Sales\Model\Order\Payment\Transaction\Builder;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Store\Model\StoreManagerInterface;
use PayGate\PayWeb\Controller\AbstractPaygate;
use PayGate\PayWeb\Model\Config as PayGateConfig;
use PayGate\PayWeb\Model\PayGate;
use Psr\Log\LoggerInterface;

/**
 * Responsible for loading page content.
 *
 * This is a basic controller that only loads the corresponding layout file. It may duplicate other such
 * controllers, and thus it is considered tech debt. This code duplication will be resolved in future releases.
 */
class Index extends AbstractPaygate
{
    /**
     * @var TransactionSearchResultInterfaceFactory
     */
    protected $transactionSearchResultInterfaceFactory;
    /**
     * @var Area
     */
    private $state;
    /**
     * @var \Magento\Framework\DB\Transaction
     */
    private $transactionModel;
    /**
     * @var PayGateConfig
     */
    private $paygateConfig;

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
        \Magento\Framework\Stdlib\DateTime\DateTime $date,
        CollectionFactory $orderCollectionFactory,
        Builder $_transactionBuilder,
        TransactionSearchResultInterfaceFactory $transactionSearchResultInterfaceFactory,
        PayGateConfig $paygateConfig,
        \Magento\Framework\DB\Transaction $transactionModel,
        State $state
    ) {
        $this->state                                   = $state;
        $this->transactionModel                        = $transactionModel;
        $this->paygateConfig                           = $paygateConfig;
        $this->transactionSearchResultInterfaceFactory = $transactionSearchResultInterfaceFactory;
        parent::__construct(
            $context,
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
            $OrderSender,
            $date,
            $orderCollectionFactory,
            $_transactionBuilder
        );
    }

    public function execute()
    {
        $this->state->emulateAreaCode(
            Area::AREA_FRONTEND,
            function () {
                $cutoffTime = (new DateTime())->sub(new DateInterval('PT10M'))->format('Y-m-d H:i:s');
                $this->_logger->info('Cutoff: ' . $cutoffTime);
                $ocf = $this->_orderCollectionFactory->create();
                $ocf->addAttributeToSelect('entity_id');
                $ocf->addAttributeToFilter('status', ['eq' => 'pending_payment']);
                $ocf->addAttributeToFilter('created_at', ['lt' => $cutoffTime]);
                $ocf->addAttributeToFilter('updated_at', ['lt' => $cutoffTime]);
                $orderIds = $ocf->getData();

                $this->_logger->info('Orders for cron: ' . json_encode($orderIds));

                foreach ($orderIds as $orderId) {
                    $order_id                = $orderId['entity_id'];
                    $transactionSearchResult = $this->transactionSearchResultInterfaceFactory;
                    $transaction             = $transactionSearchResult->create()->addOrderIdFilter(
                        $order_id
                    )->getFirstItem();

                    $transactionId   = $transaction->getData('txn_id');
                    $order           = $this->orderRepository->get($orderId['entity_id']);
                    $PaymentTitle    = $order->getPayment()->getMethodInstance()->getTitle();
                    $transactionData = $transaction->getData();
                    if (isset($transactionData['additional_information']['raw_details_info'])) {
                        $add_info = $transactionData['additional_information']['raw_details_info'];
                        if (isset($add_info['PAYMENT_TITLE'])) {
                            $PaymentTitle = $add_info['PAYMENT_TITLE'];
                        }
                    }

                    if ( ! empty($transactionId) & $PaymentTitle == "PAYGATE_PAYWEB") {
                        $orderquery['orderId']        = $order->getRealOrderId();
                        $orderquery['country']        = $order->getBillingAddress()->getCountryId();
                        $orderquery['currency']       = $order->getOrderCurrencyCode();
                        $orderquery['amount']         = $order->getGrandTotal();
                        $orderquery['reference']      = $order->getRealOrderId();
                        $orderquery['transaction_id'] = $transactionId;

                        $result = explode("&", $this->getQueryResult($orderquery));

                        $result['PAYMENT_TITLE'] = "PAYGATE_PAYWEB";
                        $this->updatePaymentStatus($order, $result);
                    }
                }
            }
        );
    }

    public function getQueryResult($orderquery)
    {
        $config         = $this->paygateConfig->getApiCredentials();
        $encryption_key = $config['encryption_key'];
        $paygate_id     = $config['paygate_id'];

        // Encryption key set in the Merchant Access Portal
        $encryptionKey  = "$encryption_key";
        $reference      = $orderquery['reference'];
        $transaction_id = $orderquery['transaction_id'];
        $data           = array(
            'PAYGATE_ID'     => $paygate_id,
            'PAY_REQUEST_ID' => "$transaction_id",
            'REFERENCE'      => "$reference",
        );

        $checksum = md5(implode('', $data) . $encryptionKey);

        $data['CHECKSUM'] = $checksum;

        $fieldsString = http_build_query($data);

        // Open connection
        $ch = curl_init();

        // Set the url, number of POST vars, POST data
        curl_setopt($ch, CURLOPT_URL, 'https://secure.paygate.co.za/payweb3/query.trans');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_NOBODY, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fieldsString);

        // Execute post
        $result = curl_exec($ch);

        // Close connection
        curl_close($ch);

        return $result;
    }

    public function updatePaymentStatus($order, $resp)
    {
        if (is_array($resp) && count($resp) > 0) {
            $paymentData = array();
            foreach ($resp as $param) {
                $pr = explode("=", $param);
                if (isset($pr[0]) && isset($pr[1])) {
                    $paymentData[$pr[0]] = $pr[1];
                }
            }
            if (isset($paymentData['ERROR'])) {
                $status = Order::STATE_CANCELED;
                $order->setStatus($status);
                $order->setState($status);
                $order->save();

                return false;
            }

            if ($paymentData['TRANSACTION_STATUS'] == 1) {
                $status = Order::STATE_PROCESSING;
                $order->setStatus($status);
                $order->setState($status);
                $order->save();
                try {
                    $this->generateInvoice($order);
                } catch (Exception $ex) {
                    $this->_logger->error($ex->getMessage());
                }
            } else {
                $status = Order::STATE_CANCELED;
                $order->setStatus($status);
                $order->setState($status);
                $order->save();
            }
        }
    }

    public function generateInvoice($order)
    {
        $model                  = $this->_paymentMethod;
        $order_successful_email = $model->getConfigData('order_email');

        if ($order_successful_email != '0') {
            $this->OrderSender->send($order);
            $order->addStatusHistoryComment(
                __('Notified customer about order #%1.', $order->getId())
            )->setIsCustomerNotified(true)->save();
        }

        // Capture invoice when payment is successfull
        $invoice = $this->_invoiceService->prepareInvoice($order);
        $invoice->setRequestedCaptureCase(Invoice::CAPTURE_ONLINE);
        $invoice->register();

        // Save the invoice to the order
        $transaction = $this->transactionModel
            ->addObject($invoice)
            ->addObject($invoice->getOrder());

        $transaction->save();

        // Magento\Sales\Model\Order\Email\Sender\InvoiceSender
        $send_invoice_email = $model->getConfigData('invoice_email');
        if ($send_invoice_email != '0') {
            $this->invoiceSender->send($invoice);
            $order->addStatusHistoryComment(
                __('Notified customer about invoice #%1.', $invoice->getId())
            )->setIsCustomerNotified(true)->save();
        }
    }

    public function createTransaction($order = null, $paymentData = array())
    {
        try {
            if ($paymentData['TRANSACTION_STATUS'] !== 1) {
                return false;
            }
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

            $message = __('The authorized amount is %1.', $formatedPrice);
            // Get the object of builder class
            $trans       = $this->_transactionBuilder;
            $transaction = $trans->setPayment($payment)
                                 ->setOrder($order)
                                 ->setTransactionId($paymentData['TRANSACTION_ID'])
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

            return $transaction->save()->getTransactionId();
        } catch (Exception $e) {
            $this->_logger->error($e->getMessage());
        }
    }

}
