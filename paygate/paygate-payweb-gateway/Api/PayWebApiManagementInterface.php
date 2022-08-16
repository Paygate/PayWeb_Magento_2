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
     * @param int $id Left-hand operand.
     * @param int $quote_id
     *
     * @return string
     * @api
     * @noinspection PhpUnused
     */
    public function getApiData(int $id, int $quote_id): string;
}
