<?php
/*
 * Plugin Name: Encrypted File Sharing
 * Plugin URI: https://github.com/lancedesk/encrypted-file-sharing
 * Description: A plugin that allows site administrators to securely send files to specific users via the WordPress admin panel.
 * Version: 1.2.5
 * Author: Robert June
 * Author URI: https://profiles.wordpress.org/lancedesk/
 * Text Domain: encrypted-file-sharing
 * Domain Path: /languages
 * Requires at least: 4.7
 * Tested up to: 6.5
 * Requires PHP: 7.0
 * Stable tag: 1.2.5
 * Beta tag: 1.3.0
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @package encrypted-file-sharing
*/

/* Ensure that WordPress functions are available */
if (!defined('ABSPATH')) 
{
    exit; /* Exit if accessed directly */
}

/* Include Composer autoloader 
require_once plugin_dir_path(__FILE__) . 'vendor/autoload.php'; */

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
require_once plugin_dir_path(__FILE__) . 'includes/functions/script-enqueues.php';
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
register_activation_hook(__FILE__, [$efs_init, 'efs_create_encrypted_files_table']);
register_activation_hook(__FILE__, [$efs_init, 'efs_create_file_metadata_table']);
register_activation_hook(__FILE__, [$efs_init, 'efs_create_master_key_table']);
register_activation_hook(__FILE__, [$efs_init, 'efs_create_recipients_table']);
register_activation_hook(__FILE__, [$efs_init, 'efs_create_private_folder']);
register_activation_hook(__FILE__, [$efs_init, 'efs_generate_master_key']);
register_activation_hook(__FILE__, [$efs_init, 'efs_create_admin_table']);
/* register_activation_hook(__FILE__, 'efs_install_dependencies'); */