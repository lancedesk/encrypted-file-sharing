<?php

/**
 * Installs Composer dependencies if not already installed.
*/

function efs_install_dependencies()
{
    global $wp_filesystem;

    /* Initialize WP_Filesystem */
    if (!function_exists('WP_Filesystem')) 
    {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
    }

    WP_Filesystem(); /* Initialize WP_Filesystem */

    /* Path to the plugin directory */
    $plugin_dir = plugin_dir_path(__FILE__);

    /* Path to Composer executable */
    $composer = 'composer'; /* Default to composer, adjust based on environment */

    /* Path to vendor directory */
    $vendor_dir = $plugin_dir . 'vendor/';

    /* Path to log file */
    $log_file = WP_CONTENT_DIR . '/efs-install-log.txt';

    /* Initialize log content */
    $log_content = '[' . gmdate('Y-m-d H:i:s') . '] Starting Composer installation...' . "\n";

    /* Check environment */
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN')
    {
        /* Windows environment */
        $composer = 'composer.phar'; /* Use composer.phar on Windows */
        $env_type = 'Windows';
    }
    else
    {
        /* Unix-like environment (Linux, macOS, etc.) */
        $composer = 'composer'; /* Use composer on Unix-like systems */
        $env_type = 'Unix-like';
    }

    /* Log environment type */
    $log_content .= '[' . gmdate('Y-m-d H:i:s') . '] Detected environment: ' . $env_type . "\n";

    /* Check if vendor directory exists */
    if (!$wp_filesystem->is_dir($vendor_dir))
    {
        /* Run Composer install */
        $cmd = sprintf('php %s install --no-dev --optimize-autoloader 2>&1', escapeshellcmd($composer)); /* Use PHP to run composer.phar if needed */
        exec($cmd, $output, $return_var);

        /* Log output */
        if ($return_var !== 0)
        {
            $log_content .= '[' . gmdate('Y-m-d H:i:s') . '] Composer install failed. Return code: ' . $return_var . "\n";
            $log_content .= 'Output: ' . implode("\n", $output) . "\n";
        }
        else
        {
            $log_content .= '[' . gmdate('Y-m-d H:i:s') . '] Composer install succeeded.' . "\n";
            $log_content .= 'Output: ' . implode("\n", $output) . "\n";
        }
    }
    else
    {
        $log_content .= '[' . gmdate('Y-m-d H:i:s') . '] Dependencies already installed or vendor directory exists.' . "\n";
    }

    /* Write log to file using WP_Filesystem */
    $wp_filesystem->put_contents($log_file, $log_content, FS_CHMOD_FILE);
}