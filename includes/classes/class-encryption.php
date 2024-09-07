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
     * @param string $data_encryption_key A key to encrypt the file.
     * @return string|false The path to the encrypted file on success, false on failure.
    */

    public function encrypt_file($file_path, &$data_encryption_key)
    {
        $output_file = $file_path . '.enc';
        $iv = openssl_random_pseudo_bytes(16); /* Initialization vector for AES-256-CBC */

        /* Generate a random DEK (256-bit key for AES encryption) */
        $data_encryption_key = openssl_random_pseudo_bytes(32);

        /* Read the file content */
        $file_data = file_get_contents($file_path);
        if ($file_data === false) {
            return false;
        }

        /* Encrypt the file content using the DEK */
        $encrypted_data = openssl_encrypt($file_data, 'AES-256-CBC', $data_encryption_key, 0, $iv);
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
     * Save the encrypted symmetric key for a specific user and file.
     *
     * @param int $user_id The ID of the user.
     * @param int $file_id The ID of the file (from `efs_file_metadata`).
     * @param string $encryption_key The encrypted key to be saved.
     * @param string $expiration_date The expiration date of the encryption key.
    */

    public function save_encrypted_key($selected_users, $file_id, $encryption_key, $expiration_date)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'efs_encryption_keys';

        /* Loop through the selected users and insert encryption key for each */
        foreach ($selected_users as $user_id)
        {
            $wpdb->insert(
                $table_name,
                [
                    'user_id' => $user_id,
                    'file_id' => $file_id,
                    'encryption_key' => $encryption_key, /* Store the key as binary data */
                    'expiration_date' => $expiration_date,
                    'created_at' => current_time('mysql')
                ],
                [
                    '%d', /* user_id */
                    '%d', /* file_id */
                    '%s', /* encryption_key */
                    '%s', /* expiration_date */
                    '%s'  /* created_at */
                ]
            );
        }
    }

    /**
     * Retrieve the encryption key for a file
    */

    public function get_encryption_key($user_id, $file_name)
    {
        global $wpdb;
        $file_metadata_table = $wpdb->prefix . 'efs_file_metadata';
        $encryption_keys_table = $wpdb->prefix . 'efs_encryption_keys';

        /* Query to get the encryption key for a specific user and file */
        $query = $wpdb->prepare(
            "SELECT ek.encryption_key
            FROM $encryption_keys_table ek
            INNER JOIN $file_metadata_table fm
            ON ek.file_id = fm.id
            WHERE ek.user_id = %d
            AND fm.file_name = %s",
            $user_id, $file_name
        );

        /* Execute the query and return the encryption key */
        $encryption_key = $wpdb->get_var($query);

        return $encryption_key;
    }

}