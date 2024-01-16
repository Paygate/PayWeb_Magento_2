<?php
/**
 * @noinspection PhpUndefinedNamespaceInspection
 */

/**
 * @noinspection PhpUnused
 */

/**
 * @noinspection PhpPropertyOnlyWrittenInspection
 */

/**
 * Copyright (c) 2024 Payfast (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

namespace PayGate\PayWeb\Cron;

use DateInterval;
use DateTime;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\DB\Transaction;
use Magento\Sales\Api\Data\TransactionSearchResultInterfaceFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use PayGate\PayWeb\Helper\Data as PaygateHelper;
use PayGate\PayWeb\Model\Config as PayGateConfig;
use PayGate\PayWeb\Model\PayGate;
use Psr\Log\LoggerInterface;

class CronQuery
{
    /**
     * @var TransactionSearchResultInterfaceFactory
     */
    protected TransactionSearchResultInterfaceFactory $transactionSearchResultInterfaceFactory;
    /**
     * @var LoggerInterface
     */
    protected LoggerInterface $_logger;
    /**
     * @var PayGate $_paymentMethod
     */
    protected PayGate $_paymentMethod;
    /**
     * @var CollectionFactory $_orderCollectionFactory
     */
    protected CollectionFactory $_orderCollectionFactory;
    /**
     * @var OrderRepositoryInterface $orderRepository
     */
    protected OrderRepositoryInterface $orderRepository;
    /**
     * @var Area|State
     */
    private State|Area $state;
    /**
     * @var \Magento\Framework\DB\Transaction
     */
    private \Magento\Framework\DB\Transaction $transactionModel;
    /**
     * @var PayGateConfig
     */
    private PayGateConfig $paygateConfig;
    /**
     * @var PaygateHelper
     */
    private PaygateHelper $paygateHelper;

    /**
     * @param PayGate $paymentMethod
     * @param TransactionSearchResultInterfaceFactory $transactionSearchResultInterfaceFactory
     * @param PayGateConfig $paygateConfig
     * @param Transaction $transactionModel
     * @param State $state
     * @param PaygateHelper $paygateHelper
     * @param CollectionFactory $orderCollectionFactory
     * @param OrderRepositoryInterface $orderRepository
     * @param LoggerInterface $logger
     */
    public function __construct(
        PayGate $paymentMethod,
        TransactionSearchResultInterfaceFactory $transactionSearchResultInterfaceFactory,
        PayGateConfig $paygateConfig,
        Transaction $transactionModel,
        State $state,
        PaygateHelper $paygateHelper,
        CollectionFactory $orderCollectionFactory,
        OrderRepositoryInterface $orderRepository,
        LoggerInterface $logger
    ) {
        $pre = __METHOD__ . " : ";

        $this->_logger = $logger;

        $this->_logger->debug($pre . 'bof');

        $this->_paymentMethod                          = $paymentMethod;
        $this->paygateHelper                           = $paygateHelper;
        $this->state                                   = $state;
        $this->transactionModel                        = $transactionModel;
        $this->paygateConfig                           = $paygateConfig;
        $this->transactionSearchResultInterfaceFactory = $transactionSearchResultInterfaceFactory;
        $this->_orderCollectionFactory                 = $orderCollectionFactory;
        $this->orderRepository                         = $orderRepository;
    }

    /**
     * Cron query Controller execution
     *
     * @return void
     * @throws \Exception
     */
    public function execute()
    {
        $this->state->emulateAreaCode(
            Area::AREA_FRONTEND,
            function () {
                $cutoffTime = (new DateTime())->sub(new DateInterval('PT10M'))->format('Y-m-d H:i:s');
                $this->_logger->info('Cutoff: ' . $cutoffTime);
                $ocf = $this->_orderCollectionFactory->create();
                $ocf->addAttributeToSelect('entity_id');
                $ocf->addAttributeToFilter('status', ['eq' => 'pending_payment']);
                $ocf->addAttributeToFilter('created_at', ['lt' => $cutoffTime]);
                $ocf->addAttributeToFilter('updated_at', ['lt' => $cutoffTime]);
                $orderIds = $ocf->getData();

                $this->_logger->info('Orders for cron: ' . json_encode($orderIds));

                foreach ($orderIds as $orderId) {
                    $order_id                = $orderId['entity_id'];
                    $transactionSearchResult = $this->transactionSearchResultInterfaceFactory;
                    $transaction             = $transactionSearchResult->create()->addOrderIdFilter(
                        $order_id
                    )->getFirstItem();

                    $transactionId   = $transaction->getData('txn_id');
                    $order           = $this->orderRepository->get($orderId['entity_id']);
                    //Get Payment
                    $payment = $order->getPayment();
                    $paymentMethodType = $payment
                        ->getAdditionalInformation()['raw_details_info']["PAYMENT_METHOD_TYPE"] ?? "0";
                    $PaymentTitle    = $order->getPayment()->getMethodInstance()->getTitle();
                    $transactionData = $transaction->getData();
                    if (isset($transactionData['additional_information']['raw_details_info'])) {
                        $add_info = $transactionData['additional_information']['raw_details_info'];
                        if (isset($add_info['PAYMENT_TITLE'])) {
                            $PaymentTitle = $add_info['PAYMENT_TITLE'];
                        }
                    }

                    if (! empty($transactionId) & $PaymentTitle == "PAYGATE_PAYWEB") {
                        $orderquery['orderId']        = $order->getRealOrderId();
                        $orderquery['country']        = $order->getBillingAddress()->getCountryId();
                        $orderquery['currency']       = $order->getOrderCurrencyCode();
                        $orderquery['amount']         = $order->getGrandTotal();
                        $orderquery['reference']      = $order->getRealOrderId();
                        $orderquery['transaction_id'] = $transactionId;

                        $result = explode("&", $this->paygateHelper->getQueryResult($orderquery));

                        $result['PAYMENT_TITLE'] = "PAYGATE_PAYWEB";
                        $result["PAYMENT_METHOD_TYPE"] = $paymentMethodType;
                        $this->paygateHelper->updatePaymentStatus($order, $result);
                    }
                }
            }
        );
    }
}
