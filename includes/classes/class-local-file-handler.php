<?php

class EFS_Local_File_Handler
{
    /**
     * Constructor to initialize actions and hooks.
     */
    public function __construct()
    {
        /* Hook for plugin activation to create a secure private uploads folder */
        /* register_activation_hook(plugin_dir_path(__FILE__) . '../encrypted-file-sharing.php', [$this, 'efs_create_private_folder']); */
        register_activation_hook(__FILE__, [$this, 'efs_create_private_folder']);
    }

    /**
     * Create a private folder outside the web root
    */

    public function efs_create_private_folder() 
    {
        /* Path outside the web root */
        $private_dir = ABSPATH . '../private_uploads/';

        /* Check if the folder already exists */
        if (!file_exists($private_dir)) 
        {
            /* Try to create the folder */
            if (!mkdir($private_dir, 0755, true)) 
            {
                wp_die('Failed to create private uploads folder. Please create it manually.');
            }
        }
    }

}