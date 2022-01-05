<?php

/**
 * Copyright (c) 2022 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

namespace PayGate\PayWeb\Api;

interface PayWebApiManagementInterface
{
    /**
     * get PayWeb Api data.
     *
     * @param int $id Left hand operand.
     * @param int $quote_id Right hand operand.
     * @param int $quote_id
     *
     * @return string
     * @api
     *
     */
    public function getApiData($id, $quote_id);
}
