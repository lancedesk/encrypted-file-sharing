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

<h1>File Download Notification</h1>
<p>The file <strong><?php echo esc_html($file_name); ?></strong> was downloaded on <strong><?php echo esc_html($download_time); ?></strong>.</p>
<p>Downloaded by: <?php echo esc_html($user_display_name); ?> (<?php echo esc_html($user_email); ?>)</p>
<p>IP Address: <?php echo esc_html($user_ip); ?></p>