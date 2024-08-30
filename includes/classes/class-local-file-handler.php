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

    private function upload_to_local($file)
    {
        /* Define the secure directory */
        $upload_dir = ABSPATH . '../private_uploads/';

        /* Ensure the directory exists */
        if (!file_exists($upload_dir)) 
        {
            mkdir($upload_dir, 0755, true);
        }

        /* Get the file name and ensure it's sanitized */
        $file_name = sanitize_file_name($file['name']);
        $target_file = $upload_dir . $file_name;

        /* Move the uploaded file to the secure directory */
        if (move_uploaded_file($file['tmp_name'], $target_file)) 
        {
            /* Generate a random encryption key (256-bit) */
            $encryption_key = openssl_random_pseudo_bytes(32);

            /* Encrypt the file */
            $encrypted_file = $this->efs_encryption->encrypt_file($target_file, $encryption_key);

            if ($encrypted_file)
            {
                /* Log the upload success */
                $this->log_message(WP_CONTENT_DIR . '/efs_upload_log.txt', 'File encrypted and uploaded successfully: ' . $encrypted_file);
                return $encrypted_file;  /* Return the encrypted file path */
            }
        } 
        else 
        {
            /* Log failure */
            $this->log_message(WP_CONTENT_DIR . '/efs_upload_log.txt', 'Failed to upload file: ' . $file_name);
            return false;
        }
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