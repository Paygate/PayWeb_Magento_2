<?php
/** @noinspection PhpUndefinedNamespaceInspection */

/** @noinspection PhpUnused */

/**
 * Copyright (c) 2022 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

namespace PayGate\PayWeb\Model;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\UrlInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteFactory;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Api\PaymentTokenManagementInterface;
use Magento\Vault\Api\PaymentTokenRepositoryInterface;
use Magento\Vault\Model\CreditCardTokenFactory;
use PayGate\PayWeb\Api\Data\PayWebApiInterface;
use PayGate\PayWeb\Helper\Data;
use PayGate\PayWeb\Model\PayGate as PayGateModel;

class PayWeb implements PayWebApiInterface
{

    const SECURE = '_secure';

    protected \Magento\Checkout\Model\Cart $cart;

    protected CustomerRepositoryInterface $_customerRepositoryInterface;

    protected QuoteFactory $quoteFactory;

    protected StoreManagerInterface $_storeManager;

    /**
     * @var UrlInterface
     */
    protected UrlInterface $_urlBuilder;

    /**
     * @var FormKey
     */
    protected FormKey $_formKey;

    /**
     * @var PayGateModel
     */
    protected PayGate $_paygatemodel;

    /**
     * @var PayGate\PayWeb\Helper\Data|Data
     */
    protected PayGate\PayWeb\Helper\Data|Data $_PaygateHelper;

    public function __construct(
        \Magento\Checkout\Model\Cart $cart,
        CustomerRepositoryInterface $customerRepositoryInterface,
        QuoteFactory $quoteFactory,
        PayGateModel $paygatemodel,
        UrlInterface $urlBuilder,
        FormKey $formKey,
        Data $PaygateHelper,
        StoreManagerInterface $storeManager
    ) {
        $this->cart                         = $cart;
        $this->_customerRepositoryInterface = $customerRepositoryInterface;
        $this->quoteFactory                 = $quoteFactory;
        $this->_paygatemodel                = $paygatemodel;
        $this->_storeManager                = $storeManager;
        $this->_urlBuilder                  = $urlBuilder;
        $this->_formKey                     = $formKey;
        $this->_PaygateHelper               = $PaygateHelper;
    }

    public function getQuote($quoteId)
    {
        return $this->quoteFactory->create()->load($quoteId);
    }

    /**
     * This is where we compile data posted by the form to PayGate
     *
     * @param $customerId
     * @param $order_id
     *
     * @return array
     * @noinspection PhpUnusedParameterInspection
     */
    public function getStandardCheckoutFormFields($customerId, $order_id): array
    {
        $paygateModel  = $this->_paygatemodel;
        $encryptionKey = $paygateModel->getEncryptionKey();
        $order         = $paygateModel->getOrderbyOrderId($order_id);

        $return_url = "api";
        $fields     = $paygateModel->prepareFields($order, $return_url);

        $fields['CHECKSUM'] = md5(implode('', $fields) . $encryptionKey);


        $response = $paygateModel->curlPost('https://secure.paygate.co.za/payweb3/initiate.trans', $fields);

        parse_str($response, $result);

        $processData = array();
        if (isset($result['ERROR'])) {
            $processData = array(
                'ERROR_CODE' => $result['ERROR'],
            );
        } else {
            $result['PAYMENT_TITLE'] = "PAYGATE_PAYWEB";
            $this->_PaygateHelper->createTransaction($order, $result);
            if ( ! str_contains($response, "ERROR")) {
                $processData = array(
                    'PAY_REQUEST_ID' => $result['PAY_REQUEST_ID'],
                    'CHECKSUM'       => $result['CHECKSUM'],
                );
            }
        }

        return ($processData);
    }

}
