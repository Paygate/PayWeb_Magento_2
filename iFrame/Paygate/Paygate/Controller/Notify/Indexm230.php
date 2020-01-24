<?php
/*
 * Copyright (c) 2020 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 *
 * Magento v2.3.0+ implement CsrfAwareActionInterface but not earlier versions
 */

namespace Paygate\Paygate\Controller\Notify;

use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Paygate\Paygate\Controller\AbstractPaygate;

class Indexm230 extends AbstractPaygate implements CsrfAwareActionInterface
{
    /**
     * @var \Magento\Framework\View\Result\PageFactory
     */
    protected $resultPageFactory;

    /**
     * Config method type
     *
     * @var string
     */
    protected $_configMethod = \Paygate\Paygate\Model\Config::METHOD_CODE;

    /**
     * Execute
     */
    public function execute()
    {
        $pre = __METHOD__ . " : ";

        $page_object = $this->pageFactory->create();

        try {
            $this->_initCheckout();
        } catch ( \Magento\Framework\Exception\LocalizedException $e ) {
            $this->_logger->error( $pre . $e->getMessage() );
            $this->messageManager->addExceptionMessage( $e, $e->getMessage() );
            $this->_redirect( 'checkout/cart' );
        } catch ( \Exception $e ) {
            $this->_logger->error( $pre . $e->getMessage() );
            $this->messageManager->addExceptionMessage( $e, __( 'We can\'t start PayGate Checkout.' ) );
            $this->_redirect( 'checkout/cart' );
        }

        return $page_object;
    }

    /**
     * @inheritDoc
     */
    public function createCsrfValidationException( RequestInterface $request ):  ? InvalidRequestException
    {
        // TODO: Implement createCsrfValidationException() method.
    }

    /**
     * @inheritDoc
     */
    public function validateForCsrf( RequestInterface $request ) :  ? bool
    {
        return true;
    }

}
