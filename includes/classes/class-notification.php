<?php

class EFS_Notification_Handler
{
    /**
     * Log debug info to a file in the wp-content folder.
     *
     * @param string $message The message to log.
    */

    private function log_debug_info($message)
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

        /* Define the log file path */
        $log_file = WP_CONTENT_DIR . '/efs_notification_log.txt';

        /* Get current time and prepare log message */
        $current_time = current_time('mysql');
        $log_message = '[' . $current_time . '] ' . $message . PHP_EOL;

        /* Check if file exists */
        if ( $wp_filesystem->exists( $log_file ) )
        {
            /* Append message if file exists */
            $current_contents = $wp_filesystem->get_contents( $log_file );
            $new_contents = $current_contents . $log_message;
            $wp_filesystem->put_contents( $log_file, $new_contents, FS_CHMOD_FILE );
        }
        else
        {
            /* Create new file if it doesn't exist */
            $wp_filesystem->put_contents( $log_file, $log_message, FS_CHMOD_FILE );
        }
    }

    /**
     * Send notifications based on file upload.
     *
     * @param int $post_id Post ID of the uploaded file.
     * @param array $selected_users Array of user IDs to notify.
     *                              Each user ID should be an integer.
    */

    public function send_upload_notifications($post_id, $selected_users)
    {
        /* Capture the output of var_dump as a string */
        ob_start();
        var_dump($selected_users);
        $selected_users_dump = ob_get_clean();

        /* Log the dump of selected users */
        $this->log_debug_info("Selected users dump: " . $selected_users_dump);

        if (!empty($selected_users) && is_array($selected_users['results']))
        {
            $file_name = get_the_title($post_id);
            $upload_time = current_time('mysql');
            $download_link = wp_login_url() . '?redirect_to=' . urlencode(get_permalink($post_id));
            $headers = ['Content-Type: text/html; charset=UTF-8'];

            $this->log_debug_info("Starting notification process for post ID: {$post_id}");

            foreach ($selected_users as $user_id_string)
            {
                /* Ensure the user ID is an integer */
                $user_id = intval($user_id_string);
                $user_info = get_userdata($user_id);

                if ($user_info)
                {
                    $user_email = $user_info->user_email;

                    /* Email subject and message */
                    $subject = "New File Available for Download: " . $file_name;
                    $message = "
                    <h1>File Upload Notification</h1>
                    <p>Hello " . esc_html($user_info->display_name) . ",</p>
                    <p>A new file titled <strong>" . esc_html($file_name) . "</strong> was uploaded for you on <strong>" . esc_html($upload_time) . "</strong>.</p>
                    <p>Please <a href='" . esc_url($download_link) . "'>log in</a> to download your file.</p>
                    ";

                    /* Send the email to the user */
                    $mail_status = wp_mail($user_email, $subject, $message, $headers);

                    /* Log debug info */
                    $this->log_debug_info(
                    "User notification sent: User email: {$user_email}, File: {$file_name}, Time: {$upload_time}, Mail status: " . ($mail_status ? 'Success' : 'Failure')
                    );
                }
            }

            if (empty($selected_users))
            {
                $this->log_debug_info("No valid users found for notifications.");
            }
        }
        else
        {
            $this->log_debug_info("No users selected for notifications.");
        }
    }

    /**
     * Send notification to admin after file download.
     *
     * @param int $file_id Post ID of the downloaded file.
     * @param WP_User $user The user who downloaded the file.
    */

    public function send_download_notification_to_admin($file_id, $user)
    {
        /* Get admin email from EFS settings page */
        $admin_email = get_option('efs_admin_email', get_option('admin_email'));

        $file_name = get_the_title($file_id);
        $download_time = current_time('mysql');
        
        /* Check if $_SERVER['REMOTE_ADDR'] is set */
        if (isset($_SERVER['REMOTE_ADDR']))
        {
            /* Retrieve, unslash, and validate the IP address */
            $user_ip = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']));
        }
        else
        {
            /* Handle the case where the IP address is not set */
            $user_ip = 'unknown';
        }

        $headers = ['Content-Type: text/html; charset=UTF-8'];

        /* Email subject and message */
        $subject = "File Downloaded: " . $file_name;
        $message = "
            <h1>File Download Notification</h1>
            <p>The file <strong>" . esc_html($file_name) . "</strong> was downloaded on <strong>" . esc_html($download_time) . "</strong>.</p>
            <p>Downloaded by: " . esc_html($user->display_name) . " (" . esc_html($user->user_email) . ")</p>
            <p>IP Address: " . esc_html($user_ip) . "</p>
        ";

        /* Send the email to the admin */
        wp_mail($admin_email, $subject, $message, $headers);

        /* Log debug info */
        /*
        $this->log_debug_info(
            "Admin notification sent: Admin email: {$admin_email}, File: {$file_name}, Time: {$download_time}, Downloaded by: {$user->user_email}, IP: {$user_ip}, Mail status: " . ($mail_status ? 'Success' : 'Failure')
        );
        */
    }
}