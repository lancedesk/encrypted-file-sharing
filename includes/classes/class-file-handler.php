<?php
use Aws\S3\S3Client;
use Aws\Exception\AwsException;

class EFS_File_Handler
{
    private $notification_handler;

    /**
     * Constructor to initialize actions and filters.
    */

    public function __construct()
    {
        $this->notification_handler = new EFS_Notification_Handler();

        /* Register AJAX actions */
        add_action('wp_ajax_efs_handle_download', [$this, 'handle_download_request']);
        add_action('wp_ajax_nopriv_efs_handle_download', [$this, 'handle_download_request']); /* Allow non-logged-in users */

        /* Hook after file is uploaded */
        add_action('save_post', [$this, 'handle_file_upload_notifications']);

        /* Initialize S3 Client */
        $this->initialize_s3_client();

        /* Register AJAX actions for managing S3 buckets */
        add_action('wp_ajax_upload_to_s3', [$this, 'handle_s3_upload_ajax']);
        add_action('wp_ajax_efs_fetch_s3_buckets', [$this, 'efs_fetch_s3_buckets']);
        add_action('wp_ajax_nopriv_efs_fetch_s3_buckets', [$this, 'efs_fetch_s3_buckets']);
        add_action('wp_ajax_efs_create_s3_bucket', [$this, 'efs_create_s3_bucket']);
        add_action('wp_ajax_nopriv_efs_create_s3_bucket', [$this, 'efs_create_s3_bucket']);
        add_action('wp_ajax_efs_fetch_s3_buckets', [$this, 'efs_fetch_s3_buckets_callback']);
    }

    /**
     * Initialize the S3 client with credentials and settings.
    */

