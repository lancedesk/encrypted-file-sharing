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
        $selected_users = $this->get_recipients_from_db($post->ID);

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
            update_post_meta($post_id, '_efs_user_selection', $selected_users);

            /* Save user selection to transient for immediate use */
            set_transient('efs_user_selection_' . $post_id, $selected_users, 12 * HOUR_IN_SECONDS);

            /* Set flag meta when user selection is fully saved */
            update_post_meta($post_id, '_efs_user_selection_saved', true);
        } else {
            delete_post_meta($post_id, '_efs_user_selection');

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
        $wpdb->delete($table_name, ['post_id' => $post_id]);

        /* Insert each recipient */
        foreach ($recipients as $recipient_id)
        {
            $wpdb->insert(
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
        }
    }


}