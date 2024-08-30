<?php

class EFS_Encryption
{
    public function __construct()
    {
        /* TODO: Implement encryption */
    }

    /**
     * Encrypt the file using OpenSSL.
     *
     * @param string $file_path The file path to encrypt.
     * @param string $encryption_key A key to encrypt the file.
     * @return string|false The path to the encrypted file on success, false on failure.
    */

    private function encrypt_file($file_path, $encryption_key)
    {
        $output_file = $file_path . '.enc';
        $iv = openssl_random_pseudo_bytes(16); /* Initialization vector for AES-256-CBC */

        /* Read the file content */
        $file_data = file_get_contents($file_path);
        if ($file_data === false) {
            return false;
        }

        /* Encrypt the file content */
        $encrypted_data = openssl_encrypt($file_data, 'AES-256-CBC', $encryption_key, 0, $iv);
        if ($encrypted_data === false) {
            return false;
        }

        /* Write the IV and encrypted data to a new file */
        file_put_contents($output_file, $iv . $encrypted_data);

        /* Remove the original file for security */
        unlink($file_path);

        return $output_file;
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
            file_name VARCHAR(255) NOT NULL,
            encryption_key BLOB NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY file_name (file_name)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql); /* Ensures the table is created or updated if it already exists */
    }
    
    /**
     * Save the encrypted symmetric key in the database
    */

    private function save_encrypted_key($file_name, $encryption_key)
    {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'efs_encryption_keys',
            [
                'file_name' => $file_name,
                'encryption_key' => $encryption_key, /* Store the key as binary data */
                'created_at' => current_time('mysql')
            ]
        );
    }

    /**
     * Retrieve the encryption key for a file
    */

    private function get_encryption_key($file_name)
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT encryption_key FROM {$wpdb->prefix}efs_encryption_keys WHERE file_name = %s", $file_name),
            ARRAY_A
        );

        if ($row) 
        {
            return $row['encryption_key'];
        }

        return false;
    }


}