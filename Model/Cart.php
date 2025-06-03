<?php

/**
 * @noinspection PhpUnused
 */

/*
 * Copyright (c) 2025 Payfast (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

namespace PayGate\PayWeb\Model;

use Magento\Payment\Model\Cart\SalesModel\SalesModelInterface;

/**
 * Paygate-specific model for shopping cart items and totals
 * The main idea is to accommodate all possible totals into Paygate-compatible 4 totals and line items
 */
class Cart extends \Magento\Payment\Model\Cart
{
    /**
     * @var bool
     */
    protected bool $_areAmountsValid = false;

    /**
     * Get shipping, tax, subtotal and discount amounts all together
     *
     * @return       array
     */
    public function getAmounts(): array
    {
        $this->_collectItemsAndAmounts();

        if (!$this->_areAmountsValid) {
            $subtotal = $this->_calculateAdjustedSubtotal();

            return [self::AMOUNT_SUBTOTAL => $subtotal];
        }

        return $this->_amounts;
    }

    /**
     * Check whether any item has negative amount
     *
     * @return bool
     */
    public function hasNegativeItemAmount(): bool
    {
        foreach ($this->_customItems as $item) {
            if ($item->getAmount() < 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Calculate subtotal from custom items
     *
     * @return void
     */
    protected function _calculateCustomItemsSubtotal()
    {
        parent::_calculateCustomItemsSubtotal();
        $this->_applyDiscountTaxCompensationWorkaround($this->_salesModel);

        $this->_validate();
    }

    /**
     * Check the line items and totals according to Paygate business logic limitations
     *
     * @return       void
     */
    protected function _validate()
    {
        $areItemsValid          = false;
        $this->_areAmountsValid = false;

        $referenceAmount = $this->_salesModel->getDataUsingMethod('base_grand_total');

        $itemsSubtotal = $this->_calculateItemsSubtotal();
        $sum           = $itemsSubtotal + $this->getTax();
        $sum           = $this->_applyTransferFlags($sum, $itemsSubtotal);

        /**
         * Numbers are intentionally converted to string by reason of possible comparison error
         * see http://php.net/float
         */
        // Match sum of all the items and totals to the reference amount
        if (sprintf('%.4F', $sum) == sprintf('%.4F', $referenceAmount)) {
            $areItemsValid = true;
        }

        $areItemsValid = $areItemsValid && $this->_areAmountsValid;

        if (!$areItemsValid) {
            $this->_salesModelItems = [];
            $this->_customItems     = [];
        }
    }

    /**
     * Import items from sales model with workarounds for Paygate
     *
     * @return       void
     */
    protected function _importItemsFromSalesModel()
    {
        $this->_salesModelItems = [];

        foreach ($this->_salesModel->getAllItems() as $item) {
            if ($item->getParentItem()) {
                continue;
            }

            $amount = $this->_calculateItemAmount($item);
            $qty    = $item->getQty();

            $this->_salesModelItems[] = $this->_createItemFromData(
                $item->getName() . $this->_getSubAggregatedLabel($amount, $qty),
                $qty,
                $amount
            );
        }

        $this->addSubtotal($this->_salesModel->getBaseSubtotal());
        $this->addTax($this->_salesModel->getBaseTaxAmount());
        $this->addShipping($this->_salesModel->getBaseShippingAmount());
        $this->addDiscount(abs($this->_salesModel->getBaseDiscountAmount()));
    }

    private function _calculateAdjustedSubtotal()
    {
        $subtotal = $this->getSubtotal() + $this->getTax();

        if (empty($this->_transferFlags[self::AMOUNT_SHIPPING])) {
            $subtotal += $this->getShipping();
        }

        if (empty($this->_transferFlags[self::AMOUNT_DISCOUNT])) {
            $subtotal -= $this->getDiscount();
        }

        return $subtotal;
    }

    private function _calculateItemsSubtotal()
    {
        $itemsSubtotal = 0;
        foreach ($this->getAllItems() as $i) {
            $itemsSubtotal += $i->getQty() * $i->getAmount();
        }

        return $itemsSubtotal;
    }

    private function _applyTransferFlags($sum, $itemsSubtotal)
    {
        $sum = $this->_applyShippingTransferFlag($sum);
        $sum = $this->_applyDiscountTransferFlag($sum, $itemsSubtotal);

        return $sum;
    }

    private function _applyShippingTransferFlag($sum)
    {
        if (empty($this->_transferFlags[self::AMOUNT_SHIPPING])) {
            $sum += $this->getShipping();
        }

        return $sum;
    }

    private function _applyDiscountTransferFlag($sum, $itemsSubtotal)
    {
        if (empty($this->_transferFlags[self::AMOUNT_DISCOUNT])) {
            $sum -= $this->getDiscount();
            // Paygate requires the discount to be less than items subtotal
            $this->_areAmountsValid = round($this->getDiscount(), 4) < round($itemsSubtotal, 4);
        } else {
            $this->_areAmountsValid = $itemsSubtotal > 0.00001;
        }

        return $sum;
    }

    private function _calculateItemAmount($item)
    {
        $amount           = $item->getPrice();
        $qty              = $item->getQty();
        $itemBaseRowTotal = $item->getOriginalItem()->getBaseRowTotal();

        // Aggregate item price if item qty * price does not match row total
        if ($amount * $qty != $itemBaseRowTotal) {
            $amount = (double)$itemBaseRowTotal;
        }

        return $amount;
    }

    private function _getSubAggregatedLabel($amount, $qty)
    {
        // Workaround in case if item subtotal precision is not compatible with Paygate (.2)
        return ($amount - round($amount, 2)) ? ' x' . $qty : '';
    }

    /**
     * Add "hidden" discount and shipping tax
     *
     * Tax settings for getting "discount tax":
     * - Catalog Prices = Including Tax
     * - Apply Customer Tax = After Discount
     * - Apply Discount on Prices = Including Tax
     *
     * Test case for getting "hidden shipping tax":
     * - Make sure shipping is taxable (set shipping tax class)
     * - Catalog Prices = Including Tax
     * - Shipping Prices = Including Tax
     * - Apply Customer Tax = After Discount
     * - Create a cart price rule with % discount applied to the Shipping Amount
     * - Run shopping cart and estimate shipping
     * - Go to Paygate
     *
     * @param SalesModelInterface $salesEntity
     *
     * @return void
     */
    protected function _applyDiscountTaxCompensationWorkaround(
        SalesModelInterface $salesEntity
    ) {
        $dataContainer = $salesEntity->getTaxContainer();
        $this->addTax((double)$dataContainer->getBaseDiscountTaxCompensationAmount());
        $this->addTax((double)$dataContainer->getBaseShippingDiscountTaxCompensationAmnt());
    }
}
