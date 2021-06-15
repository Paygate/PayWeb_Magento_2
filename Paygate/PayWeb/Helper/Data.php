<?php
/*
 * Copyright (c) 2021 PayGate (Pty) Ltd
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
use Magento\Quote\Model\Quote;
use Magento\Sales\Api\Data\TransactionSearchResultInterfaceFactory;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Sales\Model\Order\Payment\Transaction\Builder;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Store\Model\Store;
use PayGate\PayWeb\Model\Config as PayGateConfig;
use PayGate\PayWeb\Model\ConfigFactory;
use Psr\Log\LoggerInterface;

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
    protected static $_shouldAskToCreateBillingAgreement = false;

    /**
     * @var \Magento\Payment\Helper\Data
     */
    protected $_paymentData;
    /**
     * @var LoggerInterface
     */
    protected $_logger;
    /**
     * @var Magento\Sales\Model\Order\Payment\Transaction\Builder $_transactionBuilder
     */
    protected $_transactionBuilder;
    /**
     * @var TransactionSearchResultInterfaceFactory
     */
    protected $transactionSearchResultInterfaceFactory;
    /**
     * @var OrderSender
     */
    protected $OrderSender;
    /**
     * @var InvoiceService
     */
    protected $_invoiceService;
    /**
     * @var InvoiceSender
     */
    protected $invoiceSender;
    /**
     * @var DBTransaction
     */
    protected $dbTransaction;
    /**
     * @var array
     */
    private $methodCodes;
    /**
     * @var ConfigFactory
     */
    private $configFactory;
    /**
     * @var ConfigFactory
     */
    private $_paygateconfig;

    /**
     * @param Context $context
     * @param \Magento\Payment\Helper\Data $paymentData
     * @param BaseFactory $configFactory
     * @param array $methodCodes
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
        array $methodCodes
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
    }

    /**
     * Check whether customer should be asked confirmation whether to sign a billing agreement
     * should always return false.
     *
     * @return bool
     */
    public function shouldAskToCreateBillingAgreement()
    {
        $pre = __METHOD__ . " : ";
        $this->_logger->debug($pre . "bof");
        $this->_logger->debug($pre . "eof");

        return self::$_shouldAskToCreateBillingAgreement;
    }

    /**
     * Retrieve available billing agreement methods
     *
     * @param null|string|bool|int|Store $store
     * @param Quote|null $quote
     *
     * @return MethodInterface[]
     */
    public function getBillingAgreementMethods($store = null, $quote = null)
    {
        $pre = __METHOD__ . " : ";
        $this->_logger->debug($pre . 'bof');
        $result = [];
        foreach ($this->_paymentData->getStoreMethods($store, $quote) as $method) {
            if ($method instanceof MethodInterface) {
                $result[] = $method;
            }
        }
        $this->_logger->debug($pre . 'eof | result : ', $result);

        return $result;
    }

    public function getConfigData($field)
    {
        return $this->_paygateconfig->getConfig($field);
    }

    public function getQueryResult($orderquery)
    {
        $config         = $this->_paygateconfig->getApiCredentials();
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
                $pr                  = explode("=", $param);
                $paymentData[$pr[0]] = $pr[1];
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
        $order_successful_email = $this->getConfigData('order_email');

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
        $transaction = $this->dbTransaction
            ->addObject($invoice)
            ->addObject($invoice->getOrder());

        $transaction->save();

        // Magento\Sales\Model\Order\Email\Sender\InvoiceSender
        $send_invoice_email = $this->getConfigData('invoice_email');
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

            return $transaction->save()->getTransactionId();
        } catch (Exception $e) {
            $this->_logger->error($e->getMessage());
        }
    }

}
