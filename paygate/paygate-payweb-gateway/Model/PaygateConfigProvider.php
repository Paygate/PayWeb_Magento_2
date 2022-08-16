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

use JetBrains\PhpStorm\ArrayShape;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Customer\Helper\Session\CurrentCustomer;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Asset\Repository;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Payment\Model\Method\AbstractMethod;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use PayGate\PayWeb\Helper\Data as PaygateHelper;
use Psr\Log\LoggerInterface;

class PaygateConfigProvider implements ConfigProviderInterface
{
    /**
     * @var ResolverInterface
     */
    protected ResolverInterface $localeResolver;

    /**
     * @var Config
     */
    protected Config $config;

    /**
     * @var ScopeConfig|ScopeConfigInterface
     */
    protected ScopeConfig|ScopeConfigInterface $scopeConfig;

    /**
     * @var string|path
     */
    protected string|path $path = 'payment/paygate/';

    /**
     * @var StoreManagerInterface
     */
    protected StoreManagerInterface $storeManager;

    /**
     * @var CurrentCustomer
     */
    protected CurrentCustomer $currentCustomer;

    /**
     * @var LoggerInterface
     */
    protected LoggerInterface $_logger;

    /**
     * @var PaygateHelper
     */
    protected PaygateHelper $paygateHelper;

    /**
     * @var string[]
     */
    protected array $methodCodes = [
        Config::METHOD_CODE,
    ];

    /**
     * @var AbstractMethod[]
     */
    protected array $methods = [];

    /**
     * @var PaymentHelper
     */
    protected PaymentHelper $paymentHelper;

    /**
     * @var Repository
     */
    protected Repository $assetRepo;

    /**
     * @var UrlInterface
     */
    protected UrlInterface $urlBuilder;

    /**
     * @var RequestInterface
     */
    protected RequestInterface $request;

    public function __construct(
        LoggerInterface $logger,
        ConfigFactory $configFactory,
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager,
        ResolverInterface $localeResolver,
        CurrentCustomer $currentCustomer,
        PaygateHelper $paygateHelper,
        PaymentHelper $paymentHelper,
        Repository $assetRepo,
        UrlInterface $urlBuilder,
        RequestInterface $request
    ) {
        $this->_logger = $logger;
        $pre           = __METHOD__ . ' : ';
        $this->_logger->debug($pre . 'bof');

        $this->localeResolver  = $localeResolver;
        $this->config          = $configFactory->create();
        $this->scopeConfig     = $scopeConfig;
        $this->storeManager    = $storeManager;
        $this->currentCustomer = $currentCustomer;
        $this->paygateHelper   = $paygateHelper;
        $this->paymentHelper   = $paymentHelper;
        $this->assetRepo       = $assetRepo;
        $this->urlBuilder      = $urlBuilder;
        $this->request         = $request;

        foreach ($this->methodCodes as $code) {
            $this->methods[$code] = $this->paymentHelper->getMethodInstance($code);
        }

        $this->_logger->debug($pre . 'eof and this  methods has : ', $this->methods);
    }

    /**
     * {@inheritdoc}
     */
    #[ArrayShape(['payment' => "array[]"])] public function getConfig(): array
    {
        $pre                    = __METHOD__ . ' : ';
        $om                     = ObjectManager::getInstance();
        $customerSession        = $om->create('Magento\Customer\Model\Session');
        $paymentTokenManagement = $om->create('Magento\Vault\Api\PaymentTokenManagementInterface');
        $cards                  = array();
        $cardCount              = 0;
        if ($customerSession->isLoggedIn()) {
            $customerId = $customerSession->getCustomer()->getId();
            $cardList   = $paymentTokenManagement->getListByCustomerId($customerId);
            foreach ($cardList as $card) {
                if ($card['is_active'] == 1 && $card['is_visible'] == 1) {
                    $cardDetails = json_decode($card['details']);
                    $cards[]     = array(
                        'masked_cc' => $cardDetails->maskedCC,
                        'token'     => $card['public_hash'],
                        'card_type' => $cardDetails->type,
                    );
                    $cardCount++;
                }
            }
            $isVault = $this->config->isVault();
        } else {
            $isVault = 0;
        }

        $this->_logger->debug($pre . 'bof');
        $dbConfig = [
            'payment' => [
                'paygate' => [
                    'paymentAcceptanceMarkSrc'  => $this->config->getPaymentMarkImageUrl(),
                    'paymentAcceptanceMarkHref' => $this->config->getPaymentMarkWhatIsPaygate(),
                    'isVault'                   => $isVault,
                    'paymentTypes'              => $this->config->getPaymentTypes(),
                    'paymentTypeList'           => $this->getPaymentTypes(),
                    'saved_card_data'           => json_encode($cards),
                    'card_count'                => $cardCount,
                    'enablePaymentTypes'        => $this->config->isEnabledPaymentTypes(),
                ],
            ],
        ];

        foreach ($this->methodCodes as $code) {
            if ($this->methods[$code]->isAvailable()) {
                $dbConfig['payment']['paygate']['redirectUrl'][$code]          = $this->getMethodRedirectUrl($code);
                $dbConfig['payment']['paygate']['billingAgreementCode'][$code] = $this->getBillingAgreementCode($code);
            }
        }
        $this->_logger->debug($pre . 'eof', $dbConfig);

        return $dbConfig;
    }

