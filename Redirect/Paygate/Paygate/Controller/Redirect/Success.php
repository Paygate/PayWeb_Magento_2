<?php
/*
 * Copyright (c) 2019 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */
namespace Paygate\Paygate\Controller\Redirect;

require_once __DIR__ . '/../AbstractPaygate.php';

/**
 * Responsible for loading page content.
 *
 * This is a basic controller that only loads the corresponding layout file. It may duplicate other such
 * controllers, and thus it is considered tech debt. This code duplication will be resolved in future releases.
 */
class Success extends \Paygate\Paygate\Controller\AbstractPaygate
{
    /**
     * @var \Magento\Framework\View\Result\PageFactory
     */
    protected $resultPageFactory;
    protected $_messageManager;

    /**
     * Execute
     */
    public function execute()
    {

        $pre = __METHOD__ . " : ";
        $this->_logger->debug( $pre . 'bof' );
        $page_object = $this->pageFactory->create();
        try
        {
            // Get the user session
            $this->_order = $this->_checkoutSession->getLastRealOrder();

            // Get the user session
            $this->_order = $this->_checkoutSession->getLastRealOrder();

            $paygate_data = $this->getPostData();

            if ( isset( $paygate_data['TRANSACTION_STATUS'] ) ) {
                $status       = filter_var( $paygate_data['TRANSACTION_STATUS'], FILTER_SANITIZE_STRING );
                $payRequestId = filter_var( $paygate_data['PAY_REQUEST_ID'], FILTER_SANITIZE_STRING );

                switch ( $status ) {
                    case 1:
                        // Check if order process by IPN or Redirect
                        if ( $this->_paymentMethod->getConfigData( 'ipn_method' ) == '0' ) {
                            $status = \Magento\Sales\Model\Order::STATE_PROCESSING;
                            if ( $this->getConfigData( 'Successful_Order_status' ) != "" ) {
                                $status = $this->getConfigData( 'Successful_Order_status' );
                            }
                            $message = __(
                                'Redirect Response, Transaction has been approved: PAY_REQUEST_ID: "%1"',
                                $payRequestId
                            );
                            $this->_order->setStatus( $status ); //configure the status
                            $this->_order->setState( $status )->save(); //try and configure the status
                            $this->_order->save();
                            $order = $this->_order;

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
                        }

                        // Invoice capture code completed
                        $this->_redirect( 'checkout/onepage/success' );
                        break;
                    case 2:
                        $this->messageManager->addNotice( 'Transaction has been declined.' );
                        if ( $this->_paymentMethod->getConfigData( 'ipn_method' ) == '0' ) {
                            $this->_order->addStatusHistoryComment( __( 'Redirect Response, Transaction has been declined, Pay_Request_Id: ' . $payRequestId ) )->setIsCustomerNotified( false );
                        }
                        $this->_order->cancel()->save();
                        $this->_checkoutSession->restoreQuote();
                        $this->_redirect( 'checkout/cart' );
                        break;
                    case 0:
                    case 4:
                        $this->messageManager->addNotice( 'Transaction has been cancelled' );
                        if ( $this->_paymentMethod->getConfigData( 'ipn_method' ) == '0' ) {
                            $this->_order->addStatusHistoryComment( __( 'Redirect Response, Transaction has been cancelled, Pay_Request_Id: ' . $payRequestId ) )->setIsCustomerNotified( false );
                        }
                        $this->_order->cancel()->save();
                        $this->_checkoutSession->restoreQuote();
                        $this->_redirect( 'checkout/cart' );
                        break;
                    default:
                        break;
                }
            }
        } catch ( \Magento\Framework\Exception\LocalizedException $e ) {
            $this->_logger->error( $pre . $e->getMessage() );
            $this->messageManager->addExceptionMessage( $e, $e->getMessage() );
            $this->_redirect( 'checkout/cart' );
        } catch ( \Exception $e ) {
            $this->_logger->error( $pre . $e->getMessage() );
            $this->messageManager->addExceptionMessage( $e, __( 'We can\'t start PayGate Checkout.' ) );
            $this->_redirect( 'checkout/cart' );
        }

        return '';
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
}
