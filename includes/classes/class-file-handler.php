<?php

class EFS_File_Handler
{
    private $efs_notification_handler;
    private $efs_s3_file_handler;
    private $efs_local_file_handler;
    private $efs_file_encryption;
    private $efs_init;

    /**
     * Constructor to initialize dependencies and actions.
     *
     * @param object $s3_file_handler Instance of S3 file handler.
     * @param object $local_file_handler Instance of local file handler.
     * @param object $file_encryption Instance of file encryption handler.
    */

    public function __construct($efs_s3_file_handler, $efs_local_file_handler, $efs_file_encryption)
    {
        $this->efs_notification_handler = new EFS_Notification_Handler();
        $this->efs_s3_file_handler = $efs_s3_file_handler;
        $this->efs_local_file_handler = $efs_local_file_handler;
        $this->efs_file_encryption = $efs_file_encryption;
        $this->efs_init = new EFS_Init();

        /* Register AJAX actions */
        add_action('wp_ajax_efs_handle_download', [$this, 'efs_handle_download_request']);
        add_action('wp_ajax_nopriv_efs_handle_download', [$this, 'efs_handle_download_request']); /* Allow non-logged-in users */

        /* Hook after file is uploaded */
        add_action('save_post', [$this, 'efs_efs_handle_file_upload_notifications']);

    }

    /**
     * Initialize the S3 client via the S3 file handler.
    */

    public function efs_initialize_s3_client()
    {
        return $this->efs_s3_file_handler->efs_initialize_s3_client();
    }

    /**
     * Fetch stored S3 buckets via S3 file handler.
    */

    public function efs_get_stored_s3_buckets()
    {
        return $this->efs_s3_file_handler->efs_get_stored_s3_buckets();
    }

    /**
     * Fetch S3 buckets for debugging purposes via S3 file handler.
    */

    public function efs_fetch_s3_buckets_debug()
    {
        return $this->efs_s3_file_handler->efs_fetch_s3_buckets_debug();
    }

    /**
     * Upload file to a secure location via the local file handler.
    */

    private function efs_handle_local_upload()
    {
        return $this->efs_local_file_handler->efs_handle_local_upload();
    }

    /**
     * Retrieve the encryption key for a file via the encryption class.
    */

    public function efs_get_encryption_key($user_id, $file_name)
    {
        return $this->efs_file_encryption->efs_get_encryption_key($user_id, $file_name);
    }

    /**
     * Decrypt an encrypted file using OpenSSL via the encryption class.
     *
    */

    public function efs_decrypt_file($encrypted_file_path, $encryption_key)
    {
        return $this->efs_file_encryption->efs_decrypt_file($encrypted_file_path, $encryption_key);
    }

    /**
     * Handle the file upload notifications to selected users.
     *
     * @param int $post_id Post ID of the uploaded file.
    */

    public function efs_efs_handle_file_upload_notifications($post_id)
    {
        global $efs_user_selection;
        /* Define the log file path */
        $log_file = WP_CONTENT_DIR . '/efs_file_upload_notifications_log.txt';

        /* Get the selected users */
        $selected_users = $efs_user_selection->efs_get_recipients_from_db($post_id);

        /* Ensure this only runs for the `efs_file` post type */
        if (get_post_type($post_id) === 'efs_file') 
        {
            /* Retrieve the current post data */
            $post = get_post($post_id);

            /* Check if the post status is 'publish' and if the post is marked as encrypted */
            /* Convert meta values to the appropriate types for comparison */
            /* Trim to ensure it's a clean string */
            $first_published = trim(get_post_meta($post_id, '_efs_first_published', true));
            $is_encrypted = trim(get_post_meta($post_id, '_efs_encrypted', true));

            /* Check if user notifications are enabled */
            $notify_users = trim(get_option('efs_enable_user_notifications', 0));

            /* Log the post details */
            $this->efs_init->log_message($log_file, "Post ID: $post_id");
            $this->efs_init->log_message($log_file, "Post Status: {$post->post_status}");
            $this->efs_init->log_message($log_file, "First Published Meta: $first_published");

            if ($post->post_status === 'publish' &&
                $first_published === '' &&
                $is_encrypted === '1' && $notify_users === '1'
            )
            {
                /* Send the notification */
                $this->efs_notification_handler->efs_send_upload_notifications($post_id, $selected_users);
                
                $this->efs_init->log_message($log_file, "File upload notifications sent for post ID: $post_id");
                /* Mark this post as published for the first time */
                update_post_meta($post_id, '_efs_first_published', 1);
            }
            else 
            {
                $this->efs_init->log_message($log_file, "Post not published or already marked as first published.");
            }
        }
        else 
        {
            $this->efs_init->log_message($log_file, "Post type is not 'efs_file'.");
        }
    }

    /**
     * Handle the file upload based on the selected storage option.
     *
     * @param array $file The file to upload.
     * @return mixed Result of the upload operation, or false on failure.
    */

    public function efs_handle_file_upload($file)
    {
        $storage_option = get_option('efs_storage_option', 'local');

        switch ($storage_option) {
            case 'amazon':
                return $this->efs_s3_file_handler->efs_upload_to_amazon_s3($file);
            case 'google':
                return $this->efs_upload_to_google_drive($file);
            case 'dropbox':
                return $this->efs_upload_to_dropbox($file);
            case 'local':
            default:
                return $this->efs_handle_local_upload();
        }
    }

    /**
     * Upload file to Google Drive.
     *
     * @param array $file The file to upload.
     * @return mixed Result of the upload operation, or false on failure.
    */

    private function efs_upload_to_google_drive($file)
    {
        /* Implement Google Drive upload logic */
    }

