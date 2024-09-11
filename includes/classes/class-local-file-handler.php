<?php

class EFS_Local_File_Handler
{

    /**
     * Constructor to initialize actions and hooks.
    */

    public function __construct()
    {
        /* Initialize the EFS encryption class. */
        add_action('wp_ajax_efs_upload_to_local', [$this, 'handle_local_upload_ajax']);
        add_action('wp_ajax_nopriv_efs_upload_to_local', [$this, 'handle_local_upload_ajax']);
        add_action('wp_ajax_efs_write_log', [$this, 'write_log']);
        add_action('wp_ajax_nopriv_efs_write_log', [$this, 'write_log']);
    }

    /**
     * Handle the local file upload.
    */

    public function handle_local_upload()
    {
        global $wp_filesystem, $efs_file_handler, $efs_file_encryption;
        $upload_dir = ABSPATH . '../private_uploads/';

        /* Initialize WP_Filesystem */
        if (!function_exists('WP_Filesystem')) 
        {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        }

        WP_Filesystem(); /* Set up WP_Filesystem */

        if (!$wp_filesystem->is_dir($upload_dir)) 
        {
            if ($wp_filesystem->mkdir($upload_dir, FS_CHMOD_DIR)) 
            {
                $this->log_message(WP_CONTENT_DIR . '/efs_upload_log.txt', 'Created directory: ' . $upload_dir);
            } 
            else 
            {
                $this->log_message(WP_CONTENT_DIR . '/efs_upload_log.txt', 'Failed to create directory: ' . $upload_dir);
                return false;
            }
        }

        /* Calculate the expiration date */
        $expiration_date = $this->calculate_expiration_date();

        /* Log file path */
        $log_file = WP_CONTENT_DIR . '/efs_upload_log.txt';

        /* Verify the nonce */
        if (!isset($_POST['nonce']) || !wp_verify_nonce(wp_unslash(sanitize_key($_POST['nonce'])), 'efs_upload_nonce'))
        {
            $this->log_message($log_file, 'Invalid nonce.');
            wp_send_json_error(['message' => 'Invalid nonce.']);
        }

        /* Ensure file ID is provided */
        if (!isset($_POST['file_id'])) {
            wp_send_json_error(['message' => 'File ID is missing.']);
        }

        /* Ensure post ID is provided */
        if (!isset($_POST['post_id'])) {
            wp_send_json_error(['message' => 'Post ID is missing.']);
        }

        $file_id = intval($_POST['file_id']);
        $post_id = intval($_POST['post_id']);

        /* Retrieve file information */
        $file_path = get_attached_file($file_id);
        $file_name = basename($file_path);
        $target_file = $upload_dir . $file_name;
        
        if (!$file_path || !file_exists($file_path)) {
            wp_send_json_error(['message' => 'File does not exist.']);
        }

        /* Log the received file and expiration date */
        $this->log_message($log_file, 'Received file path: ' . $file_path);
        $this->log_message($log_file, 'Expiration date: ' . $expiration_date);

        /* Copy the file to the secure directory */
        if (copy($file_path, $target_file)) {
            $this->log_message(WP_CONTENT_DIR . '/efs_upload_log.txt', 'File copied to: ' . $target_file);
        
            /* Generate a random DEK (256-bit key for AES encryption) */
            $data_encryption_key = openssl_random_pseudo_bytes(32);
        
            /* Encrypt the file using the EFS_Encryption class */
            $encrypted_file = $efs_file_encryption->encrypt_file($target_file, $data_encryption_key);
        
            if ($encrypted_file)
            {
        
                /* Store the file's metadata with the target file path */
                $file_metadata = $this->save_file_metadata($post_id, $file_name, $target_file);
        
                /* Log the file metadata result */
                if ($file_metadata['success'])
                {
                    /* Log the successful metadata save */
                    $success = $this->efs_insert_encrypted_file_metadata
                    (
                        $file_metadata['file_id'], 
                        $post_id, 
                        $data_encryption_key, 
                        $expiration_date, 
                        $encrypted_file
                    );

                    if ($success) {
                        /* Metadata was successfully inserted */
                        $this->log_message(WP_CONTENT_DIR . '/efs_upload_log.txt', 'File metadata saved successfully. File ID: ' . $file_metadata['file_id']);
                    } else {
                        /* Handle insertion failure */
                        $this->log_message(WP_CONTENT_DIR . '/efs_upload_log.txt', 'File metadata save failed.');
                    }

                }
                else
                {
                    $this->log_message(WP_CONTENT_DIR . '/efs_upload_log.txt', 'File metadata save failed.');
                }
        
                /* Log the successful encryption and upload */
                $this->log_message(WP_CONTENT_DIR . '/efs_upload_log.txt', 'File encrypted and uploaded: ' . $encrypted_file);

                /* Optionally delete the original file after saving metadata */
                $delete_after_encryption = get_option('efs_delete_files', 0);
                if ($delete_after_encryption) {
                    $efs_file_handler->delete_local_file(wp_get_attachment_url($file_id));
                }

                /* Send encrypted file URL as JSON response */
                wp_send_json_success(['file_url' => $encrypted_file]);

            } else {
                $this->log_message(WP_CONTENT_DIR . '/efs_upload_log.txt', 'File encryption failed for: ' . $target_file);
                wp_send_json_error(['message' => 'File upload failed.']);
                return false;
            }
        }
        else
        {
            /* Log an error if file copy fails */
            $this->log_message(WP_CONTENT_DIR . '/efs_upload_log.txt', 'Failed to copy file to: ' . $target_file);
            return false;
        }
    
        /* Return success response */
        wp_send_json_success(['message' => 'File uploaded successfully.', 'file_id' => $file_id, 'post_id' => $post_id]);
    }

