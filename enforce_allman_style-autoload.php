<?php

/**
 * Autoloader for the Allman style enforcer.
 *
 * @package   EnforceAllmanStyle
 * @copyright 2023 Ethan Silver
 * @license   https://opensource.org/licenses/MIT MIT
 */

if (defined('ENFORCE_ALLMAN_STYLE_AUTOLOAD') === false) {
    /*
     * Register an autoloader.
     */
    spl_autoload_register(function ($fqClassName) {
        // Only try & load our own classes.
        if (stripos($fqClassName, 'EnforceAllmanStyle') !== 0) {
            return;
        }

        $file = realpath(__DIR__) . DIRECTORY_SEPARATOR . strtr($fqClassName, '\\', DIRECTORY_SEPARATOR) . '.php';

        if (file_exists($file)) {
            include_once $file;
        }
    });

    define('ENFORCE_ALLMAN_STYLE_AUTOLOAD', true);
}
