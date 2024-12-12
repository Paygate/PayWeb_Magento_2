<?php

/**
 * @noinspection PhpUndefinedNamespaceInspection
 */

/**
 * @noinspection PhpUnused
 */

/**
 * Copyright (c) 2024 Payfast (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

namespace PayGate\PayWeb\Model;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Magento\Quote\Model\QuoteFactory;
use Magento\Store\Model\StoreManagerInterface;
use PayGate\PayWeb\Api\Data\PayWebApiInterface;
use PayGate\PayWeb\Helper\Data;
use PayGate\PayWeb\Model\PayGate as PayGateModel;
use Laminas\Uri\Uri;
use Magento\Quote\Api\CartRepositoryInterface;

class PayWeb implements PayWebApiInterface
{
    public const SECURE = '_secure';

    /**
     * @var CartRepositoryInterface
     */
    protected CartRepositoryInterface $quoteRepository;

    /**
     * @var CustomerRepositoryInterface
     */
    protected CustomerRepositoryInterface $_customerRepositoryInterface;

    /**
     * @var QuoteFactory
     */
    protected QuoteFactory $quoteFactory;

    /**
     * @var StoreManagerInterface
     */
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
    protected PayGateOrig $_paygatemodel;

    /**
     * @var PayGate\PayWeb\Helper\Data|Data
     */
    protected PayGate\PayWeb\Helper\Data|Data $_PaygateHelper;
    /**
     * @var Uri
     */
    private Uri $uriHandler;

    /**
     * Builds PayWeb Object
     *
     * @param CartRepositoryInterface $quoteRepository
     * @param CustomerRepositoryInterface $customerRepositoryInterface
     * @param QuoteFactory $quoteFactory
     * @param PayGate $paygatemodel
     * @param UrlInterface $urlBuilder
     * @param FormKey $formKey
     * @param Data $PaygateHelper
     * @param StoreManagerInterface $storeManager
     * @param Uri $uriHandler
     */
    public function __construct(
        CartRepositoryInterface $quoteRepository,
        CustomerRepositoryInterface $customerRepositoryInterface,
        QuoteFactory $quoteFactory,
        PayGateModel $paygatemodel,
        UrlInterface $urlBuilder,
        FormKey $formKey,
        Data $PaygateHelper,
        StoreManagerInterface $storeManager,
        Uri $uriHandler
    ) {
        $this->quoteRepository              = $quoteRepository;
        $this->_customerRepositoryInterface = $customerRepositoryInterface;
        $this->quoteFactory                 = $quoteFactory;
        $this->_paygatemodel                = $paygatemodel;
        $this->_storeManager                = $storeManager;
        $this->_urlBuilder                  = $urlBuilder;
        $this->_formKey                     = $formKey;
        $this->_PaygateHelper               = $PaygateHelper;
        $this->uriHandler                   = $uriHandler;
    }

    /**
     * Cart quote
     *
     * @param int $quoteId
     *
     * @return \Magento\Quote\Api\Data\CartInterface
     * @throws NoSuchEntityException
     */
    public function getQuote(int $quoteId): \Magento\Quote\Api\Data\CartInterface
    {
        return $this->quoteRepository->get($quoteId);
    }

    /**
     * This is where we compile data posted by the form to Paygate
     *
     * @param int $customerId
     * @param int $order_id
     *
     * @return array
     * @noinspection PhpUnusedParameterInspection
     */
    public function getStandardCheckoutFormFields(int $customerId, int $order_id): array
    {
        $paygateModel  = $this->_paygatemodel;
        $encryptionKey = $paygateModel->getEncryptionKey();
        $order         = $paygateModel->getOrderByOrderId($order_id);

        $return_url = "api";
        $fields     = $paygateModel->prepareFields($order, $return_url);

        //@codingStandardsIgnoreStart
        $fields['CHECKSUM'] = md5(implode('', $fields) . $encryptionKey);
        //@codingStandardsIgnoreEnd

        $response = $paygateModel->guzzlePost('https://secure.paygate.co.za/payweb3/initiate.trans', $fields);

        $this->uriHandler->setQuery($response);

        $result = $this->uriHandler->getQueryAsArray();

        $processData = [];
        if (isset($result['ERROR'])) {
            $processData = [
                'ERROR_CODE' => $result['ERROR'],
            ];
        } else {
            $result['PAYMENT_TITLE'] = "PAYGATE_PAYWEB";
            $this->_PaygateHelper->createTransaction($order, $result);
            if (!str_contains($response, "ERROR")) {
                $processData = [
                    'PAY_REQUEST_ID' => $result['PAY_REQUEST_ID'],
                    'CHECKSUM'       => $result['CHECKSUM'],
                ];
            }
        }

        return ($processData);
    }
}
