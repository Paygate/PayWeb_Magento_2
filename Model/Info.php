<?php
/** @noinspection PhpUnused */

/*
 * Copyright (c) 2023 Payfast (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

// @codingStandardsIgnoreFile

namespace PayGate\PayWeb\Model;

/**
 * Paygate payment information model
 *
 * Aware of all Paygate payment methods
 * Collects and provides access to Paygate-specific payment data
 * Provides business logic information about payment flow
 */
class Info
{
    /**
     * Apply a filter upon value getting
     *
     * @param string $value
     *
     * @return string
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    protected function _getValue(string $value): string
    {
        $label       = '';
        $outputValue = implode(', ', (array)$value);

        return sprintf('#%s%s', $outputValue, $outputValue == $label ? '' : ': ' . $label);
    }

}
