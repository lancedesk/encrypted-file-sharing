<?php

class EFS_Admin_Columns
{
    /**
     * Constructor to initialize hooks.
    */

    public function __construct()
    {
        /* Hook to add custom columns */
        add_filter('manage_efs_file_posts_columns', array($this, 'add_custom_columns'));

        /* Hook to populate custom columns */
        add_action('manage_efs_file_posts_custom_column', array($this, 'populate_custom_columns'), 10, 2);
    }

    /**
     * Add custom columns to the list table.
    */

    public function add_custom_columns($columns)
    {
        $columns['recipient'] = __('Recipient', 'encrypted-file-sharing');
        $columns['downloaded'] = __('Downloaded', 'encrypted-file-sharing');
        $columns['download_date'] = __('Download Date', 'encrypted-file-sharing');

        return $columns;
    }

    /**
     * Populate custom columns with data.
    */

    public function populate_custom_columns($column, $post_id)
    {
        switch ($column) {
            case 'recipient':
                $recipients = get_post_meta($post_id, '_efs_user_selection', true);
                if ($recipients) {
                    $user_names = array_map(function($user_id) {
                        $user = get_user_by('ID', $user_id);
                        return $user ? $user->display_name : '';
                    }, $recipients);
                    echo implode(', ', $user_names);
                } else {
                    echo __('None', 'encrypted-file-sharing');
                }
                break;

            case 'downloaded':
                $status = get_post_meta($post_id, '_efs_download_status', true);
                echo $status ? __('Downloaded', 'encrypted-file-sharing') : __('Pending', 'encrypted-file-sharing');
                break;

            case 'download_date':
                $date = get_post_meta($post_id, '_efs_download_date', true);
                echo $date ? date('Y-m-d', strtotime($date)) : __('N/A', 'encrypted-file-sharing');
                break;
        }
    }
}