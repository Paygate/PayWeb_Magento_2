<?php
/**
 * @noinspection PhpUndefinedNamespaceInspection
 */

/*
 * Copyright (c) 2023 Payfast (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

use Magento\Framework\Component\ComponentRegistrar;

ComponentRegistrar::register(
    ComponentRegistrar::MODULE,
    'PayGate_PayWeb',
    __DIR__
);
