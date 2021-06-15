<?php
/*
 * Copyright (c) 2021 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

namespace PayGate\PayWeb\Controller\Redirect;

use Exception;
use Magento\Checkout\Model\Session;
use Magento\Customer\Model\Url;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DB\Transaction;
use Magento\Framework\DB\TransactionFactory;
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

/**
 * Responsible for loading page content.
 *
 * This is a basic controller that only loads the corresponding layout file. It may duplicate other such
 * controllers, and thus it is considered tech debt. This code duplication will be resolved in future releases.
 */
class Success extends AbstractPaygate
{
    /**
     * @var Transaction
     */
    private $transactionModel;
    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfigInterface;
    /**
     * @var Order
     */
    private $orderModel;

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
        Order $orderModel,
        ScopeConfigInterface $scopeConfigInterface,
        Transaction $transactionModel
    ) {
        $this->orderModel           = $orderModel;
        $this->scopeConfigInterface = $scopeConfigInterface;
        $this->transactionModel     = $transactionModel;
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
     * Execute on paygate/redirect/success
     */
    public function execute()
    {
        $pre = __METHOD__ . " : ";
        $this->_logger->debug($pre . 'bof');
        $data         = $this->getRequest()->getPostValue();
        $this->_order = $this->_checkoutSession->getLastRealOrder();
        $order        = $this->_order;
        if ( ! $this->_order->getId()) {
            $this->setlastOrderDetails();
            $order = $this->_order;
        }
        try {
            $this->Notify($data);
        } catch (Exception $ex) {
            $this->_logger->error($ex->getMessage());
        }

        $this->pageFactory->create();
        $baseurl              = $this->_storeManager->getStore()->getBaseUrl();
        $redirectToCartScript = '<script>window.top.location.href="' . $baseurl . 'checkout/cart/";</script>';
        try {
            if ( ! $this->_order->getId()) {
                // Redirect to Cart if Order not found
                echo $redirectToCartScript;
                exit;
            }

            $order = $this->orderRepository->get($order->getId());

            $test_mode     = $this->scopeConfigInterface->getValue('payment/paygate/test_mode');
            $paygateId     = '10011072130';
            $encryptionKey = 'secret';

            if ($test_mode != '1') {
                $paygateId     = $this->scopeConfigInterface->getValue('payment/paygate/paygate_id');
                $encryptionKey = $this->scopeConfigInterface->getValue('payment/paygate/encryption_key');
            }

            $pay_request_id        = $data['PAY_REQUEST_ID'];
            $status                = isset($data['TRANSACTION_STATUS']) ? $data['TRANSACTION_STATUS'] : "";
            $reference             = $order->getIncrementId();
            $checksum              = isset($data['CHECKSUM']) ? $data['CHECKSUM'] : "";
            $data['PAYMENT_TITLE'] = "PAYGATE_PAYWEB";

            $checksum_source = $paygateId . $pay_request_id . $status . $reference . $encryptionKey;
            $test_checksum   = md5($checksum_source);

            $validateChecksum = hash_equals($checksum, $test_checksum);

            if (isset($data['TRANSACTION_STATUS']) && $validateChecksum) {
                $status = $data['TRANSACTION_STATUS'];
                switch ($status) {
                    case 1:
                        // Check if order process by IPN or Redirect
                        if ($this->_paymentMethod->getConfigData('ipn_method') != '0') {
                            $status = Order::STATE_PROCESSING;
                            if ($this->getConfigData('Successful_Order_status') != "") {
                                $status = $this->getConfigData('Successful_Order_status');
                            }

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

                            // Save Transaction Response
                            $this->createTransaction($order, $data);
                            $order->setState($status)->setStatus($status)->save();
                        }

                        // Invoice capture code completed
                        echo '<script>window.top.location.href="' . $baseurl . 'checkout/onepage/success/";</script>';
                        exit;
                        break;
                    case 2:
                        // Save Transaction Response
                        $this->messageManager->addNotice('Transaction has been declined.');
                        $this->_checkoutSession->restoreQuote();
                        if ($this->_paymentMethod->getConfigData('ipn_method') != '0') {
                            $this->createTransaction($order, $data);
                            $this->_order->cancel()->save();
                        }
                        echo $redirectToCartScript;
                        exit;
                        break;
                    case 0:
                    case 4:
                        $this->messageManager->addNotice('Transaction has been cancelled');
                        $this->_checkoutSession->restoreQuote();
                        if ($this->_paymentMethod->getConfigData('ipn_method') != '0') {
                            $this->createTransaction($order, $data);
                            $this->_order->cancel()->save();
                        }
                        echo $redirectToCartScript;
                        exit;
                        break;
                }
            } else {
                $this->messageManager->addNotice('Transaction has been declined');
                $this->_checkoutSession->restoreQuote();
                if ($this->_paymentMethod->getConfigData('ipn_method') != '0') {
                    $this->createTransaction($order, $data);
                    $order->setState(Order::STATE_PENDING_PAYMENT)->setStatus(
                        'pending_payment'
                    )->save();
                }
                echo $redirectToCartScript;
            }
        } catch (Exception $e) {
            // Save Transaction Response
            $this->createTransaction($order, $data);
            $this->_logger->error($pre . $e->getMessage());
            $this->messageManager->addExceptionMessage($e, __('We can\'t start PayGate Checkout.'));
            echo $redirectToCartScript;
        }

        return '';
    }

    public function Notify($data)
    {
        $response         = array();
        $order            = $this->_order;
        $orderIncrementId = $order->getIncrementId();

        // If NOT test mode, use normal credentials
        $test_mode = $this->scopeConfigInterface->getValue('payment/paygate/test_mode');

        if ($test_mode != '1') {
            $paygateId     = $this->scopeConfigInterface->getValue('payment/paygate/paygate_id');
            $encryptionKey = $this->scopeConfigInterface->getValue('payment/paygate/encryption_key');
        } else {
            $paygateId     = '10011072130';
            $encryptionKey = 'secret';
        }

        $data = array(
            'PAYGATE_ID'     => $paygateId,
            'PAY_REQUEST_ID' => $data['PAY_REQUEST_ID'],
            'REFERENCE'      => $orderIncrementId,
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
        curl_setopt($ch, CURLOPT_REFERER, $_SERVER['HTTP_HOST']);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fieldsString);

        // Execute post
        $result = curl_exec($ch);
        parse_str($result, $response);

        if (isset($response['VAULT_ID'])) {
            $model = $this->_paymentMethod;
            $model->saveVaultData($order, $response);
        }

        // Close connection
        curl_close($ch);
    }

    public function getOrderByIncrementId($incrementId)
    {
        return $this->orderModel->loadByIncrementId($incrementId);
    }

    public function setlastOrderDetails()
    {
        $orderId      = $this->getRequest()->getParam('gid');
        $this->_order = $this->getOrderByIncrementId($orderId);
        $order        = $this->_order;
        $this->_checkoutSession->setData('last_order_id', $order->getId());
        $this->_checkoutSession->setData('last_success_quote_id', $order->getQuoteId());
        $this->_checkoutSession->setData('last_quote_id', $order->getQuoteId());
        $this->_checkoutSession->setData('last_real_order_id', $orderId);
        $_SESSION['customer_base']['customer_id']           = $order->getCustomerId();
        $_SESSION['default']['visitor_data']['customer_id'] = $order->getCustomerId();
        $_SESSION['customer_base']['customer_id']           = $order->getCustomerId();
    }
}
