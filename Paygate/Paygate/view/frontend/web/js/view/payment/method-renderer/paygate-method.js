/*
 * Copyright (c) 2018 PayGate (Pty) Ltd
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
    function($,
              Component,
              placeOrderAction,
              selectPaymentMethodAction,
              customer,
              checkoutData,
              additionalValidators,
              url
			){
           'use strict';

        return Component.extend({
            defaults:{
                template: 'Paygate_Paygate/payment/paygate'
            },
			 placeOrder: function (data, event){
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
                if (emailValidationResult && this.validate() && additionalValidators.validate()){
                    this.isPlaceOrderActionAllowed(false);
                    placeOrder = placeOrderAction(this.getData(), false, this.messageContainer);
					$.when(placeOrder).fail(function () {
						self.isPlaceOrderActionAllowed(true);
					}).done( function(order_id) {
						
						jQuery.ajax({
							url: url.build('paygate/redirect/order'),
							type: "POST",
							data: {order_id:order_id},
							complete: function(data){
							   var params = JSON.parse(data.responseText);
							   var pkey = [];
							   var pvalue = [];
							   var i = 0;
							   jQuery.each(params, function(key, value) {
                                pkey[i] = key ;
							    pvalue[i] = value ; 
								i = i + 1 ;
                               });
								jQuery("#paygateButton").after("<div id='payPopup'></div>");
							    jQuery("#payPopup").append("<div id='payPopupContent'></div>");
								jQuery("#payPopupContent").append("<form target='myIframe' name='paygate_checkout' id='paygate_checkout' action='https://secure.paygate.co.za/payweb3/process.trans' method='post'><input type='hidden' name='"+pkey[0]+"' value='"+pvalue[0]+"' size='200'><input type='hidden' name='"+pkey[1]+"' value='"+pvalue[1]+"' size='200'></form><iframe id='payPopupFrame' name='myIframe'  src='#' ></iframe><script type='text/javascript'>document.getElementById('paygate_checkout').submit();</script>");  
                            }
						});
					});
                    return false;	
                }
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
            getPaymentAcceptanceMarkHref: function(){
                return window.checkoutConfig.payment.paygate.paymentAcceptanceMarkHref;
            },
            /** Returns payment acceptance mark image path */
            getPaymentAcceptanceMarkSrc: function(){
                return window.checkoutConfig.payment.paygate.paymentAcceptanceMarkSrc;
            }

        });
    }   
);