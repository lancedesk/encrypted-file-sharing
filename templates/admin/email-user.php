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

<h1>File Upload Notification</h1>
<p>Hello <?php echo esc_html($user_display_name); ?>,</p>
<p>A new file titled <strong><?php echo esc_html($file_name); ?></strong> was uploaded for you on <strong><?php echo esc_html($upload_time); ?></strong>.</p>
<p>Please <a href="<?php echo esc_url($download_link); ?>">log in</a> to download your file.</p>