<?php
/*
 * Copyright (c) 2021 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

// @codingStandardsIgnoreFile

namespace PayGate\PayWeb\Model;

/**
 * PayGate payment information model
 *
 * Aware of all PayGate payment methods
 * Collects and provides access to PayGate-specific payment data
 * Provides business logic information about payment flow
 */
class Info
{
    /**
     * Apply a filter upon value getting
     *
     * @param string $value
     * @param string $key
     *
     * @return string
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    protected function _getValue( $value )
    {
        $label       = '';
        $outputValue = implode( ', ', (array) $value );

        return sprintf( '#%s%s', $outputValue, $outputValue == $label ? '' : ': ' . $label );
    }

}
