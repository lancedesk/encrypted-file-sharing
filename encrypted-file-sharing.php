<?php
/**
 * Plugin Name:     Encrypted File Sharing
 * Plugin URI:      https://github.com/lancedesk/encrypted-file-sharing
 * Description:     A plugin that allows site administrators to securely send files to specific users via the WordPress admin panel.
 * Author:          Robert June
 * Author URI:      https://profiles.wordpress.org/lancedesk/
 * Text Domain:     encrypted-file-sharing
 * Domain Path:     /languages
 * Version:         1.0.0
 *
 * @package         encrypted-file-sharing
 */

/* Ensure that WordPress functions are available */
if (!defined('ABSPATH')) 
{
    exit; /* Exit if accessed directly */
}

/* Include the necessary files */
require_once plugin_dir_path(__FILE__) . 'includes/classes/class-file-handler.php';
require_once plugin_dir_path(__FILE__) . 'includes/classes/class-encryption.php';
require_once plugin_dir_path(__FILE__) . 'includes/classes/class-notification.php';
require_once plugin_dir_path(__FILE__) . 'includes/classes/class-user-dashboard.php';
require_once plugin_dir_path(__FILE__) . 'includes/classes/class-2fa-auth.php';
require_once plugin_dir_path(__FILE__) . 'includes/classes/class-admin-protection.php';