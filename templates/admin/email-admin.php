<?php
/**
 * Template for the email notification to the admin after file download.
 * Variables passed from the notification class:
 * - $file_name: The name of the downloaded file.
 * - $download_time: The time when the file was downloaded.
 * - $user_display_name: The display name of the user who downloaded the file.
 * - $user_email: The email of the user who downloaded the file.
 * - $user_ip: The IP address of the user who downloaded the file.
*/
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Download Notification</title>
</head>

<body style="font-family: Arial, sans-serif; background-color: #f4f4f4; margin: 0; padding: 0; line-height: 1.6;">
    <table role="presentation" style="border-spacing: 0; width: 100%; margin: 0 auto;">
        <tr>
            <td>
                <div style="background-color: #ffffff; max-width: 600px; margin: 20px auto; border-radius: 8px; box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);">
                    <!-- Header -->
                    <div style="background-color: #2c3e50; padding: 20px; color: #ffffff; text-align: center; border-top-left-radius: 8px; border-top-right-radius: 8px;">
                        <h1 style="margin: 0;">File Download Notification</h1>
                    </div>
                    
                    <!-- Body -->
                    <div style="padding: 30px; color: #333333;">
                        <p>Hello Admin,</p>
                        <p>The file <strong><?php echo esc_html($file_name); ?></strong> was downloaded on <strong><?php echo esc_html($download_time); ?></strong>.</p>
                        <p>Downloaded by: <?php echo esc_html($user_display_name); ?> (<?php echo esc_html($user_email); ?>)</p>
                        <p>IP Address: <?php echo esc_html($user_ip); ?></p>
                        <p>For more details, please check the download logs below: </p>
                        <p><a href="<?php echo esc_url($file_logs_link); ?>" style="background-color: #2980b9; color: #ffffff; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; margin-top: 20px;">Check Logs</a></p>
                    </div>

                    <!-- Footer -->
                    <div style="background-color: #ecf0f1; padding: 20px; text-align: center; font-size: 14px; color: #7f8c8d; border-bottom-left-radius: 8px; border-bottom-right-radius: 8px;">
                        <p>&copy; <?php echo gmdate('Y'); ?> <a href="<?php echo esc_url($website_url); ?>"><?php echo esc_html($website_title); ?></a>. All Rights Reserved.</p>
                    </div>

                </div>
            </td>
        </tr>
    </table>
</body>
</html>