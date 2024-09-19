<?php

class EFS_Utilities
{
    /**
     * Constructor to add the action hook.
    */

    public function __construct()
    {
        /* Hook the delete function to before_delete_post action. */
        add_action('before_delete_post', [$this, 'efs_delete_post_data']);
    }

    /**
     * Delete corresponding custom table data when a post is deleted.
     *
     * @param int $post_id ID of the post being deleted.
    */

    public function efs_delete_post_data($post_id)
    {
        global $wpdb;

        /* Check if it's a valid post ID and not an auto-draft or revision. */
        if (get_post_status($post_id) === 'auto-draft' || wp_is_post_revision($post_id)) 
        {
            return;
        }

        /* Table names */
        $efs_files_table = $wpdb->prefix . 'efs_files';
        $efs_file_metadata_table = $wpdb->prefix . 'efs_file_metadata';
        $efs_encryption_keys_table = $wpdb->prefix . 'efs_encryption_keys';
        $efs_encrypted_files_table = $wpdb->prefix . 'efs_encrypted_files';
        $efs_recipients_table = $wpdb->prefix . 'efs_recipients';

        /* Delete from custom tables where post_id matches the deleted post */
        $wpdb->delete($efs_files_table, array('post_id' => $post_id), array('%d'));
        $wpdb->delete($efs_file_metadata_table, array('post_id' => $post_id), array('%d'));
        $wpdb->delete($efs_encryption_keys_table, array('post_id' => $post_id), array('%d'));
        $wpdb->delete($efs_encrypted_files_table, array('post_id' => $post_id), array('%d'));
        $wpdb->delete($efs_recipients_table, array('post_id' => $post_id), array('%d'));
    }
}

/* Instantiate the class to trigger the action */
$efs_post_manager = new EFS_Utilities();