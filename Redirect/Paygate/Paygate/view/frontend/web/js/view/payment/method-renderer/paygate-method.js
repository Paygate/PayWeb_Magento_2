/*
 * Copyright (c) 2020 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 * 
 * Released under the GNU General Public License
 */
define(
    [
        'jquery',
        'Magento_Checkout/js/view/payment/default',
        'Magento_Checkout/js/action/place-order',
        'Magento_Checkout/js/action/select-payment-method',
        'Magento_Customer/js/model/customer',
        'Magento_Checkout/js/checkout-data',
        'Magento_Checkout/js/model/payment/additional-validators',
        'mage/url'
    ],
    function ($,
              Component,
              placeOrderAction,
              selectPaymentMethodAction,
              customer,
              checkoutData,
              additionalValidators,
              url)  {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Paygate_Paygate/payment/paygate'
            },
			 placeOrder: function (data, event) {
                if (event) {
                    event.preventDefault();
                }
                var self = this,
                    placeOrder,
                    emailValidationResult = customer.isLoggedIn(),
                    loginFormSelector = 'form[data-role=email-with-possible-login]';
                if (!customer.isLoggedIn()) {
                    $(loginFormSelector).validation();
                    emailValidationResult = Boolean($(loginFormSelector + ' input[name=username]').valid());
                }
                if (emailValidationResult && this.validate() && additionalValidators.validate()) {
                    this.isPlaceOrderActionAllowed(false);
                    placeOrder = placeOrderAction(this.getData(), false, this.messageContainer);

                    $.when(placeOrder).fail(function () {
                        self.isPlaceOrderActionAllowed(true);
                    }).done(this.afterPlaceOrder.bind(this));
                    return true;
                }
                return false;
            },
            getCode: function() {
                return 'paygate';
            },
			 selectPaymentMethod: function() {
                selectPaymentMethodAction(this.getData());
                checkoutData.setSelectedPaymentMethod(this.item.method);
                return true;
            },
            /**
             * Get value of instruction field.
             * @returns {String}
             */
            getInstructions: function () {
                return window.checkoutConfig.payment.instructions[this.item.method];
            },
            isAvailable: function() {
                return quote.totals().grand_total <= 0;
            },
            afterPlaceOrder: function () {
                window.location.replace( url.build(window.checkoutConfig.payment.paygate.redirectUrl.paygate) );
            },
            /** Returns payment acceptance mark link path */
            getPaymentAcceptanceMarkHref: function() {
                return window.checkoutConfig.payment.paygate.paymentAcceptanceMarkHref;
            },
            /** Returns payment acceptance mark image path */
            getPaymentAcceptanceMarkSrc: function() {
                return window.checkoutConfig.payment.paygate.paymentAcceptanceMarkSrc;
            }

        });
    }
);