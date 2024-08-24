<?php

class EFS_File_CPT
{
    /**
     * Constructor to initialize actions and hooks.
     */
    public function __construct()
    {
        /* Hook for initializing the custom post type */
        add_action('init', array($this, 'register_file_cpt'));
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
}