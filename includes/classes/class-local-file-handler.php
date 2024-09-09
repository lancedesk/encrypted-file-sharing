<?php

class EFS_Local_File_Handler
{
    private $file_id;
    private $post_id;
    private $data_encryption_key;
    private $expiration_date;
    private $encrypted_file;

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
     * Handle the local file upload via AJAX.
    */

    public function process_file_upload()
    {
        global $efs_file_handler, $efs_file_encryption;
        $upload_dir = ABSPATH . '../private_uploads/';

        /* Check if upload directory exists, create it if not */
        if (!file_exists($upload_dir)) {
            if (mkdir($upload_dir, 0755, true)) {
                $this->log_message(WP_CONTENT_DIR . '/efs_upload_log.txt', 'Created directory: ' . $upload_dir);
            } else {
                $this->log_message(WP_CONTENT_DIR . '/efs_upload_log.txt', 'Failed to create directory: ' . $upload_dir);
                return false;
            }
        }

        /* Calculate the expiration date */
        $expiration_date = $this->calculate_expiration_date();

        /* Log file path */
        $log_file = WP_CONTENT_DIR . '/efs_upload_log.txt';

        /* Verify the nonce */
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'efs_upload_nonce')) {
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
                    $this->file_id = $file_metadata['file_id'];
                    $this->post_id = $post_id;
                    $this->data_encryption_key = $data_encryption_key;
                    $this->expiration_date = $expiration_date;
                    $this->encrypted_file = $encrypted_file;
                    $this->log_message(WP_CONTENT_DIR . '/efs_upload_log.txt', 'File metadata saved successfully. File ID: ' . $file_metadata['file_id']);
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

                /* Get and log sensitive data (avoid sending it as JSON) */
                $this->get_upload_data();

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
     * Formats upload data for internal use.
     *
     * @return array
    */

    private function get_upload_data()
    {
        $log_file = WP_CONTENT_DIR . '/efs_upload_log.txt';

        /* Log the success data */
        $this->log_message($log_file, 'File uploaded and encrypted successfully.');
        $this->log_message($log_file, 'File ID: ' . $this->file_id);
        $this->log_message($log_file, 'Post ID: ' . $this->post_id);
        /* Convert binary data to hex for logging */
        if (!is_null($this->data_encryption_key))
        {
            $this->log_message($log_file, 'Data Encryption Key: ' . bin2hex($this->data_encryption_key));
        }
        else
        {
            $this->log_message($log_file, 'Data Encryption Key: null');
        }

        $this->log_message($log_file, 'Expiration Date: ' . $this->expiration_date);
        $this->log_message($log_file, 'Encrypted File Path: ' . $this->encrypted_file);

        return [
            'file_id' => $this->file_id,
            'post_id' => $this->post_id,
            'data_encryption_key' => $this->data_encryption_key,
            'expiration_date' => $this->expiration_date,
            'encrypted_file' => $this->encrypted_file,
        ];
    }

    /**
     * Handles the AJAX request for file upload.
    */

    public function handle_local_upload_ajax()
    {
        /* Log a message to the error log to confirm the hook was fired */
        error_log('The handle_local_upload_ajax hook was fired!');

        $result = $this->process_file_upload();

        if (isset($result['error'])) {
            wp_send_json_error(['message' => $result['error']]);
        } else {
            wp_send_json_success($result);
        }
    }

    public function handle_file_encryption($post_id)
    {
        global $efs_user_selection, $efs_file_encryption;

        $upload_data = $this->get_upload_data();
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
        return date('Y-m-d H:i:s', $expiration_time);
    }

}