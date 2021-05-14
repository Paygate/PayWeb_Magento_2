<?php
/*
 * Copyright (c) 2021 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

namespace PayGate\PayWeb\Controller\Notify;

use PayGate\PayWeb\Controller\AbstractPaygate;
use PayGate\PayWeb\Model\PayGate;

class Indexm220 extends AbstractPaygate
{
    /**
     * @var \Magento\Framework\DB\Transaction
     */
    private $transactionModel;

    /**
     * indexAction
     *
     */

    public function __construct(\Magento\Framework\App\Action\Context $context, \Magento\Framework\View\Result\PageFactory $pageFactory, \Magento\Customer\Model\Session $customerSession, \Magento\Checkout\Model\Session $checkoutSession, \Magento\Sales\Model\OrderFactory $orderFactory, \Magento\Framework\Session\Generic $paygateSession, \Magento\Framework\Url\Helper\Data $urlHelper, \Magento\Customer\Model\Url $customerUrl, \Psr\Log\LoggerInterface $logger, \Magento\Framework\DB\TransactionFactory $transactionFactory, \Magento\Sales\Model\Service\InvoiceService $invoiceService, \Magento\Sales\Model\Order\Email\Sender\InvoiceSender $invoiceSender, PayGate $paymentMethod, \Magento\Framework\UrlInterface $urlBuilder, \Magento\Sales\Api\OrderRepositoryInterface $orderRepository, \Magento\Store\Model\StoreManagerInterface $storeManager, \Magento\Sales\Model\Order\Email\Sender\OrderSender $OrderSender, \Magento\Framework\Stdlib\DateTime\DateTime $date, \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollectionFactory, \Magento\Sales\Model\Order\Payment\Transaction\Builder $_transactionBuilder,\Magento\Framework\DB\Transaction $transactionModel)
    {
        $this->transactionModel = $transactionModel;
        parent::__construct($context, $pageFactory, $customerSession, $checkoutSession, $orderFactory, $paygateSession, $urlHelper, $customerUrl, $logger, $transactionFactory, $invoiceService, $invoiceSender, $paymentMethod, $urlBuilder, $orderRepository, $storeManager, $OrderSender, $date, $orderCollectionFactory, $_transactionBuilder);
    }

    public function execute()
    {
        echo "OK";
        ob_start();
        $pre = __METHOD__ . " : ";
        $this->_logger->debug( $pre . 'bof' );
        // PayGate API expects response of 'OK' for Notify function

        $errors       = false;
        $paygate_data = array();

        $notify_data = array();
        // Get notify data
        if ( !$errors ) {
            $paygate_data = $this->getPostData();
            if ( $paygate_data === false ) {
                $errors = true;
            }
        }

        // Verify security signature
        $checkSumParams = '';
        if ( !$errors ) {

            foreach ( $paygate_data as $key => $val ) {
                $notify_data[$key] = $val;

                if ( $key == 'PAYGATE_ID' ) {
                    $checkSumParams .= $val;
                    continue;
                }

                if($key === 'AUTH_CODE') {
                    if($val === 'null') {
                        $checkSumParams .= '';
                    } else {
                        $checkSumParams .= $val;
                    }
                    continue;
                }

                if ( $key != 'CHECKSUM' && $key != 'PAYGATE_ID' && $key !== 'AUTH_CODE' ) {
                    $checkSumParams .= $val;
                }

                if ( empty( $notify_data ) ) {
                    $errors = true;
                }
            }
            if ( $this->getConfigData( 'test_mode' ) != '0' ) {
                $encryption_key = 'secret';
            } else {
                $encryption_key = $this->getConfigData( 'encryption_key' );
            }
            $checkSumParams .= $encryption_key;
        }

        // Verify security signature
        if ( !$errors ) {
            $checkSumParams = md5( $checkSumParams );
            if ( $checkSumParams != $notify_data['CHECKSUM'] ) {
                $errors = true;
            }
        }
        if ( !$errors && isset( $paygate_data['TRANSACTION_STATUS'] ) && $this->_paymentMethod->getConfigData( 'ipn_method' ) == '0' ) {

            // Prepare PayGate Data
            $status       = filter_var( $paygate_data['TRANSACTION_STATUS'], FILTER_SANITIZE_STRING );
            $reference    = filter_var( $paygate_data['REFERENCE'], FILTER_SANITIZE_STRING );

            $order = $this->orderRepository->get( $reference );
            switch ( $status ) {
                case 1:
                    $orderState = $order->getState();
                    if ($orderState != \Magento\Sales\Model\Order::STATE_COMPLETE && $orderState != \Magento\Sales\Model\Order::STATE_PROCESSING) {
                        $status = \Magento\Sales\Model\Order::STATE_PROCESSING;
                        if ( $this->getConfigData( 'Successful_Order_status' ) != "" ) {
                            $status = $this->getConfigData( 'Successful_Order_status' );
                        }

                        $model                  = $this->_paymentMethod;
                        $order_successful_email = $model->getConfigData( 'order_email' );

                        if ( $order_successful_email != '0' ) {
                            $this->OrderSender->send( $order );
                            $order->addStatusHistoryComment( __( 'Notified customer about order #%1.', $order->getId() ) )->setIsCustomerNotified( true )->save();
                        }

                        // Capture invoice when payment is successfull
                        $invoice = $this->_invoiceService->prepareInvoice( $order );
                        $invoice->setRequestedCaptureCase( \Magento\Sales\Model\Order\Invoice::CAPTURE_ONLINE );
                        $invoice->register();

                        // Save the invoice to the order
                        $transaction = $this->transactionModel
                            ->addObject( $invoice )
                            ->addObject( $invoice->getOrder() );

                        $transaction->save();

                        // Magento\Sales\Model\Order\Email\Sender\InvoiceSender
                        $send_invoice_email = $model->getConfigData( 'invoice_email' );
                        if ( $send_invoice_email != '0' ) {
                            $this->invoiceSender->send( $invoice );
                            $order->addStatusHistoryComment( __( 'Notified customer about invoice #%1.', $invoice->getId() ) )->setIsCustomerNotified( true )->save();
                        }

                        // Save Transaction Response
                        $this->createTransaction( $order, $paygate_data );
                        $order->setState( $status )->setStatus( $status )->save();
                    }

                    exit;
                    break;
                case 2:
                case 0:
                case 4:
                default:
                    // Save Transaction Response
                    $this->createTransaction( $order, $paygate_data );
                    $order->cancel()->save();
                    exit;
                    break;
            }
        } else {
            $this->_logger->debug( 'IPN NOT START' );
        }
        $this->_logger->debug( $pre . 'eof' );
        ob_end_clean();
    }

    // Retrieve post data
    public function getPostData()
    {
        // Posted variables from ITN
        $nData = $_POST;

        // Strip any slashes in data
        foreach ( $nData as $key => $val ) {
            $nData[$key] = stripslashes( $val );
        }

        // Return "false" if no data was received
        if ( empty( $nData ) || !isset( $nData['CHECKSUM'] ) ) {
            return ( false );
        } else {
            return ( $nData );
        }

    }

    /**
     * saveInvoice
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function saveInvoice()
    {
        // Check for mail msg
        $invoice = $this->_order->prepareInvoice();

        $invoice->register()->capture();

        /**
         * @var \Magento\Framework\DB\Transaction $transaction
         */
        $transaction = $this->_transactionFactory->create();
        $transaction->addObject( $invoice )
            ->addObject( $invoice->getOrder() )
            ->save();

        $this->_order->addStatusHistoryComment( __( 'Notified customer about invoice #%1.', $invoice->getIncrementId() ) );
        $this->_order->setIsCustomerNotified( true );
        $this->_order->save();
    }

}
