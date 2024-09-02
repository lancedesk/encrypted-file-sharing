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
        $columns['expiry_date'] = __('Expiry Date', 'encrypted-file-sharing');
        $columns['status'] = __('Status', 'encrypted-file-sharing');

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

                /* Check if the file exists and get its size */
                if ($file_url) {
                    /* Convert file URL to file path */
                    $upload_dir = wp_upload_dir();
                    $relative_path = str_replace($upload_dir['baseurl'], '', $file_url);
                    $file_path = $upload_dir['basedir'] . $relative_path;

                    /* Check if the file path contains 'private_uploads' */
                    $is_secure = strpos($file_path, 'private_uploads') !== false;

                    /* Get file size */
                    if ($is_secure) 
                    {
                    /* Handle secure file path */
                        $file_size = file_exists($relative_path) ? $this->file_display->format_file_size(filesize($relative_path)) : __('Unknown size', 'encrypted-file-sharing');
                        echo esc_html($file_size);
                    } 
                    else
                    {
                        /* Handle WordPress uploads file path */
                        $file_size = file_exists($file_path) ? $this->file_display->format_file_size(filesize($file_path)) : __('Unknown size', 'encrypted-file-sharing');
                        echo esc_html($file_size);
                    }
    
                } else {
                    echo __('No file available', 'encrypted-file-sharing');
                }
                break;

            case 'expiry_date':
                /* Get the file name */
                $file_url = get_post_meta($post_id, '_efs_file_url', true);

                /* Extract the file path from the URL */
                $file_path = parse_url($file_url, PHP_URL_PATH);  /* Extract just the path part */
                $file_name = basename($file_path);  /* Get the file name, e.g., mom-pdf.pdf.enc */
                                
                /* Check and strip the .enc extension */
                if (substr($file_name, -4) === '.enc') {
                    $file_name = substr($file_name, 0, -4);  /* Strip the .enc part */
                }

                $expiry_date = $this->get_expiration_date($file_name);

                if ($expiry_date) {
                    /* Convert to date & time format */
                    $formatted_date = date('Y/m/d \a\t g:i a', strtotime($expiry_date));
                    echo esc_html($formatted_date);
                } else {
                    echo __('No expiry set', 'encrypted-file-sharing');
                }
                break;
        
            case 'status':
                /* Get the file name */
                $file_url = get_post_meta($post_id, '_efs_file_url', true);

                /* Extract the file path from the URL */
                $file_path = parse_url($file_url, PHP_URL_PATH);  /* Extract just the path part */
                $file_name = basename($file_path);  /* Get the file name, e.g., mom-pdf.pdf.enc */
                
                /* Check and strip the .enc extension */
                if (substr($file_name, -4) === '.enc') {
                    $file_name = substr($file_name, 0, -4);  /* Strip the .enc part */
                }

                $expiry_date = $this->get_expiration_date($file_name);

                if ($expiry_date) {
                    $current_date = date('Y-m-d');
                    if ($expiry_date < $current_date) {
                        echo __('Expired', 'encrypted-file-sharing');
                    } else {
                        echo __('Active', 'encrypted-file-sharing');
                    }
                } else {
                    echo __('No expiry set', 'encrypted-file-sharing');
                }
                break;
        }
    }

    /**
     * Get and format the expiration date for a file from the custom table.
     *
     * @param string $file_name The name of the file.
     * @return string Formatted expiration date or a message if not set.
    */

    private function get_expiration_date($file_name)
    {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'efs_file_metadata';
        
        /* Prepare SQL query to fetch expiration date */
        $query = $wpdb->prepare(
            "SELECT expiration_date FROM $table_name WHERE file_name = %s",
            $file_name
        );

        return $result = $wpdb->get_var($query);
    }

}