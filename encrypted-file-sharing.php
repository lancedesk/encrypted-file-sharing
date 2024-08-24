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

/* Enqueue frontend styles and scripts */
add_action('wp_enqueue_scripts', 'efs_enqueue_frontend_scripts');
function efs_enqueue_frontend_scripts()
{
    /* Enqueue frontend CSS */
    wp_enqueue_style('efs-frontend-css', plugin_dir_url(__FILE__) . 'assets/css/frontend.css', array(), '1.0.0');
    
    /* Enqueue frontend JS */
    wp_enqueue_script('efs-frontend-js', plugin_dir_url(__FILE__) . 'assets/js/frontend.js', array('jquery'), '1.0.0', true);
}

/* Enqueue admin styles and scripts */
add_action('admin_enqueue_scripts', 'efs_enqueue_admin_scripts');
function efs_enqueue_admin_scripts()
{
    /* Enqueue admin CSS */
    wp_enqueue_style('efs-admin-css', plugin_dir_url(__FILE__) . 'assets/css/admin.css', array(), '1.0.0');
    
    /* Enqueue admin JS */
    wp_enqueue_script('efs-admin-js', plugin_dir_url(__FILE__) . 'assets/js/admin.js', array('jquery'), '1.0.0', true);
}

/* Include the necessary files */
require_once plugin_dir_path(__FILE__) . 'includes/classes/class-file-handler.php';
require_once plugin_dir_path(__FILE__) . 'includes/classes/class-encryption.php';
require_once plugin_dir_path(__FILE__) . 'includes/classes/class-notification.php';
require_once plugin_dir_path(__FILE__) . 'includes/classes/class-user-dashboard.php';
require_once plugin_dir_path(__FILE__) . 'includes/classes/class-2fa-auth.php';
require_once plugin_dir_path(__FILE__) . 'includes/classes/class-admin-protection.php';
require_once plugin_dir_path(__FILE__) . 'includes/classes/class-admin-users.php';
require_once plugin_dir_path(__FILE__) . 'includes/classes/class-file-cpt.php';
require_once plugin_dir_path(__FILE__) . 'includes/classes/class-admin-columns.php';
require_once plugin_dir_path(__FILE__) . 'includes/classes/class-display.php';
require_once plugin_dir_path(__FILE__) . 'includes/functions/user-permissions.php';

/* Hook for plugin activation to create the table */
register_activation_hook(__FILE__, 'create_admin_table');

/* Instantiate the necessary classes */
new EFS_File_CPT();
$efs_admin_columns = new EFS_Admin_Columns();
new EFS_File_Display();