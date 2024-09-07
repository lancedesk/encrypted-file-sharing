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

        /* Retrieve the master key */
        $master_key = $this->get_master_key();

        if ($master_key === false) {
            /* Log error if master key retrieval fails */
            $this->log_message("Error: Master key retrieval failed.");
            return;
        }

        /* Log master key retrieval success */
        $this->log_message("Master key retrieved successfully.");

        /* Loop through the selected users and insert encryption key for each */
        foreach ($selected_users as $user_id)
        {
            /* Generate a unique Key Encryption Key (KEK) for each user */
            $user_kek = openssl_random_pseudo_bytes(32); /* 256-bit key */

            /* Use the first 16 bytes of KEK as the IV for AES-256-CBC */
            $iv = substr($user_kek, 0, 16);

            /* Encrypt the DEK with the user's KEK using AES-256-CBC */
            $encrypted_dek = openssl_encrypt($data_encryption_key, 'AES-256-CBC', $user_kek, 0, $iv);

            if ($encrypted_dek === false) {
                /* Log encryption failure */
                $this->log_message("Error: Encryption of DEK failed for user ID: $user_id.");
                continue;
            }

            /* Encrypt KEK with a master key */
            $encrypted_kek = openssl_encrypt($user_kek, 'AES-256-CBC', $master_key, 0, $iv);

            if ($encrypted_kek === false) {
                /* Log encryption failure */
                $this->log_message("Error: Encryption of KEK failed for user ID: $user_id.");
                continue;
            }

            /* Base64 encode the encrypted DEK and KEK before saving */
            $encrypted_dek = base64_encode($encrypted_dek);
            $encrypted_kek = base64_encode($encrypted_kek);

            /* Save the encrypted DEK and KEK */
            $result = $wpdb->insert(
                $table_name,
                [
                    'user_id' => $user_id,
                    'file_id' => $file_id,
                    'encryption_key' => $encrypted_dek, /* Store encrypted DEK */
                    'user_kek' => $encrypted_kek,  /* Save encrypted KEK */
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

        /* Retrieve the master key */
        $master_key = $this->get_master_key();

        if ($master_key === false) {
            return false;
        }

        /* Decode the base64 encoded KEK and DEK */
        $encrypted_kek = base64_decode($result->user_kek);
        $encrypted_dek = base64_decode($result->encryption_key);

        /* Log more information about the encrypted data */
        $this->log_message("Raw Encrypted DEK: " . bin2hex($encrypted_dek));
        $this->log_message("Raw Encrypted KEK: " . bin2hex($encrypted_kek));

        /* Use the first 16 bytes of KEK as IV (since it was used for encryption) */
        $iv = substr($encrypted_kek, 0, 16);

        /* Decrypt the KEK using the master key */
        $decrypted_kek = openssl_decrypt($encrypted_kek, 'AES-256-CBC', $master_key, 0, $iv);

        if ($decrypted_kek === false) {
            $this->log_message("Failed to decrypt KEK for user ID $user_id and file name $file_name. OpenSSL error: " . openssl_error_string());
            return false;
        }

        /* Use the first 16 bytes of the decrypted KEK as the IV for DEK decryption */
        $dek_iv = substr($decrypted_kek, 0, 16);

        /* Log decrypted KEK */
        $this->log_message("Decrypted KEK: " . bin2hex($decrypted_kek));

        /* Decrypt the DEK using the decrypted KEK */
        $decrypted_dek = openssl_decrypt($encrypted_dek, 'AES-256-CBC', $decrypted_kek, 0, $dek_iv);

        /* Decrypt the DEK using the KEK */
        $decrypted_dek = openssl_decrypt($encrypted_dek, 'AES-256-CBC', $encrypted_kek, 0, $iv);

        /* Log more information for debugging */
        $this->log_message("DEK IV: " . bin2hex($dek_iv));
        $this->log_message("Decrypted KEK: " . bin2hex($decrypted_kek));
        $this->log_message("Decrypted KEK length: " . strlen($decrypted_kek));
        $this->log_message("DEK IV length: " . strlen($dek_iv));

        $this->log_message("Encrypted DEK: " . base64_encode($encrypted_dek));
        
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
     * Retrieves and decodes the stored master key from the WordPress options table.
     *
     * @return string|false Returns the decoded master key as a string if successful,
     *                      or false if the master key doesn't exist.
    */

    public function get_master_key() 
    {
        $master_key = get_option('efs_master_key');

        if ($master_key === false) 
        {
            error_log('Master key not found.');
            return false;
        }

        $decoded_key = base64_decode($master_key);

        if ($decoded_key === false) 
        {
            error_log('Failed to decode master key.');
            return false;
        }

        return $decoded_key;
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

}