/*
 * Copyright (c) 2021 PayGate (Pty) Ltd
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
    'mage/url',
    'Magento_Payment/js/view/payment/cc-form',
    'Magento_Vault/js/view/payment/vault-enabler'
  ],
  function ($,
    Component,
    placeOrderAction,
    selectPaymentMethodAction,
    customer,
    checkoutData,
    additionalValidators,
    url,
    CCForm,
    VaultEnabler
  ) {
    'use strict'

    return Component.extend({
      defaults: {
        template: 'PayGate_PayWeb/payment/paygate'
      },
      getData: function () {

        var paymentType = $('input[name=payment-type]:checked').val()
        if ($('#paygate-payvault-method').prop('checked') == true) {
          var payvault = 1
        } else {
          var savedCard = $('#saved_cards').find(':selected').val()
          if (savedCard != 'undefined') {
            payvault = savedCard
          } else {
            payvault = 0
          }
        }
        if (null == paymentType || typeof paymentType == 'undefined') {
          paymentType = 0
        }

        var data = {
          'method': this.item.method,
          'additional_data': {
            'paygate-payvault-method': payvault,
            'paygate-payment-type': paymentType
          }
        }

        return data
      },

      /**
       * @returns {Boolean}
       */
      isVaultEnabled: function () {
        var isVault = window.checkoutConfig.payment.paygate.isVault
        return isVault
      },

      /**
       * @returns {json}
       */
      getPaymentTypesList: function () {
        var paymentTypes = window.checkoutConfig.payment.paygate.paymentTypeList
        return paymentTypes
      },

      /**
       * @returns {json}
       */
      getSavedCardList: function () {
        var savedCard = window.checkoutConfig.payment.paygate.saved_card_data
        return savedCard
      },

      /**
       * @returns {json}
       */
      checkSavedCard: function () {
        var savedCard = window.checkoutConfig.payment.paygate.card_count
        return savedCard
      },

      /**
       * @returns {Boolean}
       */
      isPaymentTypes: function () {
        var paymentTypes = window.checkoutConfig.payment.paygate.paymentTypes
        if ('null' != paymentTypes) {
          return true
        }
        return false
      },

      /**
       * @returns {Boolean}
       */
      paymentTypesEnabled: function () {
        var paymentTypes = window.checkoutConfig.payment.paygate.enablePaymentTypes
        return paymentTypes
      },

      placeOrder: function (data, event) {
        if (event) {
          event.preventDefault()
        }
        var self = this,
          placeOrder,
          emailValidationResult = customer.isLoggedIn(),
          loginFormSelector = 'form[data-role=email-with-possible-login]'
        if (!customer.isLoggedIn()) {
          $(loginFormSelector).validation()
          emailValidationResult = Boolean($(loginFormSelector + ' input[name=username]').valid())
        }
        if (emailValidationResult && this.validate() && additionalValidators.validate()) {
          this.isPlaceOrderActionAllowed(false)
          placeOrder = placeOrderAction(this.getData(), false, this.messageContainer)
          $.when(placeOrder).fail(function () {
            self.isPlaceOrderActionAllowed(true)
          }).done(this.afterPlaceOrder.bind(this))
          return true
        }
      },
      getCode: function () {
        return 'paygate'
      },
      selectPaymentMethod: function () {
        selectPaymentMethodAction(this.getData())
        checkoutData.setSelectedPaymentMethod(this.item.method)
        return true
      },
      /**
       * Get value of instruction field.
       * @returns {String}
       */
      getInstructions: function () {
        return window.checkoutConfig.payment.instructions[this.item.method]
      },
      isAvailable: function () {
        return quote.totals().grand_total <= 0
      },
      afterPlaceOrder: function () {
        window.location.replace(url.build(window.checkoutConfig.payment.paygate.redirectUrl.paygate))
      },
      /** Returns payment acceptance mark link path */
      getPaymentAcceptanceMarkHref: function () {
        return window.checkoutConfig.payment.paygate.paymentAcceptanceMarkHref
      },
      /** Returns payment acceptance mark image path */
      getPaymentAcceptanceMarkSrc: function () {
        return window.checkoutConfig.payment.paygate.paymentAcceptanceMarkSrc
      }

    })
  }
)
