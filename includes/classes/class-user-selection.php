<?php

class EFS_User_Selection
{
    private $logger;
    /**
     * Constructor to initialize hooks.
    */

    public function __construct()
    {
        /* Hook to add user selection meta box */
        add_action('add_meta_boxes', [$this, 'efs_add_user_selection_meta_box']);
        /* Hook to save user selection data */
        add_action('save_post', [$this, 'efs_save_user_selection_meta_box_data']);

        $this->logger = new EFS_Init();
    }

    /**
     * Add meta box for user selection.
    */

    public function efs_add_user_selection_meta_box()
    {
        add_meta_box(
            'efs_user_selection',
            __('User Selection', 'encrypted-file-sharing'),
            [$this, 'efs_render_user_selection_meta_box'],
            'efs_file',
            'side',
            'high'
        );
    }

    /**
     * Render the user selection meta box.
    */

    public function efs_render_user_selection_meta_box($post)
    {
        /* Nonce field for verification */
        wp_nonce_field('efs_user_selection_meta_box', 'efs_user_selection_meta_box_nonce');

        /* Get recipients from the database */
        $selected_users = $this->efs_get_recipients_from_db($post->ID)['results'];

        /* Get all users for selection */
        $all_users = get_users(['fields' => ['ID', 'display_name', 'user_email']]);

        echo '<p>';
        echo '<label for="efs_user_selection">' . esc_html__('Select Users:', 'encrypted-file-sharing') . '</label>';
        echo '<select id="efs_user_selection" name="efs_user_selection[]" multiple="multiple" style="width:100%;">';
        
        foreach ($all_users as $user)
        {
            echo '<option value="' . esc_attr($user->ID) . '" ' . (in_array($user->ID, (array) $selected_users) ? 'selected="selected"' : '') . '>' . esc_html($user->display_name) . ' - ' . esc_html($user->user_email) . '</option>';
        }

        echo '</select>';
        echo '</p>';
    }

    /**
     * Save the user selection meta box data.
    */

    public function efs_save_user_selection_meta_box_data($post_id)
    {
        $log_file = WP_CONTENT_DIR . '/efs_save_user_selection_log.txt';

        /* Log start of function execution */
        $this->logger->log_message($log_file, 'Started efs_save_user_selection_meta_box_data for post ID: ' . $post_id);

        /* Check if nonce is set */
        if (!isset($_POST['efs_user_selection_meta_box_nonce']))
        {
            $this->logger->log_message($log_file, 'Nonce not set. Exiting.');
            return;
        }

        /* Verify nonce */
        if (!isset($_POST['efs_user_selection_meta_box_nonce']) || !wp_verify_nonce(wp_unslash(sanitize_key($_POST['efs_user_selection_meta_box_nonce'])), 'efs_user_selection_meta_box')) 
        {
            $this->logger->log_message($log_file, 'Nonce verification failed for post ID: ' . $post_id);
            return;
        }

        /* Check autosave */
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
        {
            $this->logger->log_message($log_file, 'Incorrect post type. Exiting.');
            return;
        }

        /* Check permissions */
        if (!isset($_POST['post_type']) || 'efs_file' !== $_POST['post_type']) 
        {
            $this->logger->log_message($log_file, 'User does not have permission to edit post ID: ' . $post_id);
            return;
        }

        if (!current_user_can('edit_post', $post_id))
        {
            return;
        }

        /* Sanitize and save user selection */
        if (isset($_POST['efs_user_selection']) && is_array($_POST['efs_user_selection']))
        {
            $selected_users = array_map('intval', $_POST['efs_user_selection']);

            $this->logger->log_message($log_file, 'Selected users: ' . implode(', ', $selected_users));

            /* Save selected recipients to the database */
            $result = $this->efs_save_recipients_to_db($post_id, $selected_users);

            if ($result === false)
            {
                $this->logger->log_message($log_file, 'Failed to save recipients to the database for post ID: ' . $post_id);
                return;
            }
            else
            {
                global $efs_local_file_handler;

                $this->logger->log_message($log_file, 'Recipients saved successfully.');

                /* Handle file encryption for the post */
                if (isset($efs_local_file_handler) && is_object($efs_local_file_handler)) 
                {
                    $this->logger->log_message($log_file, 'Starting file encryption for post ID: ' . $post_id);
                    $efs_local_file_handler->efs_handle_file_encryption($post_id);
                    $this->logger->log_message($log_file, 'File encryption completed for post ID: ' . $post_id);
                }

                /* Set flag meta when user selection is fully saved */
                update_post_meta($post_id, '_efs_user_selection_saved', true);
                $this->logger->log_message($log_file, 'Meta updated for post ID: ' . $post_id);
            }

        } else {
            $this->logger->log_message($log_file, 'No users selected for post ID: ' . $post_id);

            /* If no users selected, delete from the database */
            $this->efs_save_recipients_to_db($post_id, []);

            /* Remove the saved flag if no users are selected */
            delete_post_meta($post_id, '_efs_user_selection_saved');
            $this->logger->log_message($log_file, 'Meta deleted for post ID: ' . $post_id);
        }

        /* Log end of function execution */
        $this->logger->log_message($log_file, 'Finished efs_save_user_selection_meta_box_data for post ID: ' . $post_id);
    }

    /**
     * Save selected recipients to the 'efs_recipients' table.
     *
     * @param int $post_id The ID of the post to assign recipients.
     * @param array $recipients Array of recipient IDs to save.
     *
     * @return void
    */

    public function efs_save_recipients_to_db($post_id, $recipients)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'efs_recipients';

        /* Delete existing recipients for this post */
        /* phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query, caching not applicable */
        $delete_result = $wpdb->delete($table_name, ['post_id' => $post_id]);

        /* Check if delete operation failed */
        if ($delete_result === false)
        {
            return false;
        }

        /* Insert each recipient */
        foreach ($recipients as $recipient_id)
        {
            /* phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table insert operation required */
            $insert_result = $wpdb->insert(
                $table_name,
                [
                    'post_id' => $post_id,
                    'recipient_id' => $recipient_id
                ],
                [
                    '%d',
                    '%d'
                ]
            );

            /* Check if insert operation failed */
            if ($insert_result === false)
            {
                return false;
            }
        }

        /* Return true if all operations were successful */
        return true;
    }

    /**
     * Retrieve recipients from the 'efs_recipients' table for a post.
     *
     * @param int $post_id The ID of the post.
     *
     * @return array Array of recipient IDs.
    */

    public function efs_get_recipients_from_db($post_id)
    {
        global $wpdb;

        /* Get all recipients for the post */
        /* phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table query, caching not applicable */
        $results = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT recipient_id FROM {$wpdb->prefix}efs_recipients WHERE post_id = %d",
                $post_id
            )
        );

        /* Response array with result and query*/
        $response = [
            'results' => $results ? $results : [],
            'query'   => $wpdb->last_query  /* Store the last executed query for debugging */
        ];

        return $response;
    }

}