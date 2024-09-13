<?php

class EFS_Init
{
    public function __construct()
    {
        /* Register the activation hook */
        $this->efs_init();
    }

    public function efs_init()
    {
        /* Create the database tables */
        $this->efs_create_files_table();
        $this->efs_create_file_metadata_table();
        $this->efs_create_encryption_keys_table();
        $this->efs_create_master_key_table();
        $this->efs_create_encrypted_files_table();
        $this->efs_create_recipients_table();
        $this->efs_create_admin_table();

        /* Generate and save the master key */
        $this->efs_generate_master_key();

        /* Create the private folder outside the web root */
        $this->efs_create_private_folder();
    }

    /**
     * Create the `efs_files` table in the database.
    */

    public function efs_create_files_table()
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'efs_files';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id INT NOT NULL AUTO_INCREMENT,
            file_name VARCHAR(255) NOT NULL,
            encrypted_file_path VARCHAR(255) NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY file_name (file_name)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql); /* Ensures the table is created or updated if it already exists */
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
            file_id INT NOT NULL,  -- Reference to the file in `efs_files`
            post_id INT NOT NULL,
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
            post_id INT NOT NULL,
            user_id INT NOT NULL,
            file_id INT NOT NULL,  -- Reference to the file in `efs_file_metadata`
            encryption_key BLOB NOT NULL,
            user_kek BLOB NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            expiration_date DATETIME NULL, -- Allow NULL values
            download_date DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE (user_id, file_id)  -- Ensure each user gets unique key/expiry for a file
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql); /* Ensures the table is created or updated if it already exists */
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
     * Create the database table for storing encrypted file metadata.
    */

    public function efs_create_encrypted_files_table()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'efs_encrypted_files';

        /* SQL to create the table */
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            file_id INT(11) NOT NULL,
            post_id INT(11) NOT NULL,
            data_encryption_key BLOB NOT NULL,
            expiration_date DATETIME NULL, -- Allow NULL values
            encrypted_file VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql); /* Ensures the table is created or updated if it already exists */
    }

    /**
     * Generate and save the master key to the custom database table.
    */

    public function efs_generate_master_key()
    {
        global $wpdb;
        $cache_key = 'efs_master_key_cache';
        $table_name = $wpdb->prefix . 'efs_master_key';

        /* Attempt to retrieve master key from cache */
        $existing_key = wp_cache_get($cache_key);

        if ($existing_key === false)
        {
            /* Cache miss, retrieve from database */
            /* phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table query, caching not applicable */
            $existing_key = $wpdb->get_var(
                $wpdb->prepare("SELECT master_key FROM {$wpdb->prefix}efs_master_key LIMIT 1")
            );

            /* Store the key in cache for future use, if found */
            if ($existing_key !== null)
            {
                wp_cache_set($cache_key, $existing_key, '', DAY_IN_SECONDS);
            }
        }

        if ($existing_key !== null)
        {
            /* A master key already exists, do nothing */
            error_log('Master key already exists, no action taken.');
            return;
        }

        /* Generate a new master key */
        $master_key = openssl_random_pseudo_bytes(32);

        /* Save the new master key */
        /* phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table query, caching not applicable */
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
            /* Store the new key in cache */
            wp_cache_set($cache_key, $master_key, '', DAY_IN_SECONDS);
            error_log('New master key saved successfully.');
        }
    }

    /**
     * Create the 'efs_recipients' table to store post-to-recipient relationships.
     *
     * @return void
    */

    public function efs_create_recipients_table()
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'efs_recipients';

        /* SQL to create the table */
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE $table_name (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id BIGINT(20) UNSIGNED NOT NULL,
            recipient_id BIGINT(20) UNSIGNED NOT NULL,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY recipient_id (recipient_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
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
     * Create a private folder outside the web root.
    */

    public function efs_create_private_folder() 
    {
        /* Load the WP_Filesystem API */
        require_once ABSPATH . 'wp-admin/includes/file.php';

        /* Initialize the WP_Filesystem object */
        global $wp_filesystem;
        if (empty($wp_filesystem)) {
            WP_Filesystem();
        }

        /* Path outside the web root */
        $private_dir = ABSPATH . '../private_uploads/';

        /* Log file path */
        $log_file = WP_CONTENT_DIR . '/efs_folder_creation_log.txt';

        /* Check if the folder already exists */
        if (!$wp_filesystem->is_dir($private_dir)) 
        {
            /* Try to create the folder */
            if ($wp_filesystem->mkdir($private_dir, 0755)) 
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
     *  Function to log messages
     * @param string $log_file
     * @param string $message
    */

    public function log_message($log_file, $message)
    {
        /* Ensure WP_Filesystem is available */
        if ( ! function_exists('get_filesystem_method') )
        {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
    
        global $wp_filesystem;
    
        /* Initialize WP_Filesystem */
        if ( empty( $wp_filesystem ) )
        {
            WP_Filesystem();
        }
    
        /* Get current time and prepare log message */
        $current_time = gmdate('Y-m-d H:i:s');
        $log_message = "{$current_time} - {$message}\n";
    
        /* Check if file exists */
        if ( $wp_filesystem->exists( $log_file ) )
        {
            /* Append if file exists */
            $current_contents = $wp_filesystem->get_contents( $log_file );
            $new_contents = $current_contents . $log_message;
            $wp_filesystem->put_contents( $log_file, $new_contents, FS_CHMOD_FILE );
        }
        else
        {
            /* Create new file if it doesn't exist */
            $wp_filesystem->put_contents( $log_file, $log_message, FS_CHMOD_FILE );
        }
    }
}