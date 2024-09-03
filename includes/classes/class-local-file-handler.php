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
     * Write a log message to a file.
    */

    public function write_log()
    {
        $log_file = WP_CONTENT_DIR . '/efs_upload_log.txt';
        $this->log_message($log_file, 'Ajax method called.');
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

    /**
     * Handle the local file upload via AJAX.
     */

    public function handle_local_upload_ajax()
    {
        global $efs_file_handler;

        /* Calculate the expiration date */
        $expiration_date = $this->calculate_expiration_date();

        /* Log a message to the error log to confirm the hook was fired */
        error_log('The handle_local_upload_ajax hook was fired!');

        /* Log file path */
        $log_file = WP_CONTENT_DIR . '/efs_upload_log.txt';

        $this->log_message($log_file, 'Ajax method called.');

        /* Verify the nonce */
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'efs_upload_nonce')) {
            $this->log_message($log_file, 'Invalid nonce.');
            wp_send_json_error(['message' => 'Invalid nonce.']);
        }

        /* Ensure file ID is provided */
        if (!isset($_POST['file_id'])) {
            wp_send_json_error(['message' => 'File ID is missing.']);
        }

        $file_id = intval($_POST['file_id']);

        /* Retrieve file information */
        $file_path = get_attached_file($file_id);
        $file_name = basename($file_path);
        
        if (!$file_path || !file_exists($file_path)) {
            wp_send_json_error(['message' => 'File does not exist.']);
        }

        /* Log the received file and expiration date */
        $this->log_message($log_file, 'Received file path: ' . $file_path);
        $this->log_message($log_file, 'Expiration date: ' . $expiration_date);

        /* Call the method to upload and encrypt the file locally */
        $encrypted_file = $this->upload_to_local($file_path, $expiration_date);

        /* Check if the file was uploaded and encrypted successfully */
        if ($encrypted_file) {
            $this->log_message($log_file, 'File upload and encryption successful. Encrypted file: ' . $encrypted_file);
            
            /* Log the file ID */
            $this->log_message(WP_CONTENT_DIR . '/efs_upload_log.txt', 'File ID: ' . $file_id);

            /* Delete the local file from WordPress media library */
            $deletion_result = $efs_file_handler->delete_local_file(wp_get_attachment_url($file_id)); /* Using the file's URL */
            
            if ($deletion_result) {
                $this->log_message(WP_CONTENT_DIR . '/efs_upload_log.txt', 'Local file successfully deleted: ' . $file_path);
            } else {
                $this->log_message(WP_CONTENT_DIR . '/efs_upload_log.txt', 'Failed to delete local file: ' . $file_path);
            }
            
            wp_send_json_success(['file_url' => $encrypted_file]);
        } else {
            $this->log_message($log_file, 'File upload or encryption failed.');
            wp_send_json_error(['message' => 'File upload failed.']);
        }
    }

    /**
     * Upload and encrypt the file locally.
     *
     * @param string $file_path The path to the file.
     * @param string $expiration_date The expiration date for the file.
     * @return mixed The path to the encrypted file or false on failure.
    */

    private function upload_to_local($file_path, $expiration_date)
    {
        global $efs_file_encryption;

        $upload_dir = ABSPATH . '../private_uploads/';

        /* Log the received file and expiration date */
        $this->log_message(WP_CONTENT_DIR . '/efs_upload_log.txt', 'Received file path: ' . $file_path);
        $this->log_message(WP_CONTENT_DIR . '/efs_upload_log.txt', 'Expiration date: ' . $expiration_date);

        /* Check if upload directory exists, create it if not */
        if (!file_exists($upload_dir)) {
            if (mkdir($upload_dir, 0755, true)) {
                $this->log_message(WP_CONTENT_DIR . '/efs_upload_log.txt', 'Created directory: ' . $upload_dir);
            } else {
                $this->log_message(WP_CONTENT_DIR . '/efs_upload_log.txt', 'Failed to create directory: ' . $upload_dir);
                return false;
            }
        }

        $file_name = basename($file_path);
        $target_file = $upload_dir . $file_name;

        /* Log the target file path */
        $this->log_message(WP_CONTENT_DIR . '/efs_upload_log.txt', 'Target file path: ' . $target_file);

        /* Copy the file to the secure directory */
        if (copy($file_path, $target_file)) {
            $this->log_message(WP_CONTENT_DIR . '/efs_upload_log.txt', 'File copied to: ' . $target_file);

            $encryption_key = openssl_random_pseudo_bytes(32); /* Generate a random encryption key (256-bit) */

            /* Encrypt the file using the EFS_Encryption class */
            $encrypted_file = $efs_file_encryption->encrypt_file($target_file, $encryption_key);

            if ($encrypted_file) {
                /* Save the encryption key securely in the database */
                $efs_file_encryption->save_encrypted_key($file_name, $encryption_key);

                /* Store the file's expiration date */
                $this->save_file_metadata($file_name, $expiration_date);

                /* Log the successful encryption and upload */
                $this->log_message(WP_CONTENT_DIR . '/efs_upload_log.txt', 'File encrypted and uploaded: ' . $encrypted_file);

                return $encrypted_file;
            } else {
                $this->log_message(WP_CONTENT_DIR . '/efs_upload_log.txt', 'File encryption failed for: ' . $target_file);
                return false;
            }
        } else {
            /* Log an error if file copy fails */
            $this->log_message(WP_CONTENT_DIR . '/efs_upload_log.txt', 'Failed to copy file to: ' . $target_file);
            return false;
        }
    }

    /**
     * Save file metadata such as expiration date.
     *
     * @param string $file_name The name of the file.
     * @param string $expiration_date The expiration date for the file.
    */

    private function save_file_metadata($file_name, $expiration_date)
    {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'efs_file_metadata';

        $wpdb->insert(
            $table_name,
            [
                'file_name' => $file_name,
                'expiration_date' => $expiration_date,
                'created_at' => current_time('mysql')
            ]
        );
    }

    /**
     * Log messages to a file
     *
     * @param string $file Log file path
     * @param string $message The message to log
    */

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