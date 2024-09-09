<?php

class EFS_User_Selection
{
    /**
     * Constructor to initialize hooks.
    */

    public function __construct()
    {
        /* Hook to add user selection meta box */
        add_action('add_meta_boxes', [$this, 'add_user_selection_meta_box']);
        /* Hook to save user selection data */
        add_action('save_post', [$this, 'save_user_selection_meta_box_data']);
    }

    /**
     * Add meta box for user selection.
    */

    public function add_user_selection_meta_box()
    {
        add_meta_box(
            'efs_user_selection',
            __('User Selection', 'encrypted-file-sharing'),
            [$this, 'render_user_selection_meta_box'],
            'efs_file',
            'side',
            'high'
        );
    }

    /**
     * Render the user selection meta box.
    */

    public function render_user_selection_meta_box($post)
    {
        /* Nonce field for verification */
        wp_nonce_field('efs_user_selection_meta_box', 'efs_user_selection_meta_box_nonce');

        /* Get recipients from the database */
        $selected_users = $this->get_recipients_from_db($post->ID)['results'];

        /* Get all users for selection */
        $all_users = get_users(['fields' => ['ID', 'display_name', 'user_email']]);

        echo '<p>';
        echo '<label for="efs_user_selection">' . __('Select Users:', 'encrypted-file-sharing') . '</label>';
        echo '<select id="efs_user_selection" name="efs_user_selection[]" multiple="multiple" style="width:100%;">';
        foreach ($all_users as $user) {
            echo '<option value="' . esc_attr($user->ID) . '" ' . (in_array($user->ID, (array) $selected_users) ? 'selected="selected"' : '') . '>' . esc_html($user->display_name) . ' - ' . esc_html($user->user_email) . '</option>';
        }
        echo '</select>';
        echo '</p>';
    }

    /**
     * Save the user selection meta box data.
    */

    public function save_user_selection_meta_box_data($post_id)
    {
        /* Check if nonce is set */
        if (!isset($_POST['efs_user_selection_meta_box_nonce'])) {
            return;
        }

        /* Verify nonce */
        if (!wp_verify_nonce($_POST['efs_user_selection_meta_box_nonce'], 'efs_user_selection_meta_box')) {
            return;
        }

        /* Check autosave */
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        /* Check permissions */
        if ('efs_file' !== $_POST['post_type']) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        /* Sanitize and save user selection */
        if (isset($_POST['efs_user_selection']) && is_array($_POST['efs_user_selection'])) {
            $selected_users = array_map('intval', $_POST['efs_user_selection']);

            /* Save selected recipients to the database */
            $result = $this->save_recipients_to_db($post_id, $selected_users);

            if ($result === false) {
                return;
            }
            else
            {
                global $efs_local_file_handler;

                /* Handle file encryption for the post */
                if (isset($efs_local_file_handler) && is_object($efs_local_file_handler)) 
                {
                    $efs_local_file_handler->handle_file_encryption($post_id);
                }

                /* Set flag meta when user selection is fully saved */
                update_post_meta($post_id, '_efs_user_selection_saved', true);
            }

        } else {
            /* If no users selected, delete from the database */
            $this->save_recipients_to_db($post_id, []);

            /* Remove the saved flag if no users are selected */
            delete_post_meta($post_id, '_efs_user_selection_saved');
        }
    }

    /**
     * Save selected recipients to the 'efs_recipients' table.
     *
     * @param int $post_id The ID of the post to assign recipients.
     * @param array $recipients Array of recipient IDs to save.
     *
     * @return void
    */

    public function save_recipients_to_db($post_id, $recipients)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'efs_recipients';

        /* Delete existing recipients for this post */
        $delete_result = $wpdb->delete($table_name, ['post_id' => $post_id]);

        /* Check if delete operation failed */
        if ($delete_result === false)
        {
            return false;
        }

        /* Insert each recipient */
        foreach ($recipients as $recipient_id)
        {
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

    public function get_recipients_from_db($post_id)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'efs_recipients';

        /* Get all recipients for the post */
        $results = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT recipient_id FROM $table_name WHERE post_id = %d",
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

    /**
     * Logs messages to a file.
     *
     * @param string $log_file Path to the log file.
     * @param string $message The message to log.
    */

    private function log_message($log_file, $message)
    {
        $timestamp = date('Y-m-d H:i:s');
        $formatted_message = '[' . $timestamp . '] ' . $message . PHP_EOL;

        /* Append the message to the log file */
        file_put_contents($log_file, $formatted_message, FILE_APPEND);
    }



}