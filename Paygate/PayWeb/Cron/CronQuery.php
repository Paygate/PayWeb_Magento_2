<?php
/*
 * Copyright (c) 2021 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */
namespace PayGate\PayWeb\Cron;

use PayGate\PayWeb\Controller\Cron\Index as CronIndex;

class CronQuery extends CronIndex
{
    /**
     * @var \Magento\Sales\Api\OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var \Magento\Framework\Api\SearchCriteriaBuilder
     */
    protected $searchCriteriaBuilder;

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

}
