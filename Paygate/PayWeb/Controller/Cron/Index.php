<?php
/*
 * Copyright (c) 2021 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

namespace PayGate\PayWeb\Controller\Cron;

use PayGate\PayWeb\Model\Config as PayGateConfig;
use PayGate\PayWeb\Model\PayGate;

/**
 * Responsible for loading page content.
 *
 * This is a basic controller that only loads the corresponding layout file. It may duplicate other such
 * controllers, and thus it is considered tech debt. This code duplication will be resolved in future releases.
 */
class Index extends \PayGate\PayWeb\Controller\AbstractPaygate
{
    /** 
     * @var \Magento\Framework\App\Area
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
    /**
     * @var \Magento\Sales\Api\Data\TransactionSearchResultInterfaceFactory
     */
    protected $transactionSearchResultInterfaceFactory;

    public function __construct(\Magento\Framework\App\Action\Context $context, \Magento\Framework\View\Result\PageFactory $pageFactory, \Magento\Customer\Model\Session $customerSession, \Magento\Checkout\Model\Session $checkoutSession, \Magento\Sales\Model\OrderFactory $orderFactory, \Magento\Framework\Session\Generic $paygateSession, \Magento\Framework\Url\Helper\Data $urlHelper, \Magento\Customer\Model\Url $customerUrl, \Psr\Log\LoggerInterface $logger, \Magento\Framework\DB\TransactionFactory $transactionFactory, \Magento\Sales\Model\Service\InvoiceService $invoiceService, \Magento\Sales\Model\Order\Email\Sender\InvoiceSender $invoiceSender, PayGate $paymentMethod, \Magento\Framework\UrlInterface $urlBuilder, \Magento\Sales\Api\OrderRepositoryInterface $orderRepository, \Magento\Store\Model\StoreManagerInterface $storeManager, \Magento\Sales\Model\Order\Email\Sender\OrderSender $OrderSender, \Magento\Framework\Stdlib\DateTime\DateTime $date, \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollectionFactory, \Magento\Sales\Model\Order\Payment\Transaction\Builder $_transactionBuilder, \Magento\Sales\Api\Data\TransactionSearchResultInterfaceFactory $transactionSearchResultInterfaceFactory, PayGateConfig $paygateConfig, \Magento\Framework\DB\Transaction $transactionModel, \Magento\Framework\App\State $state)
    {
        $this->state = $state;
        $this->transactionModel = $transactionModel;
        $this->paygateConfig = $paygateConfig;
        $this->transactionSearchResultInterfaceFactory = $transactionSearchResultInterfaceFactory;
        parent::__construct($context, $pageFactory, $customerSession, $checkoutSession, $orderFactory, $paygateSession, $urlHelper, $customerUrl, $logger, $transactionFactory, $invoiceService, $invoiceSender, $paymentMethod, $urlBuilder, $orderRepository, $storeManager, $OrderSender, $date, $orderCollectionFactory, $_transactionBuilder);
    }

    public function execute()
    {
        $this->state->emulateAreaCode(\Magento\Framework\App\Area::AREA_FRONTEND, function () {

            $cutoffTime = ( new \DateTime() )->sub( new \DateInterval( 'PT10M' ) )->format( 'Y-m-d H:i:s' );
            $this->_logger->info( 'Cutoff: ' . $cutoffTime );
            $ocf = $this->_orderCollectionFactory->create();
            $ocf->addAttributeToSelect( 'entity_id' );
            $ocf->addAttributeToFilter( 'status', ['eq' => 'pending_payment'] );
            $ocf->addAttributeToFilter( 'created_at', ['lt' => $cutoffTime] );
            $ocf->addAttributeToFilter( 'updated_at', ['lt' => $cutoffTime] );
            $orderIds = $ocf->getData();

            $this->_logger->info( 'Orders for cron: ' . json_encode( $orderIds ) );

            foreach ( $orderIds as $orderId ) {
                $order_id                = $orderId['entity_id'];
                $transactionSearchResult = $this->transactionSearchResultInterfaceFactory;
                $transaction             = $transactionSearchResult->create()->addOrderIdFilter( $order_id )->getFirstItem();

                $transactionId = $transaction->getData( 'txn_id' );
                $order         = $this->orderRepository->get( $orderId['entity_id'] );
                $PaymentTitle  = $order->getPayment()->getMethodInstance()->getTitle();

                if ( !empty( $transactionId ) & $PaymentTitle == "PayGate PayWeb" ) {
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
        });
    }

    public function getQueryResult( $orderquery )
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

        $checksum = md5( implode( '', $data ) . $encryptionKey );

        $data['CHECKSUM'] = $checksum;

        $fieldsString = http_build_query( $data );

        // Open connection
        $ch = curl_init();

        // Set the url, number of POST vars, POST data
        curl_setopt( $ch, CURLOPT_URL, 'https://secure.paygate.co.za/payweb3/query.trans' );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $ch, CURLOPT_NOBODY, false );
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
			if(isset($paymentData['ERROR'])){
				$status = \Magento\Sales\Model\Order::STATE_CANCELED;
                $order->setStatus( $status );
                $order->setState( $status );
                $order->save();
				return false;
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
