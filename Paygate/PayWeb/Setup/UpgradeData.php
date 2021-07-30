<?php

namespace PayGate\PayWeb\Setup;

use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\UpgradeDataInterface;
use Magento\Sales\Setup\SalesSetup;

class UpgradeData implements UpgradeDataInterface
{

    /**
     *
     * @var Magento\Sales\Setup\SalesSetup
     */
    private $_salesSetup;

    /**
     * Init
     *
     * @param EavSetupFactory $eavSetupFactory
     */
    public function __construct(
        SalesSetup $SalesSetup
    ) {
        $this->_salesSetup = $SalesSetup;
    }

    /**
     * {@inheritdoc}
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function upgrade(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        if (version_compare($context->getVersion(), '2.4.4') < 0) {
            $salesSetup = $this->_salesSetup;
            $salesSetup->addAttribute('order', 'payweb_payment_processed', ['type' => 'int']);
        }
    }
}
