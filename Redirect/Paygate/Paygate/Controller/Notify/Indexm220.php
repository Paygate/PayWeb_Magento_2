<?php
/*
 * Copyright (c) 2019 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */
namespace Paygate\Paygate\Controller\Notify;

use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;

require_once __DIR__ . '/../AbstractPaygate.php';

class Indexm220 extends \Paygate\Paygate\Controller\AbstractPaygate
{
    private $storeId;

    /**
     * indexAction
     *
     */
    public function execute()
    {
        ob_start();
        // PayGate API expects response of 'OK' for Notify function
        echo "OK";

        $errors       = false;
        $paygate_data = array();

        $notify_data = array();
        $post_data   = '';
        // Get notify data
        if ( !$errors ) {
            $paygate_data = $this->getPostData();
            if ( $paygate_data === false ) {
                $errors = true;
            }
        }

        $mode = $this->getConfigData( 'test_mode' );

        // Verify security signature
        $checkSumParams = '';
        if ( !$errors ) {

            foreach ( $paygate_data as $key => $val ) {
                $post_data .= $key . '=' . $val . "\n";
                $notify_data[$key] = $val;

                if ( $key == 'PAYGATE_ID' ) {
                    $checkSumParams .= $val;
                }
                if ( $key != 'CHECKSUM' && $key != 'PAYGATE_ID' ) {
                    $checkSumParams .= $val;
                }

                if ( sizeof( $notify_data ) == 0 ) {
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

        if ( !$errors ) {
            // Check if order process by IPN or Redirect
            if ( $this->_paymentMethod->getConfigData( 'ipn_method' ) != '0' ) {
                // Prepare PayGate Data
                $status        = filter_var( $paygate_data['TRANSACTION_STATUS'], FILTER_SANITIZE_STRING );
                $reference     = filter_var( $paygate_data['REFERENCE'], FILTER_SANITIZE_STRING );
                $transactionId = filter_var( $paygate_data['TRANSACTION_ID'], FILTER_SANITIZE_STRING );
                $payRequestId  = filter_var( $paygate_data['PAY_REQUEST_ID'], FILTER_SANITIZE_STRING );

                // Load order
                $orderId       = $reference;
                $this->_order  = $this->_orderFactory->create()->loadByIncrementId( $orderId );
                $this->storeId = $this->_order->getStoreId();

                // Update order additional payment information

                if ( $status == 1 ) {
                    $this->_order->setStatus( \Magento\Sales\Model\Order::STATE_PROCESSING );
                    $this->_order->save();
                    $this->_order->addStatusHistoryComment( "Notify Response, Transaction has been approved, TransactionID: " . $transactionId, \Magento\Sales\Model\Order::STATE_PROCESSING )->setIsCustomerNotified( false )->save();

                    $order                  = $this->_order;
                    $order_successful_email = $this->_paymentMethod->getConfigData( 'order_email' );
                    if ( $order_successful_email != '0' ) {
                        $this->OrderSender->send( $order );
                        $order->addStatusHistoryComment( __( 'Notified customer about order #%1.', $order->getId() ) )->setIsCustomerNotified( true )->save();
                    }

                    // Capture invoice when payment is successfull
                    $invoice = $this->_invoiceService->prepareInvoice( $order );
                    $invoice->setRequestedCaptureCase( \Magento\Sales\Model\Order\Invoice::CAPTURE_ONLINE );
                    $invoice->register();

                    // Save the invoice to the order
                    $transaction = $this->_objectManager->create( 'Magento\Framework\DB\Transaction' )
                        ->addObject( $invoice )
                        ->addObject( $invoice->getOrder() );

                    $transaction->save();

                    // Magento\Sales\Model\Order\Email\Sender\InvoiceSender
                    $send_invoice_email = $this->_paymentMethod->getConfigData( 'invoice_email' );
                    if ( $send_invoice_email != '0' ) {
                        $this->invoiceSender->send( $invoice );
                        $order->addStatusHistoryComment( __( 'Notified customer about invoice #%1.', $invoice->getId() ) )->setIsCustomerNotified( true )->save();
                    }
                } elseif ( $status == 2 ) {
                    $this->_order->setStatus( \Magento\Sales\Model\Order::STATE_CANCELED );
                    $this->_order->save();
                    $this->_order->addStatusHistoryComment( "Notify Response, The User Failed to make Payment with PayGate due to transaction being declined, TransactionID: " . $transactionId, \Magento\Sales\Model\Order::STATE_PROCESSING )->setIsCustomerNotified( false )->save();
                } elseif ( $status == 0 || $status == 4 ) {
                    $this->_order->setStatus( \Magento\Sales\Model\Order::STATE_CANCELED );
                    $this->_order->save();
                    $this->_order->addStatusHistoryComment( "Notify Response, The User Cancelled Payment with PayGate, PayRequestID: " . $payRequestId, \Magento\Sales\Model\Order::STATE_CANCELED )->setIsCustomerNotified( false )->save();
                }
            }
        }
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
        if ( sizeof( $nData ) == 0 || !isset( $nData['CHECKSUM'] ) ) {
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
