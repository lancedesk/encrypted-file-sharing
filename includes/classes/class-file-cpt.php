<?php

class EFS_File_CPT
{
    /**
     * Constructor to initialize actions and hooks.
    */

    public function __construct()
    {
        /* Hook for initializing the custom post type */
        add_action('init', [$this, 'efs_register_file_cpt']);

        /* Hook for adding meta boxes */
        add_action('add_meta_boxes', [$this, 'efs_add_file_meta_box']);
        add_action('add_meta_boxes', [$this, 'efs_add_expiry_date_meta_box']);

        /* Hook for saving meta box data */
        add_action('save_post', [$this, 'efs_save_file_meta_box_data']);
        add_action('save_post', [$this, 'efs_save_expiry_meta_box_data']);
    }

    /**
     * Register the custom post type for files.
    */

    public function efs_register_file_cpt()
    {
        $labels = array(
            'name'               => __('Files', 'encrypted-file-sharing'),
            'singular_name'      => __('File', 'encrypted-file-sharing'),
            'menu_name'          => __('File Manager', 'encrypted-file-sharing'),
            'name_admin_bar'     => __('File', 'encrypted-file-sharing'),
            'add_new'            => __('Add New File', 'encrypted-file-sharing'),
            'add_new_item'       => __('Add New File', 'encrypted-file-sharing'),
            'edit_item'          => __('Edit File', 'encrypted-file-sharing'),
            'new_item'           => __('New File', 'encrypted-file-sharing'),
            'view_item'          => __('View File', 'encrypted-file-sharing'),
            'all_items'          => __('All Files', 'encrypted-file-sharing'),
            'search_items'       => __('Search Files', 'encrypted-file-sharing'),
        );

        $args = array(
            'labels'             => $labels,
            'public'             => false,
            'publicly_queryable' => false,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'supports'           => array('title', 'editor', 'author', 'thumbnail', 'excerpt'),
            'taxonomies'         => array('category'), /* Support for categories */
            'menu_icon'          => 'dashicons-media-document', /* Icon for the post type */
        );

        register_post_type('efs_file', $args);
    }

    /**
     * Add meta box for file uploads.
    */

    public function efs_add_file_meta_box()
    {
        add_meta_box(
            'efs_file_upload',
            __('File Upload', 'encrypted-file-sharing'),
            [$this, 'efs_render_file_meta_box'],
            'efs_file',
            'side',
            'high'
        );
    }

    /**
     * Render the file upload meta box.
    */

    public function efs_render_file_meta_box($post)
    {
        /* Nonce field for verification */
        wp_nonce_field('efs_file_meta_box', 'efs_file_meta_box_nonce');

        /* Pass the post ID to JavaScript */
        echo '<script type="text/javascript"> var currentPostId = ' . intval($post->ID) . '; </script>';

        /* Create a nonce for the AJAX request */
        $nonce = wp_create_nonce('efs_upload_nonce');

        /* Retrieve the storage option from the admin settings */
        $storage_options = get_option('efs_storage_option');

        /* Get existing file URL */
        $file_url = get_post_meta($post->ID, '_efs_file_url', true);

        /* Display the existing file URL if available */
        if ($file_url) {
            echo '<p>Current File: <a href="' . esc_url($file_url) . '" target="_blank">View File</a></p>';
        }

        echo '<p>';
        echo '<label for="efs_file_url">' . esc_html__('File URL:', 'encrypted-file-sharing') . '</label>';
        echo '<input type="text" id="efs_file_url" name="efs_file_url" value="' . esc_attr($file_url) . '" size="25" />';
        echo '<button type="button" class="button" id="upload_file_button">' . esc_html__('Upload/Select File', 'encrypted-file-sharing') . '</button>';
        echo '</p>';

    }

    /**
     * Save the file URL meta box data.
    */

    public function efs_save_file_meta_box_data($post_id)
    {
        /* Check nonce and permissions */
        if (!isset($_POST['efs_file_meta_box_nonce']) || !wp_verify_nonce(wp_unslash(sanitize_key($_POST['efs_file_meta_box_nonce'])), 'efs_file_meta_box')) 
        {
        return;
        }

        /* Check if this is an autosave. */
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
        {
            return;
        }

        /* Check the user's permissions. */
        if (isset($_POST['post_type']) && 'efs_file' !== $_POST['post_type'])
        {
            return;
        }

        if (!current_user_can('edit_post', $post_id))
        {
            return;
        }

        /* Sanitize and save the manually entered file URL */
        $file_url = isset($_POST['efs_file_url']) ? sanitize_text_field(wp_unslash($_POST['efs_file_url'])) : '';

        if (!empty($file_url)) 
        {
            update_post_meta($post_id, '_efs_file_url', $file_url);
        }

        /* Handle file upload */
        if (isset($_FILES['file']) && !empty($_FILES['file']['name'])) 
        {
            global $efs_local_file_handler;

            /* Handle the file upload and get the result */
            $result = $efs_local_file_handler->efs_get_upload_data();

            if ($result && isset($result['encrypted_file'])) 
            {
                /* Save the encrypted file path */
                $file_path = $result['encrypted_file'];
                update_post_meta($post_id, '_efs_file_url', $file_path); /* Save the file path instead of media ID */
            }
        }
    }

