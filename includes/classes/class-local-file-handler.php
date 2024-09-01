<?php

require_once 'class-encryption.php'; /* Include the EFS encryption class */

class EFS_Local_File_Handler
{
    private $efs_encryption;

    /**
     * Constructor to initialize actions and hooks.
    */

    public function __construct()
    {
        /* Initialize the EFS encryption class. */
        $this->efs_encryption = new EFS_Encryption();
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
     * Upload the file to a secure location and encrypt it.
    */

    private function upload_to_local($file, $expiration_date)
    {
        $upload_dir = ABSPATH . '../private_uploads/';

        /* Log the received file and expiration date */
        $this->log_message(WP_CONTENT_DIR . '/efs_upload_log.txt', 'Received file: ' . print_r($file, true));
        $this->log_message(WP_CONTENT_DIR . '/efs_upload_log.txt', 'Expiration date: ' . $expiration_date);

        /* Check if upload directory exists, create it if not */
        if (!file_exists($upload_dir)) 
        {
            if (mkdir($upload_dir, 0755, true)) 
            {
                $this->log_message(WP_CONTENT_DIR . '/efs_upload_log.txt', 'Created directory: ' . $upload_dir);
            } 
            else 
            {
                $this->log_message(WP_CONTENT_DIR . '/efs_upload_log.txt', 'Failed to create directory: ' . $upload_dir);
                return false;
            }
        }

        $file_name = sanitize_file_name($file['name']);
        $target_file = $upload_dir . $file_name;

        /* Log the target file path */
        $this->log_message(WP_CONTENT_DIR . '/efs_upload_log.txt', 'Target file path: ' . $target_file);

        /* Move the uploaded file to the secure directory */
        if (move_uploaded_file($file['tmp_name'], $target_file)) 
        {
            $this->log_message(WP_CONTENT_DIR . '/efs_upload_log.txt', 'File moved to: ' . $target_file);
            
            $encryption_key = openssl_random_pseudo_bytes(32); /* Generate a random encryption key (256-bit) */

            /* Encrypt the file using the EFS_Encryption class */
            $encrypted_file = $this->efs_encryption->encrypt_file($target_file, $encryption_key);

            if ($encrypted_file) 
            {
                /* Save the encryption key securely in the database */
                $this->efs_encryption->save_encrypted_key($file_name, $encryption_key);

                /* Store the file's expiration date */
                $this->save_file_metadata($file_name, $expiration_date);

                /* Log the successful encryption and upload */
                $this->log_message(WP_CONTENT_DIR . '/efs_upload_log.txt', 'File encrypted and uploaded: ' . $encrypted_file);

                return $encrypted_file;
            } 
            else 
            {
                $this->log_message(WP_CONTENT_DIR . '/efs_upload_log.txt', 'File encryption failed for: ' . $target_file);
                return false;
            }
        } 
        else 
        {
            /* Log an error if file upload fails */
            $this->log_message(WP_CONTENT_DIR . '/efs_upload_log.txt', 'Failed to move uploaded file to: ' . $target_file);
            return false;
        }
    }

    /**
     * Handle the local file upload via AJAX.
    */

    public function handle_local_upload_ajax()
    {
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

        /* Check if a file was uploaded */
        if (!isset($_POST['file_data']) || empty($_POST['file_data'])) {
            wp_send_json_error(['message' => 'No file data provided.']);
        }

        /* Check if an expiration date was provided */
        if (!isset($_POST['expiration_date'])) {
            $this->log_message($log_file, 'No expiration date provided.');
            wp_send_json_error(['message' => 'No expiration date provided.']);
        }

        $file = $_FILES['file'];
        $expiration_date = sanitize_text_field($_POST['expiration_date']);

        /* Log the received file and expiration date */
        $this->log_message($log_file, 'Handling file upload - File: ' . print_r($file, true));
        $this->log_message($log_file, 'Expiration date: ' . $expiration_date);

        /* Call the method to upload and encrypt the file locally */
        $encrypted_file = $this->upload_to_local($file, $expiration_date);

        /* Check if the file was uploaded and encrypted successfully */
        if ($encrypted_file) {
            $this->log_message($log_file, 'File upload and encryption successful. Encrypted file: ' . $encrypted_file);
            wp_send_json_success(['file_url' => $encrypted_file]);
        } else {
            $this->log_message($log_file, 'File upload or encryption failed.');
            wp_send_json_error(['message' => 'File upload failed.']);
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