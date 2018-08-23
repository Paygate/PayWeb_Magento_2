<?php
/*
 * Copyright (c) 2018 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 * 
 * Released under the GNU General Public License
 */
namespace Paygate\Paygate\Block\Payment;

/**
 * Paygate common payment info block
 * Uses default templates
 */
class Info extends \Magento\Payment\Block\Info
{
    /**
     * @var \Paygate\Paygate\Model\InfoFactory
     */
    protected $_PaygateInfoFactory;

    /**
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \Magento\Payment\Model\Config $paymentConfig
     * @param \Paygate\Paygate\Model\InfoFactory $PaygateInfoFactory
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Payment\Model\Config $paymentConfig,
        \Paygate\Paygate\Model\InfoFactory $PaygateInfoFactory,
        array $data = []
    ) {
        $this->_PaygateInfoFactory = $PaygateInfoFactory;
        parent::__construct( $context, $data );
    }

}
