<?php

class EFS_Notification_Handler
{
    /**
     * Send notifications based on file upload.
     *
     * @param int $post_id Post ID of the uploaded file.
    */
    public function send_upload_notifications($post_id)
    {
        $selected_users = get_post_meta($post_id, '_efs_user_selection', true);

        if (!empty($selected_users) && is_array($selected_users)) {
            $file_name = get_the_title($post_id);
            $upload_time = current_time('mysql');
            $download_link = wp_login_url() . '?redirect_to=' . urlencode(get_permalink($post_id));

            foreach ($selected_users as $user_id) {
                $user_info = get_userdata($user_id);
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
                wp_mail($user_email, $subject, $message, array('Content-Type: text/html; charset=UTF-8'));
            }
        }
    }
}