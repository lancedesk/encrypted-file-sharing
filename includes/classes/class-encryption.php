<?php

class EFS_Encryption
{
    private $logger;

    public function __construct()
    {
        $this->logger = new EFS_Init();
    }

    /**
     * Save the encrypted symmetric key for a specific user and file.
     *
     * @param int $post_id The ID of the post (file).
     * @param int $user_id The ID of the user.
     * @param int $file_id The ID of the file (from `efs_file_metadata`).
     * @param string $data_encryption_key The encrypted key to be saved.
     * @param string $expiration_date The expiration date of the encryption key.
    */

    public function efs_save_encrypted_key($post_id, $selected_users, $file_id, $data_encryption_key, $expiration_date)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'efs_encryption_keys';

        /* Log received parameters */
        $this->logger->log_encryption_message("Selected users: " . implode(', ', $selected_users));
        $this->logger->log_encryption_message("Post ID: $post_id");
        $this->logger->log_encryption_message("File ID: $file_id");
        $this->logger->log_encryption_message("Data Encryption Key received: $data_encryption_key");
        $this->logger->log_encryption_message("Expiration date: $expiration_date");

        /* Loop through the selected users and insert encryption key for each */
        foreach ($selected_users as $user_id)
        {
            /* Generate a unique Key Encryption Key (KEK) for each user */
            $user_kek = openssl_random_pseudo_bytes(32); /* 256-bit key */

            /* Encrypt the KEK with the master key */
            $encrypted_kek = $this->efs_encrypt_with_master_key($user_kek);

            if ($encrypted_kek === false)
            {
                /* Log encryption failure */
                $this->logger->log_encryption_message("Error: Encryption of KEK failed for user ID: $user_id.");
                continue;
            }

            /* Use the first 16 bytes of KEK as the IV for AES-256-CBC */
            $iv = substr($user_kek, 0, 16);

            /* Encrypt the DEK with the user's KEK using AES-256-CBC */
            $encrypted_dek = openssl_encrypt($data_encryption_key, 'AES-256-CBC', $user_kek, 0, $iv);

            if ($encrypted_dek === false)
            {
                /* Log encryption failure */
                $this->logger->log_encryption_message("Error: Encryption of DEK failed for user ID: $user_id.");
                continue;
            }

            /* Fetch the current highest version for this user and file */
            $current_version = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT MAX(version) FROM $table_name WHERE user_id = %d AND file_id = %d",
                    $user_id,
                    $file_id
                )
            );

            /* If no previous version exists, start from 1 */
            $new_version = is_null($current_version) ? 1 : $current_version + 1;

            /* Save the encrypted DEK and KEK with the new version */
            /* phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Reason for direct query: Custom table insertion required */
            $result = $wpdb->insert(
                $table_name,
                [
                    'post_id' => $post_id,
                    'user_id' => $user_id,
                    'file_id' => $file_id,
                    'encryption_key' => $encrypted_dek, /* Store encrypted DEK */
                    'user_kek' => $encrypted_kek, /* Save encrypted KEK */
                    'expiration_date' => $expiration_date !== null ? $expiration_date : null, /* Null if no expiration */
                    'created_at' => current_time('mysql'),
                    'version' => $new_version /* Incremented version */
                ],
                [
                    '%d', /* post_id */
                    '%d', /* user_id */
                    '%d', /* file_id */
                    '%s', /* encrypted_dek */
                    '%s', /* user_kek */
                    '%s', /* expiration_date %s for DATETIME */
                    '%s', /* created_at */
                    '%d'  /* version */
                ]
            );

            if ($result === false)
            {
                /* Log insertion failure */
                $this->logger->log_encryption_message("Error: Failed to insert data into database for user ID: $user_id.");
                return false;  /* Return false immediately if database insertion fails */
            } else {
                /* Log successful insertion */
                $this->logger->log_encryption_message("Successfully saved encrypted keys for user ID: $user_id.");
            }
        }

        /* If everything succeeded, return true */
        return true;
    }

    /**
     * Retrieve and decrypt the Data Encryption Key (DEK) for a file.
     *
     * @param int $user_id The ID of the user who owns the file.
     * @param string $file_name The name of the file for which the DEK is needed.
     * @return string|false Returns the decrypted DEK if successful, false on failure.
    */

    public function efs_get_encryption_key($user_id, $file_name)
    {
        global $wpdb;

        /* Attempt to get the cached result */
        $cache_key = 'encryption_key_' . $user_id . '_' . md5($file_name);
        $cached_result = wp_cache_get($cache_key, 'efs_encryption_keys');

        if ($cached_result !== false)
        {
            /* Return the cached result */
            return $cached_result;
        }

        /* Query to get the file ID from the efs_files table */
        $file_id = $wpdb->get_var(
            $wpdb->prepare(
                "
                SELECT id
                FROM {$wpdb->prefix}efs_files
                WHERE file_name = %s
                ",
                $file_name
            )
        );

        if (!$file_id)
        {
            /* No file found with the specified name */
            $this->logger->log_encryption_message("No file found with file name $file_name.");
            return false;
        }

        /* Query to get the encrypted DEK and KEK for the specific user and file */
        /* phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Reason: Custom table query required */
        $result = $wpdb->get_row(
            $wpdb->prepare(
                "
                SELECT encryption_key, user_kek
                FROM {$wpdb->prefix}efs_encryption_keys ek
                WHERE user_id = %d
                AND file_id = %d
                ",
                $user_id, $file_id
            )
        );

        if (!$result)
        {
            /* No key found for the specified user and file */
            $this->logger->log_encryption_message("No key found for user ID $user_id and file name $file_name of id $file_id.");
            return false;
        }

        /* Get the encrypted KEK and DEK from the database */
        $encrypted_kek = $result->user_kek;
        $encrypted_dek = $result->encryption_key;

        /* Decrypt the KEK with the master key */
        $user_kek = $this->efs_decrypt_with_master_key($encrypted_kek);

        if ($user_kek === false)
        {
            $this->logger->log_encryption_message("Failed to decrypt KEK for user ID $user_id and file name $file_name.");
            return false;
        }

        /* Use the first 16 bytes of KEK as IV (since it was used for encryption) */
        $iv = substr($user_kek, 0, 16);

        /* Decrypt the DEK using KEK */
        $decrypted_dek = openssl_decrypt($encrypted_dek, 'AES-256-CBC', $user_kek, 0, $iv);
        
        /* Log IV and key lengths for debugging */
        if ($decrypted_dek === false) {
            $this->logger->log_encryption_message("Failed to decrypt DEK for user ID $user_id and file name $file_name. OpenSSL error: " . openssl_error_string());
            return false;
        }

        /* Cache the result */
        wp_cache_set($cache_key, $decrypted_dek, 'efs_encryption_keys', 3600); /* Cache for 1 hour */

        return $decrypted_dek; /* Return the decrypted DEK */
    }

    /**
     * Encrypt the file using OpenSSL.
     *
     * @param string $file_path The file path to encrypt.
     * @param string $data_encryption_key The data encryption key to use.
     * @return string|false The path to the encrypted file on success, false on failure.
    */

    public function efs_encrypt_file($file_path, $data_encryption_key)
    {
        global $wp_filesystem;
    
        /* Initialize WP_Filesystem */
        if (!function_exists('WP_Filesystem')) 
        {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        }
    
        WP_Filesystem(); /* Initialize WP_Filesystem */
    
        $output_file = $file_path . '.enc';
        $iv = openssl_random_pseudo_bytes(16); /* Initialization vector for AES-256-CBC */
    
        /* Check if file exists and get file content */
        if (!$wp_filesystem->exists($file_path))
        {
            return false;
        }
    
        /* Read the file content using WP_Filesystem */
        $file_data = $wp_filesystem->get_contents($file_path);
        if ($file_data === false) 
        {
            return false;
        }
    
        /* Encrypt the file content using the DEK */
        $encrypted_data = openssl_encrypt($file_data, 'AES-256-CBC', $data_encryption_key, 0, $iv);
        if ($encrypted_data === false) 
        {
            return false;
        }
    
        /* Write the IV and encrypted data to a new file using WP_Filesystem */
        $wp_filesystem->put_contents($output_file, $iv . $encrypted_data, FS_CHMOD_FILE);
    
        /* Remove the original file for security using wp_delete_file() */
        wp_delete_file($file_path);
    
        return $output_file;
    }    

    /**
     * Retrieve the master key from the custom database table.
     *
     * @return string|false The master key as raw bytes, or false if retrieval fails.
    */

    private function efs_get_master_key()
    {
        global $wpdb;

        /* Attempt to get the cached master key */
        $cache_key = 'master_key';
        $cached_master_key = wp_cache_get($cache_key, 'efs_master_key');

        if ($cached_master_key !== false)
        {
            /* Return the cached master key */
            return $cached_master_key;
        }

        /* Query to get the master key */
        /* phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Reason: Custom table query required */
        $master_key = $wpdb->get_var("SELECT master_key FROM {$wpdb->prefix}efs_master_key LIMIT 1");

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

        /* Cache the master key for future requests */
        wp_cache_set($cache_key, $master_key, 'efs_master_key', 3600); /* Cache for 1 hour */

        return $master_key;
    }

    /**
     * Decrypt the file using the decrypted DEK.
     *
     * @param string $encrypted_file_path The path to the encrypted file.
     * @param string $decrypted_dek The decrypted data encryption key.
     * @return string|false Returns the decrypted file content if successful, false on failure.
    */

    public function efs_decrypt_file($encrypted_file_path, $decrypted_dek)
    {
        global $wp_filesystem;

        /* Initialize the WP_Filesystem */
        if (!function_exists('WP_Filesystem')) 
        {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        }

        WP_Filesystem(); /* Set up the WP_Filesystem */

        /* Read the encrypted file data using WP_Filesystem */
        $encrypted_data = $wp_filesystem->get_contents($encrypted_file_path);

        if ($encrypted_data === false) 
        {
            $this->logger->log_encryption_message("Error: Unable to read encrypted file.");
            return false;
        }

        /* Separate IV (first 16 bytes) from the encrypted data */
        $iv = substr($encrypted_data, 0, 16);
        $ciphertext = substr($encrypted_data, 16);

        /* Decrypt the file content using the DEK */
        $decrypted_data = openssl_decrypt($ciphertext, 'AES-256-CBC', $decrypted_dek, 0, $iv);

        if ($decrypted_data === false)
        {
            $this->logger->log_encryption_message("Error: Decryption failed.");
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

    private function efs_encrypt_with_master_key($user_kek)
    {
        $master_key = $this->efs_get_master_key();

        if ($master_key === false)
        {
            error_log('Master key retrieval failed.');
            return false;
        }

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

        return $encrypted_kek_with_iv;
    }

    /**
     * Decrypt the Key Encryption Key (KEK) with the master key.
     *
     * @param string $encrypted_kek_with_iv The encrypted KEK to decrypt.
     * @return string|false The decrypted KEK if successful, false on failure.
    */

    private function efs_decrypt_with_master_key($encrypted_kek_with_iv)
    {
        $master_key = $this->efs_get_master_key();

        if ($master_key === false)
        {
            error_log('Master key retrieval failed.');
            return false;
        }

        /* Decode the base64 encoded data */
        $decoded_data = base64_decode($encrypted_kek_with_iv);

        /* Extract the first 16 bytes as the IV */
         $iv = substr($decoded_data, 0, 16);

        /* Extract the remaining bytes as the encrypted KEK */
        $encrypted_kek = substr($decoded_data, 16);

        /* Decrypt the KEK with the master key using AES-256-CBC */
        $user_kek = openssl_decrypt($encrypted_kek, 'AES-256-CBC', $master_key, 0, $iv);

        if ($user_kek === false)
        {
            error_log('Error: Decryption of KEK failed.');
            return false;
        }

        return $user_kek;
    }

    
    /**
     * Search for the DEK based on the file name.
     * 
     * @param string $file_name The name of the file to search for.
     * 
     * @return array An array containing 'found' as a boolean and 'dek' as the DEK if found.
    */

    public function efs_get_dek_by_file_name($file_name)
    {
        global $wpdb;

        /* Table names */
        $files_table = $wpdb->prefix . 'efs_files';
        $encrypted_files_table = $wpdb->prefix . 'efs_encrypted_files';

        /* Search for the file by name in the files table */
        $file = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id FROM $files_table WHERE file_name = %s",
                $file_name
            )
        );

        /* Check if the file exists in the files table */
        if ($file !== null)
        {
            /* Use the file ID to search in the encrypted files table */
            $encrypted_file = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT data_encryption_key, encrypted_file FROM $encrypted_files_table WHERE file_id = %d",
                    $file->id
                )
            );

            /* If the encrypted file is found, return the DEK and path to the encrypted file */
            if ($encrypted_file !== null)
            {
                return [
                    'found' => true,
                    'dek' => $encrypted_file->data_encryption_key,
                    'encrypted_file_path' => $encrypted_file->encrypted_file,
                    'file_id' => $file->id
                ];
            }
        }

        /* If no DEK or file path is found, return false */
        return [
            'found' => false,
            'dek' => null,
            'encrypted_file_path' => null,
            'file_id' => null
        ];
    }
}