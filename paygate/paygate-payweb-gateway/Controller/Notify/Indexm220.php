<?php
/** @noinspection PhpUndefinedNamespaceInspection */

/** @noinspection PhpUnused */

/*
 * Copyright (c) 2022 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

namespace PayGate\PayWeb\Controller\Notify;

use Magento\Checkout\Model\Session;
use Magento\Customer\Model\Url;
use Magento\Framework\App\Action\Context;
use Magento\Framework\DB\Transaction;
use Magento\Framework\DB\TransactionFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Session\Generic;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\Url\Helper\Data;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Result\PageFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
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
use PayGate\PayWeb\Model\PayGate;
use Psr\Log\LoggerInterface;

class Indexm220 extends AbstractPaygate
{
    /**
     * @var Transaction
     */
    private Transaction $transactionModel;

    /**
     * indexAction
     *
     */

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
        Builder $_transactionBuilder,
        Transaction $transactionModel
    ) {
        $this->transactionModel = $transactionModel;
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

    /**
     * @noinspection PhpUndefinedMethodInspection
     */
    public function execute()
    {
        echo "OK";
        ob_start();
        $pre = __METHOD__ . " : ";
        $this->_logger->debug($pre . 'bof');
        // PayGate API expects response of 'OK' for Notify function

        $errors = false;

        $notify_data = array();
        // Get notify data
        $paygate_data = $this->getPostData();
        if ($paygate_data === false) {
            $errors = true;
        }

        // Verify security signature
        $checkSumParams = '';
        if ( ! $errors) {
            foreach ($paygate_data as $key => $val) {
                $notify_data[$key] = $val;

                if ($key == 'PAYGATE_ID') {
                    $checkSumParams .= $val;
                    continue;
                }

                if ($key === 'AUTH_CODE') {
                    if ($val === 'null') {
                        $checkSumParams .= '';
                    } else {
                        $checkSumParams .= $val;
                    }
                    continue;
                }

                /** @noinspection PhpConditionAlreadyCheckedInspection */
                if ($key != 'CHECKSUM' && $key != 'PAYGATE_ID' && $key !== 'AUTH_CODE') {
                    $checkSumParams .= $val;
                }

                if (empty($notify_data)) {
                    $errors = true;
                }
            }
            if ($this->getConfigData('test_mode') != '0') {
                $encryption_key = 'secret';
            } else {
                $encryption_key = $this->getConfigData('encryption_key');
            }
            $checkSumParams .= $encryption_key;
        }

        // Verify security signature
        if ( ! $errors) {
            $checkSumParams = md5($checkSumParams);
            if ($checkSumParams != $notify_data['CHECKSUM']) {
                $errors = true;
            }
        }

        $paygate_data['PAYMENT_TITLE'] = "PAYGATE_PAYWEB";

        if ( ! $errors && isset($paygate_data['TRANSACTION_STATUS']) && $this->_paymentMethod->getConfigData(
                'ipn_method'
            ) == '0') {
            // Prepare PayGate Data
            $status = filter_var($paygate_data['TRANSACTION_STATUS'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

            $orderId = $this->getRequest()->getParam('eid');
            $order   = $this->orderRepository->get($orderId);

            if ($order->getPaywebPaymentProcessed() == 1) {
                $this->_logger->debug('IPN ORDER ALREADY BEING PROCESSED');
            } else {
                $order->setPaywebPaymentProcessed(1)->save();
                switch ($status) {
                    case 1:
                        $orderState = $order->getState();
                        if ($orderState != Order::STATE_COMPLETE && $orderState != Order::STATE_PROCESSING) {
                            $status = Order::STATE_PROCESSING;
                            $state  = Order::STATE_PROCESSING;

                            if ($this->getConfigData('Successful_Order_status') != "") {
                                $status = $this->getConfigData('Successful_Order_status');
                            }

                            if ($this->getConfigData('Successful_Order_state') != '') {
                                $state = $this->getConfigData('Successful_Order_state');
                            }

                            $model                  = $this->_paymentMethod;
                            $order_successful_email = $model->getConfigData('order_email');

                            if ($order_successful_email != '0') {
                                $this->OrderSender->send($order);
                                $order->addStatusHistoryComment(
                                    __('Notified customer about order #%1.', $order->getId())
                                )->setIsCustomerNotified(true)->save();
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
                            $send_invoice_email = $model->getConfigData('invoice_email');
                            if ($send_invoice_email != '0') {
                                $this->invoiceSender->send($invoice);
                                $order->addStatusHistoryComment(
                                    __('Notified customer about invoice #%1.', $invoice->getId())
                                )->setIsCustomerNotified(true)->save();
                            }

                            // Save Transaction Response
                            $this->createTransaction($order, $paygate_data);
                            $order->setState($state)->setStatus($status)->save();
                        }

                        exit;
                    case 0:
                    default:
                        // Save Transaction Response
                        $this->createTransaction($order, $paygate_data);
                        $order->cancel()->save();
                        exit;
                }
            }
        } else {
            $this->_logger->debug('IPN NOT START');
        }
        $this->_logger->debug($pre . 'eof');
    }

    // Retrieve post data
    public function getPostData(): bool|array
    {
        // Posted variables from ITN
        $nData = $_POST;

        // Strip any slashes in data
        foreach ($nData as $key => $val) {
            $nData[$key] = stripslashes($val);
        }

        // Return "false" if no data was received
        if (empty($nData) || ! isset($nData['CHECKSUM'])) {
            return (false);
        } else {
            return ($nData);
        }
    }

    /**
     * saveInvoice
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

        $this->_order->addStatusHistoryComment(__('Notified customer about invoice #%1.', $invoice->getIncrementId()));
        $this->_order->setIsCustomerNotified(true);
        $this->_order->save();
    }

}
