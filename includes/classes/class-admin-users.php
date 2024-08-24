<?php

class EFS_Admin_Users
{
    /**
     * Constructor to initialize actions and hooks.
     */
    public function __construct()
    {
        /* Hook for plugin activation to create the table */
        register_activation_hook(__FILE__, array($this, 'create_admin_table'));
    }

    /**
     * Create the database table for storing admin user information.
     */
    public function create_admin_table()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'efs_admin_users';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            username varchar(60) NOT NULL,
            password varchar(255) NOT NULL,
            email varchar(100) NOT NULL,
            two_factor_code varchar(255),
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}