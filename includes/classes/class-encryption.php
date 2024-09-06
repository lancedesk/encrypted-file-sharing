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

    public function encrypt_file($file_path, $encryption_key)
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
        unlink($file_path); /* Remove the original file */

        return $output_file;
    }

    /**
     * Decrypt an encrypted file using OpenSSL.
     *
     * @param string $encrypted_file_path The path to the encrypted file.
     * @param string $encryption_key The encryption key to decrypt the file.
     * @return string|false The decrypted file contents on success, false on failure.
    */

    public function decrypt_file($encrypted_file_path, $encryption_key)
    {
        /* Read the encrypted file data */
        $encrypted_data = file_get_contents($encrypted_file_path);
        if ($encrypted_data === false) {
            return false;
        }

        /* Separate IV (first 16 bytes) from the encrypted data */
        $iv = substr($encrypted_data, 0, 16);
        $ciphertext = substr($encrypted_data, 16);

        /* Decrypt the file content */
        $decrypted_data = openssl_decrypt($ciphertext, 'AES-256-CBC', $encryption_key, 0, $iv);

        if ($decrypted_data === false) {
            return false;
        }

        return $decrypted_data;
    }
    
    /**
     * Save the encrypted symmetric key in the database
    */

    public function save_encrypted_key($file_name, $encryption_key)
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

    public function get_encryption_key($file_name)
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