    /**
     * Handles the AJAX request for file upload.
    */

    public function handle_local_upload_ajax()
    {
        /* Log a message to the error log to confirm the hook was fired */
        error_log('The handle_local_upload_ajax hook was fired!');

        $result = $this->handle_local_upload();

        if (isset($result['error'])) {
            wp_send_json_error(['message' => $result['error']]);
        } else {
            wp_send_json_success($result);
        }
    }

    public function handle_file_encryption($post_id)
    {
        global $efs_user_selection, $efs_file_encryption;

        $upload_data = $this->efs_get_encrypted_file_metadata_by_post_id($post_id);

        if ($upload_data)
        {
            /* Metadata found */
            $this->log_message(WP_CONTENT_DIR . '/efs_upload_log.txt', 'Metadata found for post ID: ' . $post_id . $upload_data['file_id'] . $upload_data['expiration_date']);
        }
        else
        {
            /* No metadata found for the given post ID */
            $this->log_message(WP_CONTENT_DIR . '/efs_upload_log.txt', 'No data found for the specified post ID: ' . $post_id);
        }

        $selected_users = $efs_user_selection->get_recipients_from_db($post_id)['results'];
        $response = $efs_user_selection->get_recipients_from_db($post_id)['query'];

        if (empty($selected_users))
        {
            $this->log_message(WP_CONTENT_DIR . '/efs_upload_log.txt', 'No users found in database for post ID: ' . $post_id);

            /* Fallback to retrieving from post meta */
            $selected_users = get_post_meta($post_id, '_efs_user_selection', true);
            $this->log_message(WP_CONTENT_DIR . '/efs_upload_log.txt', 'Selected users retrieved from post meta: ' . implode(',', $selected_users));
        }
        else
        {
            $this->log_message(WP_CONTENT_DIR . '/efs_upload_log.txt', 'Selected users retrieved from database: ' . implode(',', $selected_users));
        }

        /* Log the database query and retrieved users */
        $this->log_message(WP_CONTENT_DIR . '/efs_upload_log.txt', 'Database query executed: ' . $response);
        $this->log_message(WP_CONTENT_DIR . '/efs_upload_log.txt', 'Selected users retrieved from database: ' . implode(',', $selected_users));

        if (!empty($selected_users) && is_array($selected_users))
        {
            /* Save the encryption key securely for all selected users in the database */
            $efs_file_encryption->save_encrypted_key($upload_data['post_id'], $selected_users, $upload_data['file_id'], $upload_data['data_encryption_key'], $upload_data['expiration_date']);
            $this->log_message(WP_CONTENT_DIR . '/efs_upload_log.txt', 'Encryption key saved for users: ' . implode(',', $selected_users));
        }
        else
        {
            $this->log_message(WP_CONTENT_DIR . '/efs_upload_log.txt', 'No users selected or invalid user selection for post ID: ' . $post_id);
        }
        
    }

    /**
     * Insert metadata for an encrypted file into the database.
     *
     * @param int $file_id The ID of the file.
     * @param int $post_id The ID of the post associated with the file.
     * @param string $data_encryption_key The encryption key used for the file.
     * @param string $expiration_date The expiration date of the file.
     * @param string $encrypted_file The path or URL to the encrypted file.
     * @return bool True on success, false on failure.
    */

    public function efs_insert_encrypted_file_metadata($file_id, $post_id, $data_encryption_key, $expiration_date, $encrypted_file)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'efs_encrypted_files';

