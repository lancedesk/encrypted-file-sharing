<?php

class EFS_Admin_Users
{
    /**
     * Constructor to initialize actions and hooks.
     */
    public function __construct()
    {
        /* Hook for plugin activation to create the table */
        /* register_activation_hook(__FILE__, array($this, 'create_admin_table')); */
        register_activation_hook(plugin_dir_path(__FILE__) . '../encrypted-file-sharing.php', array($this, 'create_admin_table'));
        /* register_deactivation_hook( __FILE__, "deactivate_myplugin" ); */
    }

    /**
     * Create the database table for storing admin user information.
     */
    public function create_admin_table()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'efs_admin_users';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            username varchar(60) NOT NULL,
            password varchar(255) NOT NULL,
            email varchar(100) NOT NULL,
            two_factor_code varchar(255),
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $result = dbDelta($sql);

        /* Handle the result or potential errors */
        $log_message = '';

        if ( is_wp_error( $result ) ) 
        {
            $error_message = $result->get_error_message();
            $log_message = "Table creation failed: $error_message";
        } 
        else if ( empty($result) ) 
        {
            $log_message = "No changes made to the database.";
        } 
        else 
        {
            $log_message = "Table $table_name created successfully.";
        }

        /* Write the log message to a file in wp-content */
        $log_file = WP_CONTENT_DIR . '/efs_admin_user_table_log.txt';
        file_put_contents($log_file, $log_message . PHP_EOL, FILE_APPEND);
    }
}