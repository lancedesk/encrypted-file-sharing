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
                echo wp_kses_post($this->get_recipients($post_id));
                break;

            case 'downloaded':
                echo esc_html($this->get_download_status($post_id));
                break;

            case 'download_date':
                echo esc_html($this->get_formatted_date($post_id, '_efs_download_date'));
                break;

            case 'file_size':
                echo esc_html($this->get_file_size($post_id));
                break;

            case 'expiry_date':
                echo esc_html($this->get_expiration_date_display($post_id));
                break;

            case 'status':
                echo esc_html($this->get_file_status($post_id));
                break;
        }
    }

    /**
     * Get recipients for a post from the custom recipients table.
     *
     * @param int $post_id The ID of the post.
     * @return string HTML string of recipients.
    */

    private function get_recipients($post_id)
    {
        global $wpdb;

        /* Table name for recipients */
        $table_name = $wpdb->prefix . 'efs_recipients';

        /* Prepare and run the query to get recipient_ids using post_id */
        $query = $wpdb->prepare("SELECT recipient_id FROM $table_name WHERE post_id = %d", $post_id);
        $recipient_ids = $wpdb->get_col($query);

        if ($recipient_ids)
        {
            /* Map recipient_ids to user links */
            $user_links = array_map(function($user_id)
            {
                $user = get_user_by('ID', $user_id);
                return $user ? '<a href="' . esc_url(get_edit_user_link($user_id)) . '">' . esc_html($user->display_name) . '</a>' : '';
            }, $recipient_ids);
            
            /* Return the links as a comma-separated string */
            return implode(', ', $user_links);
        } else {
            return __('None', 'encrypted-file-sharing');
        }
    }


    /**
     * Get the download status for a post.
     *
     * @param int $post_id The ID of the post.
     * @return string Download status.
    */

    private function get_download_status($post_id)
    {
        $status = get_post_meta($post_id, '_efs_download_status', true);
        return $status ? __('Downloaded', 'encrypted-file-sharing') : __('Pending', 'encrypted-file-sharing');
    }

    /**
     * Get formatted date from post meta.
     *
     * @param int $post_id The ID of the post.
     * @param string $meta_key The meta key to retrieve.
     * @return string Formatted date or a message if not set.
    */

    private function get_formatted_date($post_id, $meta_key)
    {
        $date = get_post_meta($post_id, $meta_key, true);
        return $date ? esc_html(date('Y/m/d \a\t g:i a', strtotime($date))) : __('N/A', 'encrypted-file-sharing');
    }

    /**
     * Get file size for a post.
     *
     * @param int $post_id The ID of the post.
     * @return string File size or a message if not available.
    */

    private function get_file_size($post_id)
    {
        $file_url = get_post_meta($post_id, '_efs_file_url', true);
        if ($file_url) {
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
            } 
            else
            {
                /* Handle WordPress uploads file path */
                $file_size = file_exists($file_path) ? $this->file_display->format_file_size(filesize($file_path)) : __('Unknown size', 'encrypted-file-sharing');
            }
            
            return esc_html($file_size);
        } else {
            return __('No file available', 'encrypted-file-sharing');
        }
    }

    /**
     * Get and format the expiration date for a file.
     *
     * @param int $post_id The ID of the post.
     * @return string Formatted expiration date or a message if not set.
    */

    private function get_expiration_date_display($post_id)
    {
        $file_url = get_post_meta($post_id, '_efs_file_url', true);
        $file_name = $this->extract_file_name($file_url);
        $expiry_date = $this->get_expiration_date($post_id);

        if ($expiry_date) {
            return esc_html(date('Y/m/d \a\t g:i a', strtotime($expiry_date)));
        } else {
            return __('No expiry set', 'encrypted-file-sharing');
        }
    }

    /**
     * Determine the file status based on the expiration date.
     *
     * @param int $post_id The ID of the post.
     * @return string Status message.
    */

    private function get_file_status($post_id)
    {
        $file_url = get_post_meta($post_id, '_efs_file_url', true);
        $file_name = $this->extract_file_name($file_url);
        $expiry_date = $this->get_expiration_date($file_name);

        if ($expiry_date) {
            $current_date = date('Y-m-d');
            return $expiry_date < $current_date ? __('Expired', 'encrypted-file-sharing') : __('Active', 'encrypted-file-sharing');
        } else {
            return __('No expiry set', 'encrypted-file-sharing');
        }
    }

    /**
     * Extract file name from file URL.
     *
     * @param string $file_url The URL of the file.
     * @return string The extracted file name.
    */

    public function extract_file_name($file_url)
    {
        $file_path = parse_url($file_url, PHP_URL_PATH);
        $file_name = basename($file_path);
        return substr($file_name, -4) === '.enc' ? substr($file_name, 0, -4) : $file_name;
    }

    /**
     * Get and format the expiration date for a file from the encryption keys table.
     *
     * @param int $post_id The ID of the post.
     * @return string Formatted expiration date or a message if not set.
    */

    public function get_expiration_date($post_id)
    {
        global $wpdb;

        /* Table name for encryption keys */
        $table_name = $wpdb->prefix . 'efs_encryption_keys';
        
        /* Prepare and run the query to get the expiration_date using post_id */
        $query = $wpdb->prepare("SELECT expiration_date FROM $table_name WHERE post_id = %d", $post_id);
        
        /* Get the expiration date */
        return $wpdb->get_var($query);

    }
}