<?php
/*
 * Copyright (c) 2020 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

namespace Paygate\Paygate\Controller\Redirect;

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
        try {
            // Get the user session
            $this->_order = $this->_checkoutSession->getLastRealOrder();

            // Get order from POST if not in session
            if ( empty( $this->_order->getId() ) && isset( $_GET['gid'] ) ) {
                $orderId      = filter_var( $_GET['gid'], FILTER_SANITIZE_STRING );
                $this->_order = $this->_orderFactory->create()->loadByIncrementId( $orderId );
                $this->_checkoutSession->setLastOrderId( $this->_order->getId() );
                $this->_checkoutSession->setLastRealOrderId( $orderId );
            }

            $baseurl      = $this->_storeManager->getStore()->getBaseUrl();
            if ( isset( $_POST['TRANSACTION_STATUS'] ) ) {
                $status = $_POST['TRANSACTION_STATUS'];

                switch ( $status ) {
                    case 1:
                        $status = \Magento\Sales\Model\Order::STATE_PROCESSING;
                        if ( $this->getConfigData( 'Successful_Order_status' ) != "" ) {
                            $status = $this->getConfigData( 'Successful_Order_status' );
                        }
                        $message = __(
                            'Redirect Response, Transaction has been approved: PAY_REQUEST_ID: "%1"',
                            $_POST['PAY_REQUEST_ID']
                        );
                        $this->_order->setStatus( $status ); //configure the status
                        $this->_order->setState( $status )->save(); //try and configure the status
                        $this->_order->save();
                        $order = $this->_order;

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

                        // Invoice capture code completed
                        echo '<script>parent.location="' . $baseurl . 'checkout/onepage/success";</script>';
                        exit;
                        break;
                    case 2:
                        $this->messageManager->addNotice( 'Transaction has been declined.' );
                        $this->_order->addStatusHistoryComment( __( 'Redirect Response, Transaction has been declined, Pay_Request_Id: ' . $_POST['PAY_REQUEST_ID'] ) )->setIsCustomerNotified( false );
                        $this->_order->cancel()->save();
                        $this->_checkoutSession->restoreQuote();
                        echo '<script>window.top.location.href="' . $baseurl . 'checkout/cart/";</script>';
                        exit;
                        break;
                    case 0:
                    case 4:
                        $this->messageManager->addNotice( 'Transaction has been cancelled' );
                        $this->_order->addStatusHistoryComment( __( 'Redirect Response, Transaction has been cancelled, Pay_Request_Id: ' . $_POST['PAY_REQUEST_ID'] ) )->setIsCustomerNotified( false );
                        $this->_order->cancel()->save();
                        $this->_checkoutSession->restoreQuote();
                        echo '<script>window.top.location.href="' . $baseurl . 'checkout/cart/";</script>';
                        exit;
                        break;
                    default:
                        break;
                }
            }
        } catch ( \Magento\Framework\Exception\LocalizedException $e ) {
            $this->_logger->error( $pre . $e->getMessage() );
            $this->messageManager->addExceptionMessage( $e, $e->getMessage() );
            echo '<script>window.top.location.href="' . $baseurl . 'checkout/cart/";</script>';
        } catch ( \Exception $e ) {
            $this->_logger->error( $pre . $e->getMessage() );
            $this->messageManager->addExceptionMessage( $e, __( 'We can\'t start PayGate Checkout.' ) );
            echo '<script>window.top.location.href="' . $baseurl . 'checkout/cart/";</script>';
        }

        return '';
    }
}
