<?php

/**
 * @noinspection PhpUnused
 */

/**
 * Copyright (c) 2025 Payfast (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

namespace PayGate\PayWeb\Model\Api;

use Magento\Framework\Exception\LocalizedException;
use PayGate\PayWeb\Api\PayWebApiManagementInterface;
use PayGate\PayWeb\Model\PayWeb;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\Result\Json;

class PayWebApiManagement implements PayWebApiManagementInterface
{
    public const SERVER_ERROR = 0;
    public const SUCCESS      = 1;
    public const LOCAL_ERROR  = 2;

    /**
     * @var PayWeb
     */
    protected PayWeb $payweb;
    /**
     * @var PayWeb
     */
    private PayWeb $_payweb;
    /**
     * @var JsonFactory
     */
    private JsonFactory $jsonFactory;

    /**
     * @param PayWeb $payweb
     * @param JsonFactory $jsonFactory
     */
    public function __construct(
        PayWeb $payweb,
        JsonFactory $jsonFactory
    ) {
        $this->_payweb     = $payweb;
        $this->jsonFactory = $jsonFactory;
    }

    /**
     * Get test Api data.
     *
     * @param int $id Left-hand operand.
     * @param int $quote_id Right-hand operand.
     *
     * @return Json
     * @api
     */
    public function getApiData(int $id, int $quote_id): Json
    {
        $jsonResult = $this->jsonFactory->create();
        try {
            $model = $this->_payweb;

            $result = $model->getStandardCheckoutFormFields($id, $quote_id);
            if (isset($result['ERROR_CODE'])) {
                $returnArray['error']  = $result['ERROR_CODE'];
                $returnArray['status'] = 0;
            } else {
                $returnArray = $result;
            }

            $jsonResult->setData(json_encode($returnArray));

            return $jsonResult;
        } catch (LocalizedException $e) {
            $returnArray['error']  = $e->getMessage();
            $returnArray['status'] = 0;
            $jsonResult->setData(json_encode($returnArray));

            return $jsonResult;
        }
    }
}
