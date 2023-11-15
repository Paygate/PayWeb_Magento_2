<?php
/**
 * Copyright (c) 2023 Payfast (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

namespace PayGate\PayWeb\Api;

use Magento\Framework\Controller\Result\Json;

interface PayWebApiManagementInterface
{
    /**
     * Get PayWeb Api data.
     *
     * @param int $id Left-hand operand.
     * @param int $quote_id
     *
     * @return Json
     * @api
     * @noinspection PhpUnused
     */
    public function getApiData(int $id, int $quote_id): Json;
}