    /* private function initialize_s3_client() */
    public function initialize_s3_client()
    {
        /* Include the AWS SDK */
        require_once plugin_dir_path(__FILE__) . '../aws-sdk/aws.phar';

         /* Fetch settings from the stored options */
        $region = get_option('efs_aws_region', '');
        $access_key = get_option('efs_aws_access_key', '');
        $secret_key = get_option('efs_aws_secret_key', '');

        if (!$region || !$access_key || !$secret_key) {
            error_log('AWS credentials or region not configured properly.');
            return false;
        }

        $this->s3_client = new S3Client([
            'region'  => $region, /* e.g., 'us-east-1' */
            'version' => 'latest',
            'credentials' => [
                'key'    => $access_key,
                'secret' => $secret_key,
            ],
        ]);

        /* Test if the connection to S3 is successful */
        try {
            /* A simple request to list S3 buckets */
            $result = $this->s3_client->listBuckets();
            
            /* If the request is successful, return true */
            return true;
        } catch (AwsException $e) {
            /* Log the error message */
            error_log('S3 Connection Failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Fetches and stores the list of S3 buckets in the WordPress options table.
     * 
     * @return array|false Returns an array of bucket names on success, or false on failure.
    */

    public function fetch_and_store_s3_buckets() {
        if (!$this->s3_client) {
            error_log('S3 client is not initialized.');
            return false;
        }

        try {
            /* List Buckets */
            $result = $this->s3_client->listBuckets();

            /* Extract and store bucket names */
            $buckets = array_map(function($bucket) {
                return $bucket['Name'];
            }, $result['Buckets']);

            /* Save buckets to WordPress options table */
            update_option('efs_s3_buckets', $buckets);

            return $buckets;
        } catch (AwsException $e) {
            /* Log any errors */
            error_log('Error fetching S3 buckets: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Callback function for fetching S3 buckets via AJAX.
     * 
     * Retrieves the list of S3 buckets using the fetch_and_store_s3_buckets method
     * and returns the result as a JSON response. Logs errors if the operation fails.
    */

    public function efs_fetch_s3_buckets_callback() {
        if (!$this->s3_client) {
            error_log('S3 client is not initialized.');
            return false;
        }

        $buckets = $this->fetch_and_store_s3_buckets();

        if ($buckets) {
            wp_send_json_success($buckets);
        } else {
            wp_send_json_error(array('message' => 'Failed to fetch buckets.'));
        }
    }

    /**
     * Retrieves the list of stored S3 buckets from the WordPress options table.
     * 
     * @return array Returns an array of stored bucket names. If no buckets are stored, returns an empty array.
    */

    public function get_stored_s3_buckets() {
        /* Retrieve stored buckets from WordPress options table */
        return get_option('efs_s3_buckets', array());
    }

    /**
     * Debug method to fetch S3 buckets without using AJAX or POST requests.
     * @return array|bool List of S3 bucket names or false on failure.
    */

    public function fetch_s3_buckets_debug()
    {
        if (!$this->s3_client) {
            error_log('S3 client is not initialized.');
            return false;
        }

        try {
            /* List Buckets */
            $result = $this->s3_client->listBuckets();

            /* Extract and return bucket names */
            $buckets = array_map(function($bucket) {
                return $bucket['Name'];
            }, $result['Buckets']);

            return $buckets;
        } catch (AwsException $e) {
            /* Log any errors */
            error_log('Error fetching S3 buckets: ' . $e->getMessage());
            return false;
        }
    }

    /* Function to create S3 bucket */
    public function efs_create_s3_bucket()
    {
        /* Check nonce for security */
        check_ajax_referer('efs_s3_nonce', '_ajax_nonce');

        $this->log_error('efs_create_s3_bucket called.');

        if (!current_user_can('manage_options')) {
            $this->log_error('Unauthorized access attempt.');
            wp_send_json_error(array('message' => 'Unauthorized user'));
        }

        $bucket_name = sanitize_text_field($_POST['bucket_name']);
        $region = get_option('efs_aws_region');
        
        if (empty($bucket_name)) {
            $this->log_error('Bucket name cannot be empty.');
            wp_send_json_error(array('message' => 'Bucket name cannot be empty'));
        }

        /* Ensure S3 client is initialized */
        if (!$this->s3_client) {
            $initialized = $this->initialize_s3_client();
            if (!$initialized) {
                wp_send_json_error(array('message' => 'S3 client initialization failed.'));
            }
        }

        $this->log_success('Attempting to create bucket: ' . $bucket_name);

        /* Use AWS SDK to create the bucket */
        try
        {
            $result = $this->s3_client->createBucket([
                'Bucket' => $bucket_name,
            ]);

            $this->log_success('Bucket created: ' . $bucket_name);
            wp_send_json_success();
        }
        catch (Aws\S3\Exception\S3Exception $e) 
        {
            if ($e->getAwsErrorCode() === 'BucketAlreadyExists')
            {
                $this->log_error('Bucket already exists: ' . $bucket_name);
                wp_send_json_error(array('message' => 'Bucket name already taken. Please choose a different name.'));
            }
            elseif ($e->getAwsErrorCode() === 'BucketAlreadyOwnedByYou')
            {
                $this->log_error('Bucket already owned by you: ' . $bucket_name);
                wp_send_json_error(array('message' => 'You already own a bucket with this name.'));
            }
            else
            {
                $this->log_error('Exception occurred: ' . $e->getMessage());
                wp_send_json_error(array('message' => $e->getMessage()));
            }
        }
        catch (Exception $e)
        {
            $this->log_error('Exception occurred: ' . $e->getMessage());
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    private function log_error($message)
    {
        $log_file = WP_CONTENT_DIR . '/efs_error_log.txt';
        error_log(date('Y-m-d H:i:s') . ' - ERROR: ' . $message . "\n", 3, $log_file);
    }

    private function log_success($message)
    {
        $log_file = WP_CONTENT_DIR . '/efs_success_log.txt';
        error_log(date('Y-m-d H:i:s') . ' - SUCCESS: ' . $message . "\n", 3, $log_file);
    }

    /* AJAX handler to fetch S3 buckets */
    public function efs_fetch_s3_buckets()
    {
        /* Check nonce for security */
        check_ajax_referer('efs_s3_nonce', '_ajax_nonce');

        /* Ensure the S3 client is initialized */
        if (!$this->s3_client) {
            wp_send_json_error(array('message' => 'S3 client is not initialized.'));
            return;
        }

        try {
            /* List Buckets */
            $result = $this->s3_client->listBuckets();

            /* Return bucket names */
            $buckets = array_map(function($bucket) {
                return $bucket['Name'];
            }, $result['Buckets']);

            /* Send bucket names as JSON response */
            wp_send_json_success($buckets);
        } catch (AwsException $e) {
            /* Handle errors */
            wp_send_json_error($e->getMessage());
        }
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
                return $this->upload_to_amazon_s3($file);
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
     * Upload file to Amazon S3.
     *
     * @param array $file The file to upload.
     * @return mixed Result of the upload operation, or false on failure.
    */

    private function upload_to_amazon_s3($file)
    {
        /* Define your bucket and file path on S3 */
        $bucket = 'your-s3-bucket';
        $file_key = basename($file['file']); /* Use the file's name as its S3 key */

        /* Retrieve the privacy setting */
        $file_privacy = get_option('efs_file_privacy', 0); /* Default to public */

        try {
            /* Upload the file to S3 */
            $result = $this->s3_client->putObject([
                'Bucket'     => $bucket,
                'Key'        => $file_key,
                'SourceFile' => $file['file'],  /* Path to the file on the local filesystem */
                'ACL'        => $file_privacy ? 'private' : 'public-read', /* Set ACL based on privacy setting */
            ]);

            /* Return the S3 URL */
            $file_url = $result['ObjectURL'];
            return $file_url;

        } catch (AwsException $e) {
            error_log('Amazon S3 Upload Failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Handle the S3 file upload via AJAX.
    */

    public function handle_s3_upload_ajax() 
    {
        if (!isset($_FILES['file']) || empty($_FILES['file']['name'])) {
            wp_send_json_error(['message' => 'No file uploaded.']);
        }

        $file = $_FILES['file'];

        /* Call the method to upload the file to S3 */
        $file_key = $this->upload_to_amazon_s3($file);

        if ($file_key) {
            /* Retrieve expiry settings */
            $enable_expiry = get_option('efs_enable_expiry', 0);
            $expiry_period = get_option('efs_expiry_period', 7); /* Default to 7 */
            $expiry_unit = get_option('efs_expiry_unit', 'days'); /* Default to days */

            /* Convert expiry period to seconds */
            $expiry_in_seconds = $expiry_period * $this->get_unit_multiplier($expiry_unit);

            if (!$enable_expiry) {
                $expiry_in_seconds = 0; /* No expiry */
            }

            /* Generate a pre-signed URL for the uploaded file */
            $presigned_url = $this->get_presigned_url($file_key, $expiry_in_seconds);

            if ($presigned_url) {
                wp_send_json_success(['presigned_url' => $presigned_url]);
            } else {
                wp_send_json_error(['message' => 'Failed to generate presigned URL.']);
            }
        } else {
            wp_send_json_error(['message' => 'S3 upload failed.']);
        }
    }

    /**
     * Returns the multiplier for a given time unit.
     *
     * This method converts time units (minutes, hours, days) into their equivalent 
     * number of seconds. It defaults to days if an unrecognized unit is provided.
     *
     * @param string $unit The time unit to convert. Valid values are 'minutes', 'hours', 'days'.
     * 
     * @return int The number of seconds corresponding to the specified time unit.
    */

    private function get_unit_multiplier($unit)
    {
        switch ($unit) {
            case 'minutes':
                return 60;
            case 'hours':
                return 3600;
            case 'days':
                return 86400;
            default:
                return 86400; /* Default to days */
        }
    }

    /**
     * Generate a pre-signed URL for downloading a private S3 file.
     *
     * @param string $file_key The S3 key of the file.
     * @param int $expiry_time The time in seconds the URL will remain valid.
     * @return string Pre-signed URL for the file.
    */

    private function get_presigned_url($file_key, $expiry_in_seconds)
    {
        /* Define your bucket */
        $bucket = 'your-s3-bucket';

        try {
            /* Generate the pre-signed URL */
            $cmd = $this->s3_client->getCommand('GetObject', [
                'Bucket' => $bucket,
                'Key'    => $file_key,
            ]);

            /* Create a pre-signed request with an expiration time */
            $request = $this->s3_client->createPresignedRequest($cmd, '+' . $expiry_in_seconds . ' seconds');

            /* Get the actual pre-signed URL */
            return (string) $request->getUri();

        } catch (AwsException $e) {
            error_log('Failed to generate pre-signed URL: ' . $e->getMessage());
            return false;
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
     * Upload file to the local media library.
     *
     * @param array $file The file to upload.
     * @return mixed Result of the upload operation, or false on failure.
    */

    private function upload_to_local($file)
    {
        $attachment_id = wp_insert_attachment($file, $file['file']);
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attachment_data = wp_generate_attachment_metadata($attachment_id, $file['file']);
        wp_update_attachment_metadata($attachment_id, $attachment_data);
        
        return $attachment_id;
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