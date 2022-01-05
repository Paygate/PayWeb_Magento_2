<?php
/*
 * Copyright (c) 2022 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

namespace PayGate\PayWeb\Controller\Redirect;

use Exception;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Result\PageFactory;
use PayGate\PayWeb\Controller\AbstractPaygate;
use PayGate\PayWeb\Model\Config;

/**
 * Responsible for loading page content.
 *
 * This is a basic controller that only loads the corresponding layout file. It may duplicate other such
 * controllers, and thus it is considered tech debt. This code duplication will be resolved in future releases.
 */
class Index extends AbstractPaygate
{
    const CARTURL = "checkout/cart";
    /**
     * @var PageFactory
     */
    protected $resultPageFactory;
    /**
     * Config method type
     *
     * @var string
     */
    protected $_configMethod = Config::METHOD_CODE;

    /**
     * Execute
     */
    public function execute()
    {
        $pre = __METHOD__ . " : ";

        $page_object = $this->pageFactory->create();

        try {
            $this->_initCheckout();
        } catch (LocalizedException $e) {
            $this->_logger->error($pre . $e->getMessage());
            $this->messageManager->addExceptionMessage($e, $e->getMessage());
            $this->_redirect(self::CARTURL);
        } catch (Exception $e) {
            $this->_logger->error($pre . $e->getMessage());
            $this->messageManager->addExceptionMessage($e, __('We can\'t start PayGate Checkout.'));
            $this->_redirect(self::CARTURL);
        }

        $block = $page_object->getLayout()
                             ->getBlock('paygate')
                             ->setPaymentFormData(isset($order) ? $order : null);

        $formData = $block->getFormData();
        if ( ! $formData) {
            $this->_logger->error("We can\'t start PayGate Checkout.");
            $this->_redirect(self::CARTURL);
        }

        return $page_object;
    }

}
