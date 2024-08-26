<?php
/**
 * Class to handle file expiry via cron jobs in WordPress.
*/

class EFS_File_Expiry_Handler
{
    private $file_handler; /* Class property. */

    /**
     * Constructor to set up hooks and actions.
    */

    public function __construct()
    {
        $this->file_handler = new EFS_File_Handler(); /* Initialize the file handler. */

        /* Schedule cron event on init. */
        add_action('init', array($this, 'schedule_file_expiry_cron'));

        /* Hook into the cron event to check for expired files. */
        add_action('efs_check_file_expiry_event', array($this, 'check_file_expiry'));

        /* Unschedule the event when the plugin is deactivated. */
        register_deactivation_hook(__FILE__, array($this, 'unschedule_file_expiry_cron'));

        /* Hook into save_post to save expiry information */
        add_action('save_post', array($this, 'save_file_expiry'));
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
        /* Check if file expiry is enabled */
        $enable_expiry = get_option('efs_enable_expiry', 0);

        if ($enable_expiry) 
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

    public function save_file_expiry($post_id)
    {
        /* Check if this is an auto-save routine. */
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        /* Check if this is a valid post type. */
        if (get_post_type($post_id) !== 'efs_file') {
            return;
        }

        /* Get the file expiry settings. */
        $enable_expiry = get_option('efs_enable_expiry', 0);

        if ($enable_expiry)
        {
            $expiry_period = intval(get_option('efs_expiry_period', 7));
            $expiry_unit = get_option('efs_expiry_unit', 'days');
            
            /* Calculate the expiry date based on the current date and the settings. */
            $current_date = new DateTime();
            $expiry_date = clone $current_date;
            
            if ($expiry_unit === 'minutes')
            {
                $expiry_date->add(new DateInterval('PT' . $expiry_period . 'M'));
            }
            elseif ($expiry_unit === 'hours')
            {
                $expiry_date->add(new DateInterval('PT' . $expiry_period . 'H'));
            }
            else
            {
                $expiry_date->add(new DateInterval('P' . $expiry_period . 'D'));
            }
            
            /* Update the post meta with the expiry date. */
            update_post_meta($post_id, '_efs_file_expiry_date', $expiry_date->format('Y-m-d H:i:s'));
        }
        else
        {
            /* Remove the expiry date if expiry is disabled. */
            delete_post_meta($post_id, '_efs_file_expiry_date');
        }
    }

}