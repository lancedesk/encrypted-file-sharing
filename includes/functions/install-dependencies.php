<?php
/**
 * Installs Composer dependencies if not already installed.
*/

function efs_install_dependencies() {
    /* Path to the plugin directory */
    $plugin_dir = plugin_dir_path(__FILE__);

    /* Path to Composer executable */
    $composer = 'composer'; /* Use 'composer.phar' if composer is not globally available */

    /* Path to vendor directory */
    $vendor_dir = $plugin_dir . 'vendor/';

    /* Check if vendor directory exists */
    if (!is_dir($vendor_dir)) {
        /* Run Composer install */
        $cmd = sprintf('%s install --no-dev --optimize-autoloader', escapeshellcmd($composer));
        exec($cmd, $output, $return_var);

        if ($return_var !== 0) {
            error_log('Composer install failed: ' . implode("\n", $output));
        }
    }
}