    /**
     * Upload file to Dropbox.
     *
     * @param array $file The file to upload.
     * @return mixed Result of the upload operation, or false on failure.
    */

    private function efs_upload_to_dropbox($file)
    {
        /* Implement Dropbox upload logic */
    }

    /**
     * Handle the file download request via AJAX.
    */

    public function efs_handle_download_request()
    {
        $log_file = WP_CONTENT_DIR . '/efs_decrypt_log.txt';

        /* Check nonce for security */
        check_ajax_referer('efs_download_nonce', 'security');
        $this->efs_init->log_message($log_file, 'Nonce checked successfully.');
    
        /* Validate user */
        if (!is_user_logged_in())
        {
            $this->efs_init->log_message($log_file, 'User not logged in.');
            wp_send_json_error(array('message' => 'User not logged in.'));
        }

        /* Get current user ID */
        $current_user = wp_get_current_user();
        $user_id = $current_user->ID; /* Retrieve user ID from current user */
        $this->efs_init->log_message($log_file, 'Current user ID: ' . $user_id);
    
        /* Check if file ID is set */
        if (!isset($_POST['file_id'])) {
            $this->efs_init->log_message($log_file, 'File ID is missing.');
            wp_send_json_error(array('message' => 'File ID missing.'));
        }
    
        $file_id = intval($_POST['file_id']);
        $this->efs_init->log_message($log_file, 'Received file ID: ' . $file_id);
    
        /* Validate file ID */
        if (get_post_type($file_id) !== 'efs_file')
        {
            $this->efs_init->log_message($log_file, 'Invalid file ID: ' . $file_id);
            wp_send_json_error(array('message' => 'Invalid file ID.'));
        }
    
        /* Retrieve the file URL */
        $file_url = get_post_meta($file_id, '_efs_file_url', true);
    
        if (empty($file_url))
        {
            $this->efs_init->log_message($log_file, 'File URL not found for file ID: ' . $file_id);
            wp_send_json_error(array('message' => 'File URL not found.'));
        }

        $this->efs_init->log_message($log_file, 'File URL: ' . $file_url);

        /* Parse the file path */
        $file_path = wp_parse_url($file_url, PHP_URL_PATH);
        $file_name = basename($file_path);
        $this->efs_init->log_message($log_file, 'Parsed file path: ' . $file_path);
        $this->efs_init->log_message($log_file, 'Parsed file name: ' . $file_name);

        /* Strip the .enc extension if present */
        if (substr($file_name, -4) === '.enc')
        {
            $file_name = substr($file_name, 0, -4);
            $this->efs_init->log_message($log_file, 'Stripped .enc extension. Final file name: ' . $file_name);
        }

        /* Retrieve the encryption key from the database */
        $encryption_key = $this->efs_get_encryption_key($user_id, $file_name);  /* File name & id are used to store the key */

        /* Check if the key was retrieved */
        if ($encryption_key === false)
        {
            $this->efs_init->log_message($log_file, 'Encryption key not found for file: ' . $file_name);
            wp_send_json_error(array('message' => 'Encryption key not found for file: ' . $file_name));
        }

        $this->efs_init->log_message($log_file, 'Encryption key retrieved for file: ' . $file_name);

        /* Decrypt the file */
        $decrypted_data = $this->efs_decrypt_file($file_path, $encryption_key);

        if ($decrypted_data === false)
        {
            $this->efs_init->log_message($log_file, 'File decryption failed for file: ' . $file_name);
            wp_send_json_error(array('message' => 'File decryption failed.'));
        }

        $this->efs_init->log_message($log_file, 'File decrypted successfully for file: ' . $file_name);
        $this->efs_init->log_message($log_file, 'Decrypted file size: ' . strlen($decrypted_data) . ' bytes');
    
        /* Update download status and date */
        $current_time = current_time('mysql');
        update_post_meta($file_id, '_efs_download_status', '1'); /* Mark as downloaded */
        /* Set download date as MySQL timestamp */
        update_post_meta($file_id, '_efs_download_date', $current_time);
        $this->efs_init->log_message($log_file, 'Download status updated for file ID: ' . $file_id);
    
        /* Retrieve the admin notification setting */
        $send_notifications = get_option('efs_send_notifications', 0); /* Default to 0 (disabled) */
        $this->efs_init->log_message($log_file, 'Admin notifications setting: ' . ($send_notifications ? 'Enabled' : 'Disabled'));

        /* Send notification to admin if notifications are enabled */
        if ($send_notifications)
        {
            $current_user = wp_get_current_user();
            $this->efs_notification_handler->efs_send_download_notification_to_admin($file_id, $current_user);
            $this->efs_init->log_message($log_file, 'Admin notified of file download for file ID: ' . $file_id);
        }

        /* Serve the decrypted file for download */
        $file_name = sanitize_file_name($file_name); /* Sanitize the file name */
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $file_name . '"');
        header('Content-Length: ' . strlen($decrypted_data));

        /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The file data is safe to output without escaping */
        echo $decrypted_data;
        $this->efs_init->log_message($log_file, 'File served for download: ' . $file_name);
    
        /* Terminate script execution */
        exit;
    }

    /**
     * Delete local file based on the file URL.
     * 
     * @param string $file_url The URL of the file to delete.
    */

    public function efs_delete_local_file($file_url)
    {
        $attachment_id = attachment_url_to_postid($file_url);
        if ($attachment_id) 
        {
            $result = wp_delete_attachment($attachment_id, true); /* Delete permanently */
            return ($result !== false); /* Return true if deletion succeeded */
        }
        return false; /* Return false if attachment ID was not found */
    }
}