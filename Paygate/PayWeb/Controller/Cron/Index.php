<?php
/*
 * Copyright (c) 2021 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */
namespace PayGate\PayWeb\Controller\Cron;

/**
 * Responsible for loading page content.
 *
 * This is a basic controller that only loads the corresponding layout file. It may duplicate other such
 * controllers, and thus it is considered tech debt. This code duplication will be resolved in future releases.
 */
class Index extends \PayGate\PayWeb\Controller\AbstractPaygate
{

    public function execute()
    {
        $cutoffTime = ( new \DateTime() )->sub( new \DateInterval( 'PT10M' ) )->format( 'Y-m-d H:i:s' );
        $this->_logger->info( 'Cutoff: ' . $cutoffTime );
        $ocf = $this->_orderCollectionFactory->create();
        $ocf->addAttributeToSelect( 'entity_id' );
        $ocf->addAttributeToFilter( 'status', ['eq' => 'pending_payment'] );
        $ocf->addAttributeToFilter( 'created_at', ['lt' => $cutoffTime] );
        $ocf->addAttributeToFilter( 'updated_at', ['lt' => $cutoffTime] );
        $orderIds = $ocf->getData();

        $this->_logger->info( 'Orders for cron: ' . json_encode( $orderIds ) );

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

        foreach ( $orderIds as $orderId ) {
            $order_id                = $orderId['entity_id'];
            $transactionSearchResult = $objectManager->get( '\Magento\Sales\Api\Data\TransactionSearchResultInterfaceFactory' );
            $transaction             = $transactionSearchResult->create()->addOrderIdFilter( $order_id )->getFirstItem();

            $transactionId = $transaction->getData( 'txn_id' );
            $order         = $this->orderRepository->get( $orderId['entity_id'] );
            $PaymentTitle  = $order->getPayment()->getMethodInstance()->getTitle();

            if ( !empty( $transactionId ) & $PaymentTitle == "PayGate" ) {
                $orderquery['orderId']        = $order->getRealOrderId();
                $orderquery['country']        = $order->getBillingAddress()->getCountryId();
                $orderquery['currency']       = $order->getOrderCurrencyCode();
                $orderquery['amount']         = $order->getGrandTotal();
                $orderquery['reference']      = $order->getRealOrderId();
                $orderquery['transaction_id'] = $transactionId;

                $result = explode( "&", $this->getQueryResult( $orderquery ) );
                $this->updatePaymentStatus( $order, $result );
            }

        }
    }

    public function getQueryResult( $orderquery )
    {
        $objectManager  = \Magento\Framework\App\ObjectManager::getInstance();
        $config         = $objectManager->get( "PayGate\PayWeb\Model\Config" )->getApiCredentials();
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

        $checksum = md5( implode( '', $data ) . $encryptionKey );

        $data['CHECKSUM'] = $checksum;

        $fieldsString = http_build_query( $data );

        // Open connection
        $ch = curl_init();

        // Set the url, number of POST vars, POST data
        curl_setopt( $ch, CURLOPT_URL, 'https://secure.paygate.co.za/payweb3/query.trans' );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $ch, CURLOPT_NOBODY, false );
        curl_setopt( $ch, CURLOPT_REFERER, $_SERVER['HTTP_HOST'] );
        curl_setopt( $ch, CURLOPT_POST, true );
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $fieldsString );

        // Execute post
        $result = curl_exec( $ch );

        // Close connection
        curl_close( $ch );

        return $result;
    }

    public function updatePaymentStatus( $order, $resp )
    {

        if ( is_array( $resp ) && count( $resp ) > 0 ) {

            $paymentData = array();
            foreach ( $resp as $param ) {
                $pr                  = explode( "=", $param );
                $paymentData[$pr[0]] = $pr[1];
            }
            if ( $paymentData['TRANSACTION_STATUS'] == 1 ) {
                $status = \Magento\Sales\Model\Order::STATE_PROCESSING;
                $order->setStatus( $status );
                $order->setState( $status );
                $order->save();
                try {
                    $this->generateInvoice( $order );
                } catch ( \Exception $ex ) {

                    $this->_logger->error( $ex->getMessage() );
                }
            } else {
                $status = \Magento\Sales\Model\Order::STATE_CANCELED;
                $order->setStatus( $status );
                $order->setState( $status );
                $order->save();
            }
        }
    }

    public function generateInvoice( $order )
    {
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
        $transaction = $this->_objectManager->create( 'Magento\Framework\DB\Transaction' )
            ->addObject( $invoice )
            ->addObject( $invoice->getOrder() );

        $transaction->save();

        // Magento\Sales\Model\Order\Email\Sender\InvoiceSender
        $send_invoice_email = $model->getConfigData( 'invoice_email' );
        if ( $send_invoice_email != '0' ) {
            $this->invoiceSender->send( $invoice );
            $order->addStatusHistoryComment( __( 'Notified customer about invoice #%1.', $invoice->getId() ) )->setIsCustomerNotified( true )->save();
        }
    }

    public function createTransaction( $order = null, $paymentData = array() )
    {
        try {
            if ( $paymentData['TRANSACTION_STATUS'] !== 1 ) {
                return false;
            }
            // Get payment object from order object
            $payment = $order->getPayment();
            $payment->setLastTransId( $paymentData['PAY_REQUEST_ID'] )
                ->setTransactionId( $paymentData['PAY_REQUEST_ID'] )
                ->setAdditionalInformation( [\Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS => (array) $paymentData] );
            $formatedPrice = $order->getBaseCurrency()->formatTxt(
                $order->getGrandTotal()
            );

            $message = __( 'The authorized amount is %1.', $formatedPrice );
            // Get the object of builder class
            $trans       = $this->_transactionBuilder;
            $transaction = $trans->setPayment( $payment )
                ->setOrder( $order )
                ->setTransactionId( $paymentData['TRANSACTION_ID'] )
                ->setAdditionalInformation(
                    [\Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS => (array) $paymentData]
                )
                ->setFailSafe( true )
            // Build method creates the transaction and returns the object
                ->build( \Magento\Sales\Model\Order\Payment\Transaction::TYPE_CAPTURE );

            $payment->addTransactionCommentsToOrder(
                $transaction,
                $message
            );
            $payment->setParentTransactionId( null );
            $payment->save();
            $order->save();

            return $transaction->save()->getTransactionId();
        } catch ( \Exception $e ) {

            $this->_logger->error( $e->getMessage() );
        }
    }

}
