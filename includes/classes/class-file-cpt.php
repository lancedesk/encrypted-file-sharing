<?php

require_once 'class-file-handler.php'; /* Include the file handler class */
require_once 'class-user-selection.php'; /* Include the user selection class */

class EFS_File_CPT
{
    /**
     * Constructor to initialize actions and hooks.
     */
    public function __construct()
    {
        $this->file_handler = new EFS_File_Handler(); /* Instantiate the file handler */
        $this->user_selection = new EFS_User_Selection(); /* Instantiate user selection */

        /* Hook for initializing the custom post type */
        add_action('init', array($this, 'register_file_cpt'));

        /* Hook for adding admin menus */
        add_action('admin_menu', array($this, 'add_settings_menu'));

        /* Hook for adding meta boxes */
        add_action('add_meta_boxes', array($this, 'add_file_meta_box'));

        /* Hook for saving meta box data */
        add_action('save_post', array($this, 'save_file_meta_box_data'));
    }

    /**
     * Register the custom post type for files.
    */

    public function register_file_cpt()
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
     * Add settings submenu under CPT menu.
    */

    public function add_settings_menu()
    {
        add_submenu_page(
            'edit.php?post_type=efs_file', /* Parent slug (the slug of the CPT menu) */
            __('EFS Settings', 'encrypted-file-sharing'), // Page title */
            __('Settings', 'encrypted-file-sharing'), /* Menu title */
            'manage_options', /* Capability */
            'efs-settings', /* Menu slug */
            array($this, 'settings_page_content') /* Callback function */
        );
    }

    /**
     * Display content of the settings page.
    */

    public function settings_page_content()
    {
        if (isset($_POST['efs_storage_option'])) {
            update_option('efs_storage_option', sanitize_text_field($_POST['efs_storage_option']));
        }

        $selected_storage = get_option('efs_storage_option', 'local');

        echo '<div class="wrap">';
        echo '<h1>' . __('EFS Settings', 'encrypted-file-sharing') . '</h1>';
        echo '<form method="post" action="">';
        echo '<h2>' . __('Select Storage Option', 'encrypted-file-sharing') . '</h2>';
        echo '<select name="efs_storage_option">';
        echo '<option value="local"' . selected($selected_storage, 'local', false) . '>' . __('Local Media', 'encrypted-file-sharing') . '</option>';
        echo '<option value="amazon"' . selected($selected_storage, 'amazon', false) . '>' . __('Amazon S3', 'encrypted-file-sharing') . '</option>';
        echo '<option value="google"' . selected($selected_storage, 'google', false) . '>' . __('Google Drive', 'encrypted-file-sharing') . '</option>';
        echo '<option value="dropbox"' . selected($selected_storage, 'dropbox', false) . '>' . __('Dropbox', 'encrypted-file-sharing') . '</option>';
        echo '</select>';
        submit_button(__('Save Settings', 'encrypted-file-sharing'));
        echo '</form>';
        echo '</div>';
    }

    /**
     * Add meta box for file uploads.
     */
    public function add_file_meta_box()
    {
        add_meta_box(
            'efs_file_upload',
            __('File Upload', 'encrypted-file-sharing'),
            array($this, 'render_file_meta_box'),
            'efs_file',
            'side',
            'high'
        );
    }

    /**
     * Render the file upload meta box.
     */
    public function render_file_meta_box($post)
    {
        /* Nonce field for verification */
        wp_nonce_field('efs_file_meta_box', 'efs_file_meta_box_nonce');

        /* Get existing file URL */
        $file_url = get_post_meta($post->ID, '_efs_file_url', true);

        echo '<p>';
        echo '<label for="efs_file_url">' . __('File URL:', 'encrypted-file-sharing') . '</label>';
        echo '<input type="text" id="efs_file_url" name="efs_file_url" value="' . esc_attr($file_url) . '" size="25" />';
        echo '<button type="button" class="button" id="upload_file_button">' . __('Upload/Select File', 'encrypted-file-sharing') . '</button>';
        echo '</p>';

        /* Enqueue media uploader scripts */
        echo '<script type="text/javascript">
            jQuery(document).ready(function($) {
                var mediaUploader;
                $("#upload_file_button").click(function(e) {
                    e.preventDefault();
                    if (mediaUploader) {
                        mediaUploader.open();
                        return;
                    }
                    mediaUploader = wp.media.frames.file_frame = wp.media({
                        title: "' . __('Select File', 'encrypted-file-sharing') . '",
                        button: {
                            text: "' . __('Use this file', 'encrypted-file-sharing') . '"
                        },
                        multiple: false
                    });
                    mediaUploader.on("select", function() {
                        var attachment = mediaUploader.state().get("selection").first().toJSON();
                        $("#efs_file_url").val(attachment.url);
                    });
                    mediaUploader.open();
                });
            });
            </script>';
    }

    /**
     * Save the file URL meta box data.
    */

    public function save_file_meta_box_data($post_id)
    {
        /* Check if our nonce is set. */
        if (!isset($_POST['efs_file_meta_box_nonce'])) {
            return;
        }

        /* Verify that the nonce is valid. */
        if (!wp_verify_nonce($_POST['efs_file_meta_box_nonce'], 'efs_file_meta_box')) {
            return;
        }

        /* Check if this is an autosave. */
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        /* Check the user's permissions. */
        if ('efs_file' !== $_POST['post_type']) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        /* Sanitize user input. */
        $file_url = sanitize_text_field($_POST['efs_file_url']);

        /* Handle the file upload if a file is provided */
        if (isset($_FILES['file']) && !empty($_FILES['file']['name'])) {
            $upload_result = $this->file_handler->handle_file_upload($_FILES['file']);

            if ($upload_result) {
                /* Update the meta field with the new file URL if upload was successful */
                update_post_meta($post_id, '_efs_file_url', $upload_result);
            }
        } else {
            /* Update file URL if it's provided in the form */
            update_post_meta($post_id, '_efs_file_url', $file_url);
        }
    }
}