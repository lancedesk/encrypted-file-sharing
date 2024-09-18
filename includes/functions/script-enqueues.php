<?php

/* Enqueue frontend styles and scripts */
add_action('wp_enqueue_scripts', 'efs_enqueue_frontend_scripts');
function efs_enqueue_frontend_scripts()
{
    /* Enqueue Font Awesome locally */
    wp_enqueue_style('font-awesome-local', plugin_dir_url(__FILE__) . '../../assets/css/all.min.css', array(), '6.6.0');

    /* Enqueue frontend CSS */
    wp_enqueue_style('efs-frontend-css', plugin_dir_url(__FILE__) . '../../assets/css/frontend.css', array(), '1.0.0');
    
    /* Enqueue frontend JS */
    wp_enqueue_script('efs-frontend-js', plugin_dir_url(__FILE__) . '../../assets/js/frontend.js', array('jquery'), '1.0.0', true);

    /* Localize script with data */
    wp_localize_script('efs-frontend-js', 'efsAdminAjax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('efs_download_nonce')
    ));
}

/* Enqueue admin styles and scripts */
add_action('admin_enqueue_scripts', 'efs_enqueue_admin_scripts');
function efs_enqueue_admin_scripts($hook_suffix)
{
    global $post;
    $post_type = get_post_type();

    /* Ensure we are editing an efs_file post type or on the EFS settings page */
    if (($hook_suffix === 'post-new.php' ||
         $hook_suffix === 'post.php' ||
         $hook_suffix === 'edit.php' ||
         /* phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Conditionally enqueue scripts, not for sensitive actions*/
         (isset($_GET['page']) && $_GET['page'] === 'efs-settings')) &&
         /* phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Conditionally enqueue scripts, not for sensitive actions*/
         ($post_type === 'efs_file' || (isset($_GET['page']) && $_GET['page'] === 'efs-settings'))
        )
    {
        /* Enqueue admin CSS */
        wp_enqueue_style('efs-admin-css', plugin_dir_url(__FILE__) . '../../assets/css/admin.css', array(), '1.0.0');
        
        /* Enqueue admin JS */
        wp_enqueue_script('efs-admin-js', plugin_dir_url(__FILE__) . '../../assets/js/admin.js', array('jquery'), '1.0.0', true);

        /* Create a nonce for the AJAX request */
        $nonce = wp_create_nonce('efs_upload_nonce');

        /* Retrieve the storage option from the admin settings */
        $storage_options = get_option('efs_storage_option');

        /* Localize script with data */
        wp_localize_script('efs-admin-js', 'efsAdminAjax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => $nonce,
            'efsStorageOption' => $storage_options,
            'efsSelectFileTitle' => __('Select or Upload a File', 'encrypted-file-sharing'),
            'efsSelectFileButtonText' => __('Use this file', 'encrypted-file-sharing'),
            'efsUploadFailedMessage' => __('File upload failed', 'encrypted-file-sharing'),
            'efsErrorMessage' => __('An error occurred during the file upload', 'encrypted-file-sharing')
            /* 'nonce'    => wp_create_nonce('efs_admin_nonce') */
        ));
    }
}

/* Enqueue S3 handler script for the specific admin page */
add_action('admin_enqueue_scripts', 'efs_enqueue_s3_handler_script');
function efs_enqueue_s3_handler_script($hook_suffix)
{
    /* Check if we are on the correct admin page */
    if (isset($_GET['post_type']) && $_GET['post_type'] === 'efs_file' && isset($_GET['page']) && $_GET['page'] === 'efs-settings')
    {
        
        /* Enqueue the S3 handler JS */
        wp_enqueue_script('efs-s3-handler', plugin_dir_url(__FILE__) . '../../assets/js/s3-handler.js', array('jquery'), '1.0.0', true);

        /* Localize script with data */
        wp_localize_script('efs-s3-handler', 'efs_s3_params', array(
            'efs_s3_nonce' => wp_create_nonce('efs_s3_nonce'),
            'ajax_url'     => admin_url('admin-ajax.php')  /* Ensure the AJAX URL is correct */
        ));
    }
}