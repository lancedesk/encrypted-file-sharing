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
        add_action('wp_ajax_upload_to_local', [$this, 'handle_local_upload_ajax']);
        add_action('wp_ajax_nopriv_upload_to_local', [$this, 'handle_local_upload_ajax']);
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

        /* Check if upload directory exists, create it if not */
        if (!file_exists($upload_dir)) 
        {
            mkdir($upload_dir, 0755, true);
        }

        $file_name = sanitize_file_name($file['name']);
        $target_file = $upload_dir . $file_name;

        /* Move the uploaded file to the secure directory */
        if (move_uploaded_file($file['tmp_name'], $target_file)) 
        {
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
        } 
        else 
        {
            /* Log an error if file upload fails */
            $this->log_message(WP_CONTENT_DIR . '/efs_upload_log.txt', 'Failed to upload file: ' . $file_name);
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
        $timestamp = date('Y-m-d H:i:s');
        $log_message = $timestamp . ' - ' . $message . PHP_EOL;
        file_put_contents($file, $log_message, FILE_APPEND);
    }

}