<?php
/**
* Template for the email notification after file upload.
* Variables passed from the notification class:
* - $file_name: The name of the uploaded file.
* - $upload_time: The time when the file was uploaded.
* - $user_display_name: The display name of the user being notified.
* - $download_link: Link to download the file.
*/
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Upload Notification</title>
</head>

<body style="font-family: Arial, sans-serif; background-color: #f4f4f4; margin: 0; padding: 0; line-height: 1.6;">
    <table role="presentation" style="border-spacing: 0; width: 100%; margin: 0 auto;">
        <tr>
            <td>
                <div style="background-color: #ffffff; max-width: 600px; margin: 20px auto; border-radius: 8px; box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);">
                    <!-- Header -->
                    <div style="background-color: #2c3e50; padding: 20px; color: #ffffff; text-align: center; border-top-left-radius: 8px; border-top-right-radius: 8px;">
                        <h1 style="margin: 0;">File Upload Notification</h1>
                    </div>
                    
                    <!-- Body -->
                    <div style="padding: 30px; color: #333333;">
                        <p>Hello <?php echo esc_html($user_display_name); ?>,</p>
                        <p>A new file titled <strong><?php echo esc_html($file_name); ?></strong> was uploaded for you on <strong><?php echo esc_html($upload_time); ?></strong>.</p>
                        <p>You can login to our website and download the file using the link below:</p>
                        <p><a href="<?php echo esc_url($download_link); ?>" style="background-color: #2980b9; color: #ffffff; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; margin-top: 20px;">Download File</a></p>
                    </div>

                    <!-- Footer -->
                    <div style="background-color: #ecf0f1; padding: 20px; text-align: center; font-size: 14px; color: #7f8c8d; border-bottom-left-radius: 8px; border-bottom-right-radius: 8px;">
                        <p>For any queries, please <a href="mailto:<?php echo esc_attr($website_email); ?>" style="color: #2c3e50; text-decoration: none;">contact support</a>.</p>
                        <p>&copy; <?php echo gmdate('Y'); ?> <a href="<?php echo esc_url($website_url); ?>"><?php echo esc_html($website_title); ?></a>. All Rights Reserved.</p>
                    </div>

                </div>
            </td>
        </tr>
    </table>
</body>
</html>