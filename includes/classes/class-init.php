<?php

class EFS_Init
{
    public function __construct() {
        /* Empty constructor */
    }

    /**
     * Create the `efs_file_metadata` table in the database.
    */

    public function efs_create_file_metadata_table()
    {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'efs_file_metadata';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id INT NOT NULL AUTO_INCREMENT,
            file_name VARCHAR(255) NOT NULL,
            file_path VARCHAR(255) NOT NULL,
            upload_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY file_name (file_name)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql); /* Ensures the table is created or updated if it already exists */
    }

    /**
     * Create the `efs_encryption_keys` table in the database.
     * This method is called during plugin activation.
    */

    public function efs_create_encryption_keys_table()
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'efs_encryption_keys';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id INT NOT NULL AUTO_INCREMENT,
            user_id INT NOT NULL,
            file_id INT NOT NULL,  -- Reference to the file in `efs_file_metadata`
            encryption_key BLOB NOT NULL,
            user_kek BLOB NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            expiration_date DATETIME NOT NULL,
            download_date DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE (user_id, file_id)  -- Ensure each user gets unique key/expiry for a file
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql); /* Ensures the table is created or updated if it already exists */
    }

    /**
     * Create the database table for storing admin user information.
    */

    public function efs_create_admin_table()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'efs_admin_users';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            username varchar(60) NOT NULL,
            password varchar(255) NOT NULL,
            email varchar(100) NOT NULL,
            two_factor_code varchar(255),
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql); /* Ensures the table is created or updated if it already exists */
    }

    /**
     * Create a private folder outside the web root and log events
    */

    public function efs_create_private_folder() 
    {

        /* Path outside the web root */
        $private_dir = ABSPATH . '../private_uploads/';

        /* Log file path */
        $log_file = WP_CONTENT_DIR . '/efs_folder_creation_log.txt';

        /* Check if the folder already exists */
        if (!file_exists($private_dir)) 
        {
            /* Try to create the folder */
            if (mkdir($private_dir, 0755, true)) 
            {
                /* Log success with the absolute path */
                $this->log_message($log_file, 'Private uploads folder created at: ' . realpath($private_dir));
            } 
            else 
            {
                /* Log failure */
                $this->log_message($log_file, 'Failed to create private uploads folder at: ' . $private_dir);
                wp_die('Failed to create private uploads folder. Please create it manually.');
            }
        } 
        else 
        {
            /* Log that the folder already exists */
            $this->log_message($log_file, 'Private uploads folder already exists at: ' . realpath($private_dir));
        }
    }

    /**
     * Create the custom table for storing the master key.
    */

    public function efs_create_master_key_table()
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'efs_master_key';

        /* SQL to create the table */
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE $table_name (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            master_key BLOB NOT NULL,
            PRIMARY KEY (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Generate and save the master key to the custom database table.
    */

    public function efs_generate_master_key()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'efs_master_key';

        /* Check if a master key already exists */
        $existing_key = $wpdb->get_var("SELECT master_key FROM $table_name LIMIT 1");

        if ($existing_key !== null)
        {
            /* Delete the existing master key */
            $deleted = $wpdb->delete($table_name, array('id' => 1));

            if ($deleted === false)
            {
                error_log('Failed to delete the existing master key.');
                return;
            }

            error_log('Master key deleted.');
        }

        /* Generate a new master key */
        $master_key = openssl_random_pseudo_bytes(32);

        /* Save the new master key */
        $inserted = $wpdb->insert(
            $table_name,
            array('master_key' => $master_key),
            array('%s')
        );

        if ($inserted === false)
        {
            error_log('Failed to save the new master key.');
        }
        else
        {
            error_log('New master key saved successfully.');
        }
    }

    private function log_message($file, $message)
    {
        if (file_exists($file)) 
        {
            $current = file_get_contents($file);
            $current .= "[" . date('Y-m-d H:i:s') . "] " . $message . "\n";
            file_put_contents($file, $current);
        } 
        else 
        {
            $timestamp = date('Y-m-d H:i:s');
            $log_message = $timestamp . ' - ' . $message . PHP_EOL;
            file_put_contents($file, $log_message, FILE_APPEND);
        }
    }
}