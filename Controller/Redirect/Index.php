<?php
/**
 * @noinspection PhpMissingFieldTypeInspection
 */

/**
 * @noinspection PhpUndefinedNamespaceInspection
 */

/**
 * @noinspection PhpUnused
 */

/*
 * Copyright (c) 2023 Payfast (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

namespace PayGate\PayWeb\Controller\Redirect;

use Exception;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Webapi\Rest\Response;

/**
 * Responsible for loading page content.
 *
 * This is a basic controller that only loads the corresponding layout file. It may duplicate other such
 * controllers, and thus it is considered tech debt. This code duplication will be resolved in future releases.
 */
class Index implements HttpGetActionInterface, CsrfAwareActionInterface
{
    private const CART_URL = "checkout/cart";

    /**
     * @var ResultFactory
     */
    private ResultFactory $resultFactory;

    /**
     * @var ManagerInterface
     */
    private ManagerInterface $messageManager;

    /**
     * @var PageFactory
     */
    private PageFactory $pageFactory;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @var Order
     */
    private Order $order;
    /**
     * @var CheckoutSession
     */
    private CheckoutSession $checkoutSession;
    /**
     * @var Response
     */
    private Response $response;
    /**
     * @var CustomerSession
     */
    private CustomerSession $customerSession;

    /**
     * @param ResultFactory $resultFactory
     * @param ManagerInterface $messageManager
     * @param PageFactory $pageFactory
     * @param LoggerInterface $logger
     * @param Order $order
     * @param CheckoutSession $checkoutSession
     * @param Response $response
     * @param CustomerSession $customerSession
     */
    public function __construct(
        ResultFactory $resultFactory,
        ManagerInterface $messageManager,
        PageFactory $pageFactory,
        LoggerInterface $logger,
        Order $order,
        CheckoutSession $checkoutSession,
        Response $response,
        CustomerSession $customerSession
    ) {
        $this->resultFactory = $resultFactory;
        $this->messageManager = $messageManager;
        $this->pageFactory = $pageFactory;
        $this->logger = $logger;
        $this->order = $order;
        $this->checkoutSession = $checkoutSession;
        $this->response = $response;
        $this->customerSession = $customerSession;
    }

    /**
     * Execute
     *
     * @noinspection PhpUndefinedMethodInspection
     */
    public function execute()
    {
        $pre = __METHOD__ . " : ";

        $page_object = $this->pageFactory->create();

        // Get the current customer session
        $customerSession = $this->customerSession;

        // Check if the customer is logged in
        if ($customerSession->isLoggedIn()) {
            // Set the customer session lifetime to 30 days
            $customerSession->setLifetime(30 * 60 * 60);
        }

        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        try {
            $this->_initCheckout();
        } catch (LocalizedException $e) {
            $this->logger->error($pre . $e->getMessage());
            $this->messageManager->addExceptionMessage($e, $e->getMessage());
            return $resultRedirect->setPath(self::CART_URL);
        } catch (Exception $e) {
            $this->logger->error($pre . $e->getMessage());
            $this->messageManager->addExceptionMessage($e, __('We can\'t start PayGate Checkout.'));
            return $resultRedirect->setPath(self::CART_URL);
        }

        $block = $page_object->getLayout()
            ->getBlock('paygate')
            ->setPaymentFormData($this->order ?? null);

        $formData = $block->getFormData();
        if (isset($formData["error"])) {
            $this->logger->error("We can\'t start Paygate Checkout.");
            $this->messageManager->addErrorMessage(__('Error code: ' . $formData["error"]));
            return $resultRedirect->setPath(self::CART_URL);
        }

        return $page_object;
    }

    /**
     * Instantiate
     *
     * @return       void
     * @throws       LocalizedException
     * @noinspection PhpUndefinedMethodInspection
     */
    protected function _initCheckout()
    {
        $pre = __METHOD__ . " : ";
        $this->logger->debug($pre . 'bof');
        $this->order = $this->checkoutSession->getLastRealOrder();

        if (!$this->order->getId()) {
            $this->response->setStatusHeader(404, '1.1', 'Not found');
            throw new LocalizedException(__('We could not find "Order" for processing'));
        }

        if ($this->order->getState() != Order::STATE_PENDING_PAYMENT) {
            $this->order->setState(
                Order::STATE_PENDING_PAYMENT
            )->save();
        }

        if ($this->order->getQuoteId()) {
            $this->checkoutSession->setPaygateQuoteId($this->checkoutSession->getQuoteId());
            $this->checkoutSession->setPaygateSuccessQuoteId($this->checkoutSession->getLastSuccessQuoteId());
            $this->checkoutSession->setPaygateRealOrderId($this->checkoutSession->getLastRealOrderId());
            $this->checkoutSession->getQuote()->setIsActive(false)->save();
        }

        $this->logger->debug($pre . 'eof');
    }

    /**
     * Validation exception csrf
     *
     * @param RequestInterface $request
     * @return InvalidRequestException|null
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /**
     * Validate csrf
     *
     * @param RequestInterface $request
     * @return bool|null
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
