<?php
require_once 'class-s3-file-handler.php'; /* Include the S3 file handler class */

class EFS_File_Handler
{
    private $notification_handler;
    private $s3_file_handler;

    /**
     * Constructor to initialize actions and filters.
    */

    public function __construct()
    {
        $this->notification_handler = new EFS_Notification_Handler();
        $this->s3_file_handler = new EFS_S3_File_Handler();

        /* Register AJAX actions */
        add_action('wp_ajax_efs_handle_download', [$this, 'handle_download_request']);
        add_action('wp_ajax_nopriv_efs_handle_download', [$this, 'handle_download_request']); /* Allow non-logged-in users */

        /* Hook after file is uploaded */
        add_action('save_post', [$this, 'handle_file_upload_notifications']);

    }

    /**
     * Initialize the S3 client.
     *
     * Calls the S3 file handler's method to initialize the S3 client.
     *
     * @return \Aws\S3\S3Client Initialized S3 client instance.
    */

    public function initialize_s3_client()
    {
        return $this->s3_file_handler->initialize_s3_client();
    }

    /**
     * Fetch stored S3 buckets via S3 file handler.
    */

    public function get_stored_s3_buckets()
    {
        return $this->s3_file_handler->get_stored_s3_buckets();
    }

    /**
     * Fetch S3 buckets for debugging purposes.
     *
     * Calls the S3 file handler's method to fetch S3 buckets.
     *
     * @return array List of S3 buckets for debugging.
    */

    public function fetch_s3_buckets_debug()
    {
        return $this->s3_file_handler->fetch_s3_buckets_debug();
    }

    /**
     * Handle the file upload notifications to selected users.
     *
     * @param int $post_id Post ID of the uploaded file.
    */

    public function handle_file_upload_notifications($post_id)
    {
        /* Ensure this only runs for the `efs_file` post type */
        if (get_post_type($post_id) === 'efs_file') 
        {
            /* Retrieve the current post data */
            $post = get_post($post_id);

            /* Check if the post status is 'publish' and if the post hasn't been marked as first published */
            $first_published = get_post_meta($post_id, '_efs_first_published', true);

            if ($post->post_status === 'publish' && empty($first_published)) 
            {
                $file_url = get_post_meta($post_id, '_efs_file_url', true);

                /* Check if file URL is set or file is uploaded */
                if (!empty($file_url)) 
                {
                    /* Send the notification */
                    $this->notification_handler->send_upload_notifications($post_id);

                    /* Mark this post as published for the first time */
                    update_post_meta($post_id, '_efs_first_published', 1);
                }
            }
        }
    }

    /**
     * Handle the file upload based on the selected storage option.
     *
     * @param array $file The file to upload.
     * @return mixed Result of the upload operation, or false on failure.
    */

    public function handle_file_upload($file)
    {
        $storage_option = get_option('efs_storage_option', 'local');

        switch ($storage_option) {
            case 'amazon':
                return $this->s3_file_handler->upload_to_amazon_s3($file);
            case 'google':
                return $this->upload_to_google_drive($file);
            case 'dropbox':
                return $this->upload_to_dropbox($file);
            case 'local':
            default:
                return $this->upload_to_local($file);
        }
    }

    /**
     * Upload file to Google Drive.
     *
     * @param array $file The file to upload.
     * @return mixed Result of the upload operation, or false on failure.
    */

    private function upload_to_google_drive($file)
    {
        /* Implement Google Drive upload logic */
    }

    /**
     * Upload file to Dropbox.
     *
     * @param array $file The file to upload.
     * @return mixed Result of the upload operation, or false on failure.
    */

    private function upload_to_dropbox($file)
    {
        /* Implement Dropbox upload logic */
    }

    /**
     * Upload file to a secure location outside the web root.
     *
     * @param array $file File array from the upload form ($_FILES['file'])
     * @return string|false The file path on success, false on failure.
    */

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
            /* Log the upload success */
            $this->log_message(WP_CONTENT_DIR . '/efs_upload_log.txt', 'File uploaded successfully: ' . $target_file);
            
            /* Return the file path or URL (for secure downloads, you might return a URL later) */
            return $target_file;
        } 
        else 
        {
            /* Log failure */
            $this->log_message(WP_CONTENT_DIR . '/efs_upload_log.txt', 'Failed to upload file: ' . $file_name);
            return false;
        }
    }

    /**
     * Handle the file download request via AJAX.
    */

    public function handle_download_request()
    {
        /* Check nonce for security */
        check_ajax_referer('efs_download_nonce', 'security');
    
        /* Validate user */
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'User not logged in.'));
        }
    
        /* Check if file ID is set */
        if (!isset($_POST['file_id'])) {
            wp_send_json_error(array('message' => 'File ID missing.'));
        }
    
        $file_id = intval($_POST['file_id']);
    
        /* Validate file ID */
        if (get_post_type($file_id) !== 'efs_file') {
            wp_send_json_error(array('message' => 'Invalid file ID.'));
        }
    
        /* Retrieve the file URL */
        $file_url = get_post_meta($file_id, '_efs_file_url', true);
    
        if (empty($file_url)) {
            wp_send_json_error(array('message' => 'File URL not found.'));
        }
    
        /* Update download status and date */
        $current_time = current_time('mysql');
        update_post_meta($file_id, '_efs_download_status', '1'); /* Mark as downloaded */
        /* Set download date as MySQL timestamp */
        update_post_meta($file_id, '_efs_download_date', $current_time);

        /* Serve the file for download */
        $file_path = parse_url($file_url, PHP_URL_PATH);
        $file_name = basename($file_path);
    
        /* Retrieve the admin notification setting */
        $send_notifications = get_option('efs_send_notifications', 0); /* Default to 0 (disabled) */

        /* Send notification to admin if notifications are enabled */
        if ($send_notifications) {
            $current_user = wp_get_current_user();
            $this->notification_handler->send_download_notification_to_admin($file_id, $current_user);
        }

        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $file_name . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($file_path));
        flush(); /* Flush system output buffer */
        readfile($file_path);
    
        /* Terminate script execution */
        exit;
    }

    /**
     * Delete local file based on the file URL.
     * 
     * @param string $file_url The URL of the file to delete.
    */

    public function delete_local_file($file_url)
    {
        $attachment_id = attachment_url_to_postid($file_url);
        if ($attachment_id) 
        {
            wp_delete_attachment($attachment_id, true); /* Delete permanently */
        }
    }
}