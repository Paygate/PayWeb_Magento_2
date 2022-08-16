<?php
/** @noinspection PhpUnused */

/** @noinspection PhpUndefinedNamespaceInspection */

namespace PayGate\PayWeb\Setup;

use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\UpgradeDataInterface;
use Magento\Sales\Setup\SalesSetup;

class UpgradeData implements UpgradeDataInterface
{

    /**
     *
     * @var Magento\Sales\Setup\SalesSetup|SalesSetup
     */
    private SalesSetup|Magento\Sales\Setup\SalesSetup $_salesSetup;

    /**
     * Init
     *
     * @param SalesSetup $SalesSetup
     *
     * @noinspection PhpUnused
     */
    public function __construct(
        SalesSetup $SalesSetup
    ) {
        $this->_salesSetup = $SalesSetup;
    }

    /**
     * {@inheritdoc}
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     * @noinspection PhpUnused
     * @noinspection PhpUnusedParameterInspection
     */
    public function upgrade(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        if (version_compare($context->getVersion(), '2.4.4') < 0) {
            $salesSetup = $this->_salesSetup;
            $salesSetup->addAttribute('order', 'payweb_payment_processed', ['type' => 'int']);
        }
    }
}
