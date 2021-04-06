<?php
/*
 * Copyright (c) 2021 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */
namespace PayGate\PayWeb\Block\Payment;

/**
 * PayGate common payment info block
 * Uses default templates
 */
class Info extends \Magento\Payment\Block\Info
{
    /**
     * @var \PayGate\PayWeb\Model\InfoFactory
     */
    protected $_PaygateInfoFactory;

    /**
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \Magento\Payment\Model\Config $paymentConfig
     * @param \PayGate\PayWeb\Model\InfoFactory $PaygateInfoFactory
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Payment\Model\Config $paymentConfig,
        \PayGate\PayWeb\Model\InfoFactory $PaygateInfoFactory,
        array $data = []
    ) {
        $this->_PaygateInfoFactory = $PaygateInfoFactory;
        $this->_paymentConfig      = $paymentConfig;
        parent::__construct( $context, $data );
    }

}
