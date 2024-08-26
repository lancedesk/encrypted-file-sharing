<?php
/**
 * Class to handle file expiry via cron jobs in WordPress.
*/

class EFS_File_Expiry_Handler
{
    private $file_handler;

    /**
     * Constructor to set up hooks and actions.
    */

    public function __construct($file_handler)
    {
        $this->file_handler = $file_handler;

        /* Schedule cron event on init. */
        add_action('init', array($this, 'schedule_file_expiry_cron'));

        /* Hook into the cron event to check for expired files. */
        add_action('efs_check_file_expiry_event', array($this, 'check_file_expiry'));

        /* Unschedule the event when the plugin is deactivated. */
        register_deactivation_hook(__FILE__, array($this, 'unschedule_file_expiry_cron'));
    }

    /**
     * Schedule cron event if not already scheduled.
    */

    public function schedule_file_expiry_cron()
    {
        if (!wp_next_scheduled('efs_check_file_expiry_event')) 
        {
            wp_schedule_event(time(), 'daily', 'efs_check_file_expiry_event');
        }
    }

    /**
     * Unschedule the cron event when the plugin is deactivated.
    */

    public function unschedule_file_expiry_cron()
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

    public function check_file_expiry()
    {
        /* Get expired files based on meta_key `_efs_file_expiry_date` */
        $args = array(
            'post_type'    => 'efs_file',
            'meta_key'     => '_efs_file_expiry_date',
            'meta_value'   => date('Y-m-d'),
            'meta_compare' => '<=',
            'post_status'  => 'publish',
            'fields'       => 'ids',
        );

        $expired_posts = get_posts($args);

        foreach ($expired_posts as $post_id) 
        {
            /* Delete the local file if stored locally */
            $storage_option = get_option('efs_storage_option', 'local');
            if ($storage_option === 'local') 
            {
                $file_url = get_post_meta($post_id, '_efs_file_url', true);
                $this->file_handler->delete_local_file($file_url);
            }

            /* Change post status to 'expired' */
            wp_update_post(array(
                'ID'          => $post_id,
                'post_status' => 'expired', /* Set to 'expired' */
            ));
        }
    }
}