    public function getPaymentTypes(): bool|string
    {
        $storeId = $this->storeManager->getStore()->getId();

        $paygate_pay_method_active = $this->scopeConfig->getValue(
            $this->path . 'paygate_pay_method_active',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        $enable_payment_types = explode(
            ',',
            $this->scopeConfig->getValue(
                $this->path . 'enable_payment_types',
                ScopeInterface::SCOPE_STORE,
                $storeId
            ) ?? ''
        );

        $allTypes = array(
            'CC'           => array(
                'value' => 'CC',
                'label' => "Card",
                'image' => $this->getViewFileUrl('PayGate_PayWeb::images/mastercard-visa.svg'),
            ),
            'BT'           => array(
                'value' => 'BT',
                'label' => "SiD Secure EFT",
                'image' => $this->getViewFileUrl('PayGate_PayWeb::images/sid.svg'),
            ),
            'EW-ZAPPER'    => array(
                'value' => 'EW-ZAPPER',
                'label' => "Zapper",
                'image' => $this->getViewFileUrl('PayGate_PayWeb::images/zapper.svg'),
            ),
            'EW-SNAPSCAN'  => array(
                'value' => 'EW-SNAPSCAN',
                'label' => "SnapScan",
                'image' => $this->getViewFileUrl('PayGate_PayWeb::images/snapscan.svg'),
            ),
            'EW-MOBICRED'  => array(
                'value' => 'EW-MOBICRED',
                'label' => "Mobicred",
                'image' => $this->getViewFileUrl('PayGate_PayWeb::images/mobicred.svg'),
            ),
            'EW-MOMOPAY'   => array(
                'value' => 'EW-MOMOPAY',
                'label' => "MoMoPay",
                'image' => $this->getViewFileUrl('PayGate_PayWeb::images/momopay.svg'),
            ),
            'EW-SCANTOPAY' => array(
                'value' => 'EW-SCANTOPAY',
                'label' => "ScanToPay",
                'image' => $this->getViewFileUrl('PayGate_PayWeb::images/scan-to-pay.svg'),
            ),
            'EW-PAYPAL'    => array(
                'value' => 'EW-PAYPAL',
                'label' => "PayPal",
                'image' => $this->getViewFileUrl('PayGate_PayWeb::images/paypal.svg'),
            )
        );

        $types = array();
        if ($paygate_pay_method_active != '0') {
            foreach ($enable_payment_types as $value) {
                $types[] = $allTypes[$value];
            }
        }

        return json_encode($types);
    }

    /**
     * Retrieve url of a view file
     *
     * @param string $fileId
     * @param array $params
     *
     * @return string
     */
    public function getViewFileUrl(string $fileId, array $params = []): string
    {
        try {
            $params = array_merge(['_secure' => $this->request->isSecure()], $params);

            return $this->assetRepo->getUrlWithParams($fileId, $params);
        } catch (LocalizedException $e) {
            $this->_logger->critical($e);

            return $this->urlBuilder->getUrl('', ['_direct' => 'core/index/notFound']);
        }
    }

    /**
     * Return redirect URL for method
     *
     * @param string $code
     *
     * @return mixed
     */
    protected function getMethodRedirectUrl(string $code): mixed
    {
        $pre = __METHOD__ . ' : ';
        $this->_logger->debug($pre . 'bof');

        $methodUrl = $this->methods[$code]->getCheckoutRedirectUrl();

        $this->_logger->debug($pre . 'eof');

        return $methodUrl;
    }

    /**
     * Return billing agreement code for method
     *
     * @param string $code
     *
     * @return bool
     */
    protected function getBillingAgreementCode(string $code): bool
    {
        $pre = __METHOD__ . ' : ';
        $this->_logger->debug($pre . 'bof');

        $this->config->setMethod($code);

        $this->_logger->debug($pre . 'eof');

        // Always return null
        return $this->paygateHelper->shouldAskToCreateBillingAgreement();
    }
}
