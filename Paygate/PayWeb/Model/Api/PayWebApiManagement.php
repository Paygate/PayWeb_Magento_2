<?php
/**
 * Copyright (c) 2022 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

namespace PayGate\PayWeb\Model\Api;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\Exception\LocalizedException;
use PayGate\PayWeb\Api\PayWebApiManagementInterface;
use PayGate\PayWeb\Model\PayWeb;

class PayWebApiManagement implements PayWebApiManagementInterface
{
    const SERVER_ERROR = 0;
    const SUCCESS      = 1;
    const LOCAL_ERROR  = 2;

    protected $payweb;

    public function __construct(
        PayWeb $payweb
    ) {
        $this->_payweb = $payweb;
    }

    /**
     * get test Api data.
     *
     * @param int $id Left hand operand.
     * @param int $quote_id Right hand operand.
     *
     * @return TestApiInterface
     * @api
     *
     */
    public function getApiData($id, $quote_id)
    {
        header('Access-Control-Allow-Origin: *');
        header('Content-type: application/json');
        try {
            $model = $this->_payweb;

            $result = $model->getStandardCheckoutFormFields($id, $quote_id);
            if (isset($result['ERROR_CODE'])) {
                $returnArray['error']  = $result['ERROR_CODE'];
                $returnArray['status'] = 0;
            } else {
                $returnArray = $result;
            }

            echo json_encode($returnArray);
            exit;
        } catch (LocalizedException $e) {
            $returnArray['error']  = $e->getMessage();
            $returnArray['status'] = 0;
            echo json_encode($returnArray);
            exit;
        }
    }


}