    /**
     * Add meta box for file expiry date.
    */

    function efs_add_expiry_date_meta_box()
    {
        /* Check if expiry is enabled in the admin settings */
        $efs_enable_expiry = trim(get_option('efs_enable_expiry', 0));
    
        /* Only add the meta box if expiry is enabled */
        if ($efs_enable_expiry === '1')
        {
            /* Check if we are editing an existing post */
            global $post;
    
            /* Ensure the post object exists and it's not a new post */
            if (isset($post->ID))
            {
                $first_published = trim(get_post_meta($post->ID, '_efs_first_published', true));
    
                /* Add the meta box only if the post has been published or saved before */
                if ($first_published === '1')
                {
                    add_meta_box(
                        'efs_expiry_date_meta_box', /* Meta box ID */
                        __('File Expiry Date', 'encrypted-file-sharing'), /* Title */
                        [$this, 'efs_render_expiry_date_meta_box'], /* Callback function */
                        'efs_file', /* Post type */
                        'side', /* Position: 'normal', 'side', or 'advanced' */
                        'high' /* Priority */
                    );
                }
            }
        }
    }    

    /**
     * Render the expiry date meta box.
    */

    public function efs_render_expiry_date_meta_box($post)
    {
        /* An instance of the admin columns class */
        global $efs_admin_columns;

        /* Nonce field for verification */
        wp_nonce_field('efs_expiry_meta_box', 'efs_expiry_meta_box_nonce');

        /* Retrieve expiration date and time from the custom table */
        $file_url = get_post_meta($post->ID, '_efs_file_url', true);
        $file_name = $efs_admin_columns->efs_extract_file_name($file_url);
        $expiry_datetime = $efs_admin_columns->efs_get_expiration_date($file_name);

        /* Split expiry date and time */
        $expiry_date = $expiry_time = '';
        if ($expiry_datetime)
        {
            $expiry_date = esc_attr(gmdate('Y-m-d', strtotime($expiry_datetime)));
            $expiry_time = esc_attr(gmdate('H:i', strtotime($expiry_datetime)));
        }
        
        /* Display the date and time fields */
        echo '<p>';
        echo '<label for="efs_file_expiry_date">' . esc_html__('Expiry Date:', 'encrypted-file-sharing') . '</label>';
        echo '<input type="date" id="efs_file_expiry_date" name="efs_file_expiry_date" value="' . esc_attr($expiry_date) . '" />';
        echo '</p>';

        echo '<p>';
        echo '<label for="efs_file_expiry_time">' . esc_html__('Expiry Time:', 'encrypted-file-sharing') . '</label>';
        echo '<input type="time" id="efs_file_expiry_time" name="efs_file_expiry_time" value="' . esc_html($expiry_time) . '" />';
        echo '</p>';
    }

    /**
     * Save the expiry date meta box data.
    */

    public function efs_save_expiry_meta_box_data($post_id)
    {
        /* An instance of the admin columns class */
        global $efs_admin_columns;

        /* Check if our nonce is set & verify that the nonce is valid. */
        if (!isset($_POST['efs_expiry_meta_box_nonce']) || !wp_verify_nonce(wp_unslash(sanitize_key($_POST['efs_expiry_meta_box_nonce'])), 'efs_expiry_meta_box'))
        {
            return;
        }

        /* Check if this is an autosave. */
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
        {
            return;
        }

        /* Check the user's permissions. */
        if (!current_user_can('edit_post', $post_id))
        {
            return;
        }

        /* Sanitize and prepare date and time inputs */
        $expiry_date = isset($_POST['efs_file_expiry_date']) ? sanitize_text_field(wp_unslash($_POST['efs_file_expiry_date'])) : '';
        $expiry_time = isset($_POST['efs_file_expiry_time']) ? sanitize_text_field(wp_unslash($_POST['efs_file_expiry_time'])) : '';

        /* Combine date and time */
        $expiry_datetime = $expiry_date . ' ' . $expiry_time;

        /* Retrieve file name or URL */
        $file_url = get_post_meta($post_id, '_efs_file_url', true);
        $file_name = $efs_admin_columns->efs_extract_file_name($file_url);

        /* Update expiry date in custom table */
        global $wpdb;
        $table_name = $wpdb->prefix . 'efs_file_metadata';

        /* Insert or update expiration date in the custom table */
        /* phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table query, caching not applicable */
        $wpdb->replace(
            $table_name,
            array(
                'file_name' => $file_name,
                'expiration_date' => $expiry_datetime
            ),
            array(
                '%s', '%s'
            )
        );
    }

}