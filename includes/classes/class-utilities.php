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

        /* Check if the post type is 'efs_file' */
        $post_type = get_post_type($post_id);
        if ($post_type !== 'efs_file') 
        {
            return;
        }

        /* Step 1: Retrieve file metadata based on the post_id */
        $efs_file_metadata_table = $wpdb->prefix . 'efs_file_metadata';

        $file_metadata = $wpdb->get_row(
            $wpdb->prepare("SELECT file_id FROM $efs_file_metadata_table WHERE post_id = %d",
            $post_id
            )
        );

        if (!$file_metadata) 
        {
            return;  /* No metadata found, exit */
        }

        /* Step 2: Get file_id from from file metadata */
        $file_id = $file_metadata->file_id;

        /* Table names */
        $efs_files_table = $wpdb->prefix . 'efs_files';
        $efs_encryption_keys_table = $wpdb->prefix . 'efs_encryption_keys';
        $efs_encrypted_files_table = $wpdb->prefix . 'efs_encrypted_files';
        $efs_recipients_table = $wpdb->prefix . 'efs_recipients';

        /* Step 3: Delete related rows in efs_encrypted_files using file_id */
        $wpdb->delete($efs_encrypted_files_table, array('file_id' => $file_id), array('%d'));

        /* Step 4: Delete related rows in efs_recipients using post_id */
        $wpdb->delete($efs_recipients_table, array('post_id' => $post_id), array('%d'));

        /* Step 5: Delete related rows in efs_encryption_keys using file_id */
        $wpdb->delete($efs_encryption_keys_table, array('file_id' => $file_id), array('%d'));

        /* Step 6: Delete related rows in efs_file_metadata using post_id */
        $wpdb->delete($efs_file_metadata_table, array('post_id' => $post_id), array('%d'));

        /* Step 7: Finally, delete the row in efs_files using file_id */
        $wpdb->delete($efs_files_table, array('id' => $file_id), array('%d'));
    }

}

/* Instantiate the class to trigger the action */
$efs_post_manager = new EFS_Utilities();
