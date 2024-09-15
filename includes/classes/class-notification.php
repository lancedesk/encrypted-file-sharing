<?php

class EFS_Notification_Handler
{
    private $logger;

    public function __construct()
    {
        $this->logger = new EFS_Init();
    }

    /**
     * Get template path, allowing for overrides in the theme.
     *
     * @param string $template_name The name of the template file.
     * @param string $default_path Optional. Default path to look for templates.
     *                             Defaults to the plugin's `templates` directory.
     * @return string Path to the template file.
    */

    private function efs_get_template($template_name, $default_path = '')
    {
        /* Set the default plugin template path */
        if (empty($default_path)) 
        {
            $default_path = plugin_dir_path(dirname(__FILE__, 2)) . 'templates/admin/';
        }

        /* Look for the template in the theme */
        $theme_template = locate_template($template_name);

        /* If a theme override exists, return its path */
        if ($theme_template) 
        {
            return $theme_template;
        }

        /* Otherwise, return the plugin's default template path */
        return $default_path . $template_name;
    }

    /**
     * Send notifications based on file upload.
     *
     * @param int $post_id Post ID of the uploaded file.
     * @param array $selected_users Array of user IDs to notify.
     *                              Each user ID should be an integer.
    */

    public function efs_send_upload_notifications($post_id, $selected_users)
    {
        /* Capture the output of var_dump as a string */
        ob_start();
        var_dump($selected_users);
        $selected_users_dump = ob_get_clean();

        /* Log the dump of selected users */
        $this->logger->log_debug_info("Selected users dump: " . $selected_users_dump);

        if (!empty($selected_users) && is_array($selected_users['results']))
        {
            $file_name = get_the_title($post_id);
            $upload_time = current_time('mysql');
            $download_link = wp_login_url() . '?redirect_to=' . urlencode(get_permalink($post_id));
            $website_email = get_option('admin_email');
            $website_title = get_bloginfo('name');
            $website_url = get_bloginfo('url');
            $headers = ['Content-Type: text/html; charset=UTF-8'];

            $this->logger->log_debug_info("Starting notification process for post ID: {$post_id}");

            foreach ($selected_users['results'] as $user_id_string)
            {
                /* Ensure the user ID is an integer */
                $user_id = intval($user_id_string);
                $user_info = get_userdata($user_id);

                if ($user_info)
                {
                    $user_email = $user_info->user_email;
                    /* Email subject and message */
                    $subject = "New File Available for Download: " . $file_name;

                    /* Load the email template */
                    ob_start();
                    $template_path = $this->efs_get_template('email-user.php');
                    $user_display_name = $user_info->display_name;
                    include($template_path);
                    $message = ob_get_clean();

                    /* Send the email to the user */
                    $mail_status = wp_mail($user_email, $subject, $message, $headers);

                    /* Log debug info */
                    $this->logger->log_debug_info(
                        "User notification sent: User email: {$user_email}, File: {$file_name}, Time: {$upload_time}, Mail status: " . ($mail_status ? 'Success' : 'Failure')
                    );
                }
            }

            if (empty($selected_users))
            {
                $this->logger->log_debug_info("No valid users found for notifications.");
            }
        }
        else
        {
            $this->logger->log_debug_info("No users selected for notifications.");
        }
    }

    /**
     * Send notification to admin after file download.
     *
     * @param int $file_id Post ID of the downloaded file.
     * @param WP_User $user The user who downloaded the file.
    */

    public function efs_send_download_notification_to_admin($file_id, $user)
    {
        /* Get admin email from EFS settings page */
        $admin_email = get_option('efs_admin_email', get_option('admin_email'));

        $file_name = get_the_title($file_id);
        $download_time = current_time('mysql');
        $website_title = get_bloginfo('name');
        $website_url = get_bloginfo('url');
        $file_logs_link = home_url("/wp-admin/edit.php?post_type=efs_file");
        /* Email subject and message */
        $subject = "File Downloaded: " . $file_name;
        
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

        /* Load the admin notification template */
        ob_start();
        $template_path = $this->efs_get_template('email-admin.php');
        $user_display_name = $user->display_name;
        $user_email = $user->user_email;
        include($template_path);
        $message = ob_get_clean();

        /* Send the email to the admin */
        $mail_status = wp_mail($admin_email, $subject, $message, $headers);

        /* Log debug info */
        $this->logger->log_debug_info(
            "Admin notification sent: Admin email: {$admin_email}, File: {$file_name}, Time: {$download_time}, Downloaded by: {$user->user_email}, IP: {$user_ip}, Mail status: " . ($mail_status ? 'Success' : 'Failure')
        );
    }
}