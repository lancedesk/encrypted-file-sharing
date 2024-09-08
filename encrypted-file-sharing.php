<?php
/**
 * Plugin Name:     Encrypted File Sharing
 * Plugin URI:      https://github.com/lancedesk/encrypted-file-sharing
 * Description:     A plugin that allows site administrators to securely send files to specific users via the WordPress admin panel.
 * Author:          Robert June
 * Author URI:      https://profiles.wordpress.org/lancedesk/
 * Text Domain:     encrypted-file-sharing
 * Domain Path:     /languages
 * Version:         1.2.4
 *
 * @package         encrypted-file-sharing
*/

/* Ensure that WordPress functions are available */
if (!defined('ABSPATH')) 
{
    exit; /* Exit if accessed directly */
}

/* Include Composer autoloader 
require_once plugin_dir_path(__FILE__) . 'vendor/autoload.php'; */

/* Enqueue frontend styles and scripts */
add_action('wp_enqueue_scripts', 'efs_enqueue_frontend_scripts');
function efs_enqueue_frontend_scripts()
{
    /* Enqueue frontend CSS */
    wp_enqueue_style('efs-frontend-css', plugin_dir_url(__FILE__) . 'assets/css/frontend.css', array(), '1.0.0');
    
    /* Enqueue frontend JS */
    wp_enqueue_script('efs-frontend-js', plugin_dir_url(__FILE__) . 'assets/js/frontend.js', array('jquery'), '1.0.0', true);

    /* Localize script with data */
    wp_localize_script('efs-frontend-js', 'efsAdminAjax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('efs_download_nonce')
    ));
}

/* Enqueue admin styles and scripts */
add_action('admin_enqueue_scripts', 'efs_enqueue_admin_scripts');
function efs_enqueue_admin_scripts($hook_suffix)
{
    global $post;
    $post_type = get_post_type();

    /* Ensure we are editing an efs_file post type or on the EFS settings page */
    if (($hook_suffix === 'post-new.php' || 
         $hook_suffix === 'post.php' || 
         $hook_suffix === 'edit.php' ||
         (isset($_GET['page']) && $_GET['page'] === 'efs-settings')) && 
         ($post_type === 'efs_file' || $_GET['page'] === 'efs-settings')
        )
    {
        /* Enqueue admin CSS */
        wp_enqueue_style('efs-admin-css', plugin_dir_url(__FILE__) . 'assets/css/admin.css', array(), '1.0.0');
        
        /* Enqueue admin JS */
        wp_enqueue_script('efs-admin-js', plugin_dir_url(__FILE__) . 'assets/js/admin.js', array('jquery'), '1.0.0', true);

        /* Create a nonce for the AJAX request */
        $nonce = wp_create_nonce('efs_upload_nonce');

        /* Retrieve the storage option from the admin settings */
        $storage_options = get_option('efs_storage_option');

        /* Localize script with data */
        wp_localize_script('efs-admin-js', 'efsAdminAjax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => $nonce,
            'efsStorageOption' => $storage_options,
            'efsSelectFileTitle' => __('Select or Upload a File', 'encrypted-file-sharing'),
            'efsSelectFileButtonText' => __('Use this file', 'encrypted-file-sharing'),
            'efsUploadFailedMessage' => __('File upload failed', 'encrypted-file-sharing'),
            'efsErrorMessage' => __('An error occurred during the file upload', 'encrypted-file-sharing')
            /* 'nonce'    => wp_create_nonce('efs_admin_nonce') */
        ));
    }
}

/* Enqueue S3 handler script for the specific admin page */
add_action('admin_enqueue_scripts', 'efs_enqueue_s3_handler_script');
function efs_enqueue_s3_handler_script($hook_suffix)
{
    /* Check if we are on the correct admin page */
    if (isset($_GET['post_type']) && $_GET['post_type'] === 'efs_file' && isset($_GET['page']) && $_GET['page'] === 'efs-settings') {
        
        /* Enqueue the S3 handler JS */
        wp_enqueue_script('efs-s3-handler', plugin_dir_url(__FILE__) . 'assets/js/s3-handler.js', array('jquery'), '1.0.0', true);

        /* Localize script with data */
        wp_localize_script('efs-s3-handler', 'efs_s3_params', array(
            'efs_s3_nonce' => wp_create_nonce('efs_s3_nonce'),
            'ajax_url'     => admin_url('admin-ajax.php')  /* Ensure the AJAX URL is correct */
        ));
    }
}

/* Include the necessary EFS files */
require_once plugin_dir_path(__FILE__) . 'includes/classes/class-file-expiry-handler.php';
require_once plugin_dir_path(__FILE__) . 'includes/classes/class-admin-settings-page.php';
require_once plugin_dir_path(__FILE__) . 'includes/classes/class-local-file-handler.php';
require_once plugin_dir_path(__FILE__) . 'includes/classes/class-admin-protection.php';
require_once plugin_dir_path(__FILE__) . 'includes/classes/class-s3-file-handler.php';
require_once plugin_dir_path(__FILE__) . 'includes/classes/class-user-dashboard.php';
require_once plugin_dir_path(__FILE__) . 'includes/classes/class-user-selection.php';
require_once plugin_dir_path(__FILE__) . 'includes/classes/class-admin-columns.php';
require_once plugin_dir_path(__FILE__) . 'includes/classes/class-notification.php';
require_once plugin_dir_path(__FILE__) . 'includes/classes/class-file-handler.php';
require_once plugin_dir_path(__FILE__) . 'includes/classes/class-admin-users.php';
require_once plugin_dir_path(__FILE__) . 'includes/classes/class-encryption.php';
require_once plugin_dir_path(__FILE__) . 'includes/classes/class-2fa-auth.php';
require_once plugin_dir_path(__FILE__) . 'includes/classes/class-file-cpt.php';
require_once plugin_dir_path(__FILE__) . 'includes/classes/class-display.php';
require_once plugin_dir_path(__FILE__) . 'includes/classes/class-init.php';
require_once plugin_dir_path(__FILE__) . 'includes/functions/user-permissions.php';
require_once plugin_dir_path(__FILE__) . 'includes/functions/install-dependencies.php';

/* Instantiate the necessary EFS classes */
$efs_local_file_handler = new EFS_Local_File_Handler();
$efs_s3_file_handler = new EFS_S3_File_Handler();
$create_admin_db_table = new EFS_Admin_Users();
$efs_user_selection = new EFS_User_Selection();
$efs_admin_columns = new EFS_Admin_Columns();
$efs_file_encryption = new EFS_Encryption();
$efs_file_handler =  new EFS_File_Handler(
    $efs_s3_file_handler,
    $efs_local_file_handler,
    $efs_file_encryption
);

new EFS_File_Expiry_Handler();
new EFS_Admin_Settings_Page();
$efs_init = new EFS_Init();
new EFS_File_Display();
new EFS_File_CPT();

/* EFS activation hooks */
register_activation_hook(__FILE__, [$efs_init, 'efs_create_encryption_keys_table']);
register_activation_hook(__FILE__, [$efs_init, 'efs_create_file_metadata_table']);
register_activation_hook(__FILE__, [$efs_init, 'efs_create_master_key_table']);
register_activation_hook(__FILE__, [$efs_init, 'efs_create_recipients_table']);
register_activation_hook(__FILE__, [$efs_init, 'efs_create_private_folder']);
register_activation_hook(__FILE__, [$efs_init, 'efs_generate_master_key']);
register_activation_hook(__FILE__, [$efs_init, 'efs_create_admin_table']);
/* register_activation_hook(__FILE__, 'efs_install_dependencies'); */