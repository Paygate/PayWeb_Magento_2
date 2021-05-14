<?php
/*
 * Copyright (c) 2021 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */
namespace PayGate\PayWeb\Helper;


/**
 * PayGate Data helper
 */
class Data extends \Magento\Framework\App\Helper\AbstractHelper
{
    /**
     * Cache for shouldAskToCreateBillingAgreement()
     *
     * @var bool
     */
    protected static $_shouldAskToCreateBillingAgreement = false;

    /**
     * @var \Magento\Payment\Helper\Data
     */
    protected $_paymentData;

    /**
     * @var array
     */
    private $methodCodes;

    /**
     * @var \PayGate\PayWeb\Model\ConfigFactory
     */
    private $configFactory;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $_logger;
	
	/**
     * @var Magento\Sales\Model\Order\Payment\Transaction\Builder $_transactionBuilder
     */
    protected $_transactionBuilder;

    /**
     * @param \Magento\Framework\App\Helper\Context $context
     * @param \Magento\Payment\Helper\Data $paymentData
     * @param \Magento\Framework\App\Config\BaseFactory $configFactory
     * @param array $methodCodes
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\App\Config\BaseFactory $configFactory,
		\Magento\Sales\Model\Order\Payment\Transaction\Builder $_transactionBuilder,
        array $methodCodes
    ) {
        $this->_logger = $context->getLogger();

        $pre = __METHOD__ . " : ";
        $this->_logger->debug( $pre . 'bof, methodCodes is : ', $methodCodes );

        $this->_paymentData  = $paymentData;
        $this->methodCodes   = $methodCodes;
        $this->configFactory = $configFactory;

        parent::__construct( $context );
        $this->_logger->debug( $pre . 'eof' );
		$this->_transactionBuilder     = $_transactionBuilder;
    }

    /**
     * Check whether customer should be asked confirmation whether to sign a billing agreement
     * should always return false.
     *
     * @return bool
     */
    public function shouldAskToCreateBillingAgreement()
    {
        $pre = __METHOD__ . " : ";
        $this->_logger->debug( $pre . "bof" );
        $this->_logger->debug( $pre . "eof" );

        return self::$_shouldAskToCreateBillingAgreement;
    }

    /**
     * Retrieve available billing agreement methods
     *
     * @param null|string|bool|int|\Magento\Store\Model\Store $store
     * @param \Magento\Quote\Model\Quote|null $quote
     *
     * @return MethodInterface[]
     */
    public function getBillingAgreementMethods( $store = null, $quote = null )
    {
        $pre = __METHOD__ . " : ";
        $this->_logger->debug( $pre . 'bof' );
        $result = [];
        foreach ( $this->_paymentData->getStoreMethods( $store, $quote ) as $method ) {
            if ( $method instanceof MethodInterface ) {
                $result[] = $method;
            }
        }
        $this->_logger->debug( $pre . 'eof | result : ', $result );

        return $result;
    }
	
	public function createTransaction( $order = null, $paymentData = array() )
    {
        try {
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
                ->setTransactionId( $paymentData['PAY_REQUEST_ID'] )
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
