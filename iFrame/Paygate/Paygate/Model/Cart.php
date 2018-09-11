<?php
/*
 * Copyright (c) 2018 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 * 
 * Released under the GNU General Public License
 */
namespace Paygate\Paygate\Model;

/**
 * Paygate-specific model for shopping cart items and totals
 * The main idea is to accommodate all possible totals into Paygate-compatible 4 totals and line items
 */
class Cart extends \Magento\Payment\Model\Cart
{
    /**
     * @var bool
     */
    protected $_areAmountsValid = false;

    /**
     * Get shipping, tax, subtotal and discount amounts all together
     *
     * @return array
     */
    public function getAmounts()
    {
        $this->_collectItemsAndAmounts();

        if ( !$this->_areAmountsValid ) {
            $subtotal = $this->getSubtotal() + $this->getTax();

            if ( empty( $this->_transferFlags[self::AMOUNT_SHIPPING] ) ) {
                $subtotal += $this->getShipping();
            }

            if ( empty( $this->_transferFlags[self::AMOUNT_DISCOUNT] ) ) {
                $subtotal -= $this->getDiscount();
            }

            return [self::AMOUNT_SUBTOTAL => $subtotal];
        }

        return $this->_amounts;
    }

    /**
     * Calculate subtotal from custom items
     *
     * @return void
     */
    protected function _calculateCustomItemsSubtotal()
    {
        parent::_calculateCustomItemsSubtotal();
        $this->_applyDiscountTaxCompensationWorkaround( $this->_salesModel );

        $this->_validate();
    }

    /**
     * Check the line items and totals according to PayGate business logic limitations
     *
     * @return void
     */
    protected function _validate()
    {
        $areItemsValid          = false;
        $this->_areAmountsValid = false;

        $referenceAmount = $this->_salesModel->getDataUsingMethod( 'base_grand_total' );

        $itemsSubtotal = 0;
        foreach ( $this->getAllItems() as $i ) {
            $itemsSubtotal = $itemsSubtotal + $i->getQty() * $i->getAmount();
        }

        $sum = $itemsSubtotal + $this->getTax();

        if ( empty( $this->_transferFlags[self::AMOUNT_SHIPPING] ) ) {
            $sum += $this->getShipping();
        }

        if ( empty( $this->_transferFlags[self::AMOUNT_DISCOUNT] ) ) {
            $sum -= $this->getDiscount();
            // PayGate requires to have discount less than items subtotal
            $this->_areAmountsValid = round( $this->getDiscount(), 4 ) < round( $itemsSubtotal, 4 );
        } else {
            $this->_areAmountsValid = $itemsSubtotal > 0.00001;
        }

        /**
         * Numbers are intentionally converted to strings by reason of possible comparison error
         * see http://php.net/float
         */
        // match sum of all the items and totals to the reference amount
        if ( sprintf( '%.4F', $sum ) == sprintf( '%.4F', $referenceAmount ) ) {
            $areItemsValid = true;
        }

        $areItemsValid = $areItemsValid && $this->_areAmountsValid;

        if ( !$areItemsValid ) {
            $this->_salesModelItems = [];
            $this->_customItems     = [];
        }
    }

    /**
     * Import items from sales model with workarounds for PayGate
     *
     * @return void
     */
    protected function _importItemsFromSalesModel()
    {
        $this->_salesModelItems = [];

        foreach ( $this->_salesModel->getAllItems() as $item ) {
            if ( $item->getParentItem() ) {
                continue;
            }

            $amount = $item->getPrice();
            $qty    = $item->getQty();

            $subAggregatedLabel = '';

            // Workaround in case if item subtotal precision is not compatible with PayGate (.2)
            if ( $amount - round( $amount, 2 ) ) {
                $amount             = $amount * $qty;
                $subAggregatedLabel = ' x' . $qty;
                $qty                = 1;
            }

            // Aggregate item price if item qty * price does not match row total
            $itemBaseRowTotal = $item->getOriginalItem()->getBaseRowTotal();
            if ( $amount * $qty != $itemBaseRowTotal ) {
                $amount             = (double) $itemBaseRowTotal;
                $subAggregatedLabel = ' x' . $qty;
                $qty                = 1;
            }

            $this->_salesModelItems[] = $this->_createItemFromData(
                $item->getName() . $subAggregatedLabel,
                $qty,
                $amount
            );
        }

        $this->addSubtotal( $this->_salesModel->getBaseSubtotal() );
        $this->addTax( $this->_salesModel->getBaseTaxAmount() );
        $this->addShipping( $this->_salesModel->getBaseShippingAmount() );
        $this->addDiscount( abs( $this->_salesModel->getBaseDiscountAmount() ) );
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
     * - Go to PayGate
     *
     * @param \Magento\Payment\Model\Cart\SalesModel\SalesModelInterface $salesEntity
     * @return void
     */
    protected function _applyDiscountTaxCompensationWorkaround(
        \Magento\Payment\Model\Cart\SalesModel\SalesModelInterface $salesEntity
    ) {
        $dataContainer = $salesEntity->getTaxContainer();
        $this->addTax( (double) $dataContainer->getBaseDiscountTaxCompensationAmount() );
        $this->addTax( (double) $dataContainer->getBaseShippingDiscountTaxCompensationAmnt() );
    }

    /**
     * Check whether any item has negative amount
     *
     * @return bool
     */
    public function hasNegativeItemAmount()
    {
        foreach ( $this->_customItems as $item ) {
            if ( $item->getAmount() < 0 ) {
                return true;
            }
        }
        return false;
    }
}