        $result = $wpdb->insert(
            $table_name,
            [
                'file_id' => $file_id,
                'post_id' => $post_id,
                'data_encryption_key' => $data_encryption_key,
                'expiration_date' => $expiration_date !== null ? $expiration_date : null, /* Null if no expiration */
                'encrypted_file' => $encrypted_file
            ],
            [
                '%d',   /* file_id */
                '%d',   /* post_id */
                '%s',   /* data_encryption_key */
                '%s',   /* expiration_date %s for DATETIME */
                '%s'    /* encrypted_file */
            ]
        );

        return $result !== false;
    }

    /**
     * Retrieve encrypted file metadata by post ID.
     *
     * @param int $post_id The ID of the post to retrieve the encrypted file metadata for.
     * @return array|false An associative array of the metadata if found, false otherwise.
    */

    public function efs_get_encrypted_file_metadata_by_post_id($post_id)
    {
        global $wpdb;

        /* Execute query and retrieve results */
        $result = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}efs_encrypted_files WHERE post_id = %d",
                $post_id
            ), ARRAY_A);

        return $result;
    }

    /**
     * Save file metadata such as expiration date.
     *
     * @param int $post_id The post ID of the post the file is uploaded.
     * @param string $file_name The name of the file.
     * @param string $file_path The path where the file is stored.
    */

    private function save_file_metadata($post_id, $file_name, $file_path)
    {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'efs_file_metadata';

        /* Insert or update the file metadata */
        $result = $wpdb->replace(
            $table_name,
            [
                'post_id' => $post_id,
                'file_name' => $file_name,
                'file_path' => $file_path, /* Use the target file path  */
                'upload_date' => current_time('mysql')
            ],
            [
                '%d', /* post_id */
                '%s', /* file_name */
                '%s', /* file_path */
                '%s'  /* upload_date */
            ]
        );

        /* Retrieve the file ID of the inserted/updated row */
        $file_id = $wpdb->insert_id;

        /* Return an array containing the success status and file_id */
        if ($result !== false)
        {
            return [
                'success' => true,
                'file_id' => $file_id
            ];
        }
        else
        {
            return [
                'success' => false,
                'file_id' => null
            ];
        }
    }

    /**
     * Log messages to a file
     *
     * @param string $file Log file path
     * @param string $message The message to log
    */

    public function log_message($file, $message)
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

        /* Check if the file exists */
        if ( $wp_filesystem->exists( $file ) )
        {
            /* Read current contents */
            $current = $wp_filesystem->get_contents( $file );
            /* Append new message */
            $current .= "[" . gmdate('Y-m-d H:i:s') . "] " . $message . "\n";
            /* Write updated contents */
            $wp_filesystem->put_contents( $file, $current );
        } else
        {
            /* Prepare log message */
            $timestamp = gmdate('Y-m-d H:i:s');
            $log_message = $timestamp . ' - ' . $message . PHP_EOL;
            /* Create new file with the log message */
            $wp_filesystem->put_contents( $file, $log_message, FS_CHMOD_FILE );
        }
    }

        /**
     * Write a log message to a file.
    */

    public function write_log()
    {
        $log_file = WP_CONTENT_DIR . '/efs_upload_log.txt';
        $this->log_message($log_file, 'Ajax method called.');
    }

    /**
     * Calculate the expiration date based on admin settings.
     *
     * @return string Expiration date in 'Y-m-d H:i:s' format.
    */

    private function calculate_expiration_date()
    {
        /* Check if the admin has enabled expiry */
        $enable_expiry = get_option('efs_enable_expiry', 0); /* Default to 0 (disabled) if not set */

        /* If expiry is not enabled, return null */
        if (!$enable_expiry) 
        {
            return null;
        }

        /* Get the expiration period and unit from the admin settings */
        $expiry_period = get_option('efs_expiry_period', 1); /* Default to 1 if not set */
        $expiry_unit = get_option('efs_expiry_unit', 'days'); /* Default to 'days' if not set */

        /* Get the current date and time */
        $current_time = current_time('timestamp');

        /* Calculate the expiration date based on the unit */
        switch ($expiry_unit) 
        {
            case 'minutes':
                $expiration_time = strtotime("+{$expiry_period} minutes", $current_time);
                break;
            case 'hours':
                $expiration_time = strtotime("+{$expiry_period} hours", $current_time);
                break;
            case 'days':
            default:
                $expiration_time = strtotime("+{$expiry_period} days", $current_time);
                break;
        }

        /* Return the expiration date in 'Y-m-d H:i:s' format */
        return gmdate('Y-m-d H:i:s', $expiration_time);
    }

}