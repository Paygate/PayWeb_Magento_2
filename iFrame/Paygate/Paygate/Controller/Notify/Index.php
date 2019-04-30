<?php
/*
 * Copyright (c) 2019 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 * 
 * Released under the GNU General Public License
 */
namespace Paygate\Paygate\Controller\Notify;

class Index extends \Paygate\Paygate\Controller\AbstractPaygate
{
    private $storeId;

    /**
     * indexAction
     *
     */
    public function execute()
    {
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
                $notify_data[$key] = stripslashes( $val );

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
            // Load order

            $orderId       = $paygate_data['REFERENCE'];
            $this->_order  = $this->_orderFactory->create()->loadByIncrementId( $orderId );
            $this->storeId = $this->_order->getStoreId();

            $status = $paygate_data['TRANSACTION_STATUS'];

            // Update order additional payment information

            if ( $status == 1 ) {
                $this->_order->setStatus( \Magento\Sales\Model\Order::STATE_PROCESSING );
                $this->_order->save();
                $this->_order->addStatusHistoryComment( "Notify Response, Transaction has been approved, TransactionID: " . $paygate_data['TRANSACTION_ID'], \Magento\Sales\Model\Order::STATE_PROCESSING )->setIsCustomerNotified( false )->save();
            } elseif ( $status == 2 ) {
                $this->_order->setStatus( \Magento\Sales\Model\Order::STATE_CANCELED );
                $this->_order->save();
                $this->_order->addStatusHistoryComment( "Notify Response, The User Failed to make Payment with PayGate due to transaction being declined, TransactionID: " . $paygate_data['TRANSACTION_ID'], \Magento\Sales\Model\Order::STATE_PROCESSING )->setIsCustomerNotified( false )->save();
            } elseif ( $status == 0 || $status == 4 ) {
                $this->_order->setStatus( \Magento\Sales\Model\Order::STATE_CANCELED );
                $this->_order->save();
                $this->_order->addStatusHistoryComment( "Notify Response, The User Cancelled Payment with PayGate, PayRequestID: " . $paygate_data['PAY_REQUEST_ID'], \Magento\Sales\Model\Order::STATE_CANCELED )->setIsCustomerNotified( false )->save();
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
        if ( sizeof( $nData ) == 0 ) {
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
