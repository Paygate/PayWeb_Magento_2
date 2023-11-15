<?php

namespace PayGate\PayWeb\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Sales\Setup\SalesSetup;

class DataPatch implements DataPatchInterface
{
    /**
     * @var ModuleDataSetupInterface
     */
    private ModuleDataSetupInterface $moduleDataSetup;
    /**
     * @var SalesSetup
     */
    private SalesSetup $salesSetup;

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param SalesSetup $salesSetup
     */
    public function __construct(ModuleDataSetupInterface $moduleDataSetup, SalesSetup $salesSetup)
    {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->salesSetup = $salesSetup;
    }

    /**
     * Apply Data Patch for NGenius custom order statuses
     *
     * @return void
     */
    public function apply()
    {
        $this->moduleDataSetup->startSetup();

        if (!$this->salesSetup->getAttribute('order', 'payweb_payment_processed')) {
            $this->salesSetup->addAttribute('order', 'payweb_payment_processed', ['type' => 'int']);
        }

        $this->moduleDataSetup->endSetup();
    }

    /**
     * Mark any dependencies here
     *
     * @return array|string[]
     */
    public static function getDependencies()
    {
        return [];
    }

    /**
     * For any references to old installation data
     *
     * @return array|string[]
     */
    public function getAliases()
    {
        return [];
    }
}
