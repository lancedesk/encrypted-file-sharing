<?php

class EFS_Encryption
{
    public function __construct()
    {
        /* TODO: Implement encryption */
    }

    /**
     * Logs messages to a file.
     *
     * @param string $message The message to log.
    */

    private function log_message($message)
    {
        $log_file = WP_CONTENT_DIR . '/efs_encryption_log.txt';
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
    }

    /**
     * Save the encrypted symmetric key for a specific user and file.
     *
     * @param int $user_id The ID of the user.
     * @param int $file_id The ID of the file (from `efs_file_metadata`).
     * @param string $data_encryption_key The encrypted key to be saved.
     * @param string $expiration_date The expiration date of the encryption key.
    */

    public function save_encrypted_key($selected_users, $file_id, $data_encryption_key, $expiration_date)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'efs_encryption_keys';

        /* Log received parameters */
        $this->log_message("Selected users: " . implode(', ', $selected_users));
        $this->log_message("File ID: $file_id");
        $this->log_message("Data Encryption Key received: $data_encryption_key");
        $this->log_message("Expiration date: $expiration_date");

        /* Loop through the selected users and insert encryption key for each */
        foreach ($selected_users as $user_id)
        {
            /* Generate a unique Key Encryption Key (KEK) for each user */
            $user_kek = openssl_random_pseudo_bytes(32); /* 256-bit key */

            /* Encrypt the KEK with the master key */
            $encrypted_kek = $this->encrypt_with_master_key($user_kek);

            if ($encrypted_kek === false)
            {
                /* Log encryption failure */
                $this->log_message("Error: Encryption of KEK failed for user ID: $user_id.");
                continue;
            }

            /* Use the first 16 bytes of KEK as the IV for AES-256-CBC */
            $iv = substr($user_kek, 0, 16);

            /* Encrypt the DEK with the user's KEK using AES-256-CBC */
            $encrypted_dek = openssl_encrypt($data_encryption_key, 'AES-256-CBC', $user_kek, 0, $iv);

            if ($encrypted_dek === false)
            {
                /* Log encryption failure */
                $this->log_message("Error: Encryption of DEK failed for user ID: $user_id.");
                continue;
            }

            /* Save the encrypted DEK and KEK */
            $result = $wpdb->insert(
                $table_name,
                [
                    'user_id' => $user_id,
                    'file_id' => $file_id,
                    'encryption_key' => $encrypted_dek, /* Store encrypted DEK */
                    'user_kek' => $encrypted_kek, /* Save encrypted KEK */
                    'expiration_date' => $expiration_date,
                    'created_at' => current_time('mysql')
                ],
                [
                    '%d', /* user_id */
                    '%d', /* file_id */
                    '%s', /* encrypted_dek */
                    '%s', /* user_kek */
                    '%s', /* expiration_date */
                    '%s'  /* created_at */
                ]
            );

            if ($result === false) {
                /* Log insertion failure */
                $this->log_message("Error: Failed to insert data into database for user ID: $user_id.");
            } else {
                /* Log successful insertion */
                $this->log_message("Successfully saved encrypted keys for user ID: $user_id.");
            }
        }
    }


    /**
     * Retrieve and decrypt the Data Encryption Key (DEK) for a file.
     *
     * @param int $user_id The ID of the user who owns the file.
     * @param string $file_name The name of the file for which the DEK is needed.
     * @return string|false Returns the decrypted DEK if successful, false on failure.
    */

    public function get_encryption_key($user_id, $file_name)
    {
        global $wpdb;
        $file_metadata_table = $wpdb->prefix . 'efs_file_metadata';
        $encryption_keys_table = $wpdb->prefix . 'efs_encryption_keys';

        /* Query to get the encrypted DEK and KEK for the specific user and file */
        $query = $wpdb->prepare(
            "SELECT ek.encryption_key, ek.user_kek
            FROM $encryption_keys_table ek
            INNER JOIN $file_metadata_table fm
            ON ek.file_id = fm.id
            WHERE ek.user_id = %d
            AND fm.file_name = %s",
            $user_id, $file_name
        );

        $result = $wpdb->get_row($query);

        if (!$result) {
            /* No key found for the specified user and file */
            $this->log_message("No key found for user ID $user_id and file name $file_name.");
            return false;
        }

        /* Get the encrypted KEK and DEK from the database */
        $encrypted_kek = $result->user_kek;
        $encrypted_dek = $result->encryption_key;

        /* Decrypt the KEK with the master key */
        $user_kek = $this->decrypt_with_master_key($encrypted_kek);

        if ($user_kek === false)
        {
            $this->log_message("Failed to decrypt KEK for user ID $user_id and file name $file_name.");
            return false;
        }

        /* Use the first 16 bytes of KEK as IV (since it was used for encryption) */
        $iv = substr($user_kek, 0, 16);

        /* Decrypt the DEK using KEK */
        $decrypted_dek = openssl_decrypt($encrypted_dek, 'AES-256-CBC', $user_kek, 0, $iv);
        
        /* Log IV and key lengths for debugging */
        if ($decrypted_dek === false) {
            $this->log_message("Failed to decrypt DEK for user ID $user_id and file name $file_name. OpenSSL error: " . openssl_error_string());
            return false;
        }

        return $decrypted_dek; /* Return the decrypted DEK */
    }

    /**
     * Encrypt the file using OpenSSL.
     *
     * @param string $file_path The file path to encrypt.
     * @param string $data_encryption_key The data encryption key to use.
     * @return string|false The path to the encrypted file on success, false on failure.
    */

    public function encrypt_file($file_path, $data_encryption_key)
    {
        $output_file = $file_path . '.enc';
        $iv = openssl_random_pseudo_bytes(16); /* Initialization vector for AES-256-CBC */

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
     * Retrieve the master key from the custom database table.
     *
     * @return string|false The master key as raw bytes, or false if retrieval fails.
    */

    private function get_master_key()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'efs_master_key';

        /* Query to get the master key */
        $master_key = $wpdb->get_var("SELECT master_key FROM $table_name LIMIT 1");

        if ($master_key === null)
        {
            error_log('No master key found in the database.');
            return false;
        }

        /* Validate the length of the master key (should be 256 bits / 32 bytes) */
        if (strlen($master_key) !== 32)
        {
            error_log('Error: Invalid length of retrieved master key.');
            return false;
        }

        return $master_key;
    }

    /**
     * Decrypt the file using the decrypted DEK.
     *
     * @param string $encrypted_file_path The path to the encrypted file.
     * @param string $decrypted_dek The decrypted data encryption key.
     * @return string|false Returns the decrypted file content if successful, false on failure.
    */

    public function decrypt_file($encrypted_file_path, $decrypted_dek)
    {
        /* Read the encrypted file data */
        $encrypted_data = file_get_contents($encrypted_file_path);

        if ($encrypted_data === false) {
            $this->log_message("Error: Unable to read encrypted file.");
            return false;
        }

        /* Separate IV (first 16 bytes) from the encrypted data */
        $iv = substr($encrypted_data, 0, 16);
        $ciphertext = substr($encrypted_data, 16);

        /* Decrypt the file content using the DEK */
        $decrypted_data = openssl_decrypt($ciphertext, 'AES-256-CBC', $decrypted_dek, 0, $iv);

        if ($decrypted_data === false) {
            $this->log_message("Error: Decryption failed.");
            return false;
        }

        return $decrypted_data; /* Return the decrypted file content */
    }

    /**
     * Encrypt the Key Encryption Key (KEK) with the master key.
     *
     * @param string $user_kek The KEK to encrypt.
     * @return string|false The encrypted KEK if successful, false on failure.
    */

    private function encrypt_with_master_key($user_kek)
    {
        $master_key = $this->get_master_key();

        if ($master_key === false)
        {
            error_log('Master key retrieval failed.');
            return false;
        }

        /* Use the first 16 bytes of KEK as the IV for AES-256-CBC
        $iv = substr($user_kek, 0, 16); */

        /* Generate a random IV (16 bytes for AES-256-CBC) */
        $iv = openssl_random_pseudo_bytes(16);

        /* Encrypt the KEK with the master key using AES-256-CBC */
        $encrypted_kek = openssl_encrypt($user_kek, 'AES-256-CBC', $master_key, 0, $iv);

        if ($encrypted_kek === false)
        {
            error_log('Error: Encryption of KEK failed.');
            return false;
        }

        /* Prepend the IV to the encrypted KEK for storage */
        $encrypted_kek_with_iv = base64_encode($iv . $encrypted_kek);

        return $encrypted_kek;
    }

    /**
     * Decrypt the Key Encryption Key (KEK) with the master key.
     *
     * @param string $encrypted_kek The encrypted KEK to decrypt.
     * @return string|false The decrypted KEK if successful, false on failure.
    */

    private function decrypt_with_master_key($encrypted_kek)
    {
        $master_key = $this->get_master_key();

        if ($master_key === false)
        {
            error_log('Master key retrieval failed.');
            return false;
        }

        /* Decode the base64 encoded data */
        $decoded_data = base64_decode($encrypted_kek);

        /* Extract the first 16 bytes as the IV for AES-256-CBC*/
        $iv = substr($decoded_data, 0, 16);

        /* Extract the remaining bytes as the encrypted KEK */
        $encrypted_kek_without_iv = substr($decoded_data, 16);

        /* Decrypt the KEK with the master key using AES-256-CBC */
        $user_kek = openssl_decrypt($encrypted_kek_without_iv, 'AES-256-CBC', $master_key, 0, $iv);

        if ($user_kek === false)
        {
            error_log('Error: Decryption of KEK failed.');
            return false;
        }

        return $user_kek;
    }

}