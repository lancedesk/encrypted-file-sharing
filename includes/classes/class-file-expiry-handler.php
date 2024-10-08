<?php
/**
 * Class to handle file expiry via cron jobs in WordPress.
*/

class EFS_File_Expiry_Handler
{
    /**
     * Constructor to set up hooks and actions.
    */

    public function __construct()
    {
        /* Schedule cron event on init. */
        add_action('init', [$this, 'efs_schedule_file_expiry_cron']);

        /* Hook into the cron event to check for expired files. */
        add_action('efs_check_file_expiry_event', [$this, 'efs_check_file_expiry']);

        /* Unschedule the event when the plugin is deactivated. */
        register_deactivation_hook(__FILE__, [$this, 'efs_unschedule_file_expiry_cron']);
    }

    /**
     * Schedule cron event if not already scheduled.
    */

    public function efs_schedule_file_expiry_cron()
    {
        if (!wp_next_scheduled('efs_check_file_expiry_event')) 
        {
            wp_schedule_event(time(), 'daily', 'efs_check_file_expiry_event');
        }
    }

    /**
     * Unschedule the cron event when the plugin is deactivated.
    */

    public function efs_unschedule_file_expiry_cron()
    {
        $timestamp = wp_next_scheduled('efs_check_file_expiry_event');
        if ($timestamp) 
        {
            wp_unschedule_event($timestamp, 'efs_check_file_expiry_event');
        }
    }

    /**
     * Function to check for expired files and handle them.
    */

    public function efs_check_file_expiry()
    {
        global $efs_file_handler, $efs_admin_columns, $wpdb;

        /* Check if file expiry is enabled */
        $enable_expiry = get_option('efs_enable_expiry', 0);

        if ($enable_expiry) 
        {
            /* Get all published files */
            $args = [
                'post_type'   => 'efs_file',
                'post_status' => 'publish',
                'fields'      => 'ids',
            ];

            $file_posts = get_posts($args);

            /* Loop through the files and check expiry from custom table */
            foreach ($file_posts as $post_id) 
            {
                $expiration_date = $efs_admin_columns->efs_get_expiration_date($post_id);

                /* Compare expiration date if it exists */
                if ($expiration_date && strtotime($expiration_date) <= strtotime(gmdate('Y-m-d'))) 
                {
                    /* Delete the local file if stored locally */
                    $storage_option = get_option('efs_storage_option', 'local');

                    if ($storage_option === 'local') 
                    {
                        $file_url = get_post_meta($post_id, '_efs_file_url', true);
                        $efs_file_handler->efs_delete_local_file($file_url);
                    }

                    /* Change post status to 'expired' */
                    wp_update_post([
                        'ID'          => $post_id,
                        'post_status' => 'expired',
                    ]);
                }
            }
        }
    }
}