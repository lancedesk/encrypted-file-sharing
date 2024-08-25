<?php

class EFS_Admin_Columns
{
    protected $file_display;
    /**
     * Constructor to initialize hooks.
    */

    public function __construct()
    {
        /* Instantiate the EFS_File_Display class to access its methods */
        $this->file_display = new EFS_File_Display();

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
        $columns['file_size'] = __('File Size', 'encrypted-file-sharing');
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
                    $user_links = array_map(function($user_id) {
                        $user = get_user_by('ID', $user_id);
                        if ($user) {
                            return '<a href="' . esc_url(get_edit_user_link($user_id)) . '">' . esc_html($user->display_name) . '</a>';
                        }
                        return '';
                    }, $recipients);
                    echo implode(', ', $user_links);
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
                if ($date) {
                    /* Convert to date & time format */
                    $formatted_date = date('Y/m/d \a\t g:i a', strtotime($date));
                    echo esc_html($formatted_date);
                } else {
                    echo __('N/A', 'encrypted-file-sharing');
                }
                break;
            
            case 'file_size':
                $file_url = get_post_meta($post_id, '_efs_file_url', true);
                if ($file_url) {
                    /* Convert file URL to file path */
                    $upload_dir = wp_upload_dir();
                    $relative_path = str_replace($upload_dir['baseurl'], '', $file_url);
                    $file_path = $upload_dir['basedir'] . $relative_path;
    
                    /* Check if the file exists and get its size */
                    if (file_exists($file_path)) {
                        $file_size = filesize($file_path);
                        echo esc_html($this->file_display->format_file_size($file_size));
                    } else {
                        echo __('File not found', 'encrypted-file-sharing');
                    }
                } else {
                    echo __('No file available', 'encrypted-file-sharing');
                }
                break;
        }
    }
}