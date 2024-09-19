<?php

class EFS_Admin_Settings_Page
{
    public function __construct()
    {
        /* Hook for adding admin menus */
        add_action('admin_menu', [$this, 'efs_add_settings_menu']);
        /* add_action('admin_init', [$this, 'handle_form_submission']); */
    }

    /**
     * Add settings submenu under CPT menu.
    */

    public function efs_add_settings_menu()
    {
        add_submenu_page(
            'edit.php?post_type=efs_file', /* Parent slug (the slug of the CPT menu) */
            __('EFS Settings', 'encrypted-file-sharing'), /* Page title */
            __('Settings', 'encrypted-file-sharing'), /* Menu title */
            'manage_options', /* Capability */
            'efs-settings', /* Menu slug */
            [$this, 'efs_settings_page_content'] /* Callback function */
        );
    }

    /**
     * Display content of the settings page.
    */

    public function efs_settings_page_content()
    {
        global $efs_file_handler; /* Use the global file handler instance */

        /* Fetch the list of S3 buckets */
        $buckets = $efs_file_handler->efs_get_stored_s3_buckets(); /* Use file_handler instance */

        /* Check if the private folder exists */
        $private_dir = realpath(ABSPATH . '../private_uploads/');
        $folder_exists = $private_dir !== false && file_exists($private_dir);

        /* Handle form submission for AWS settings, storage, admin email, and other settings */
        if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST')
        {
            /* Verify nonce */
            if (!isset($_POST['efs_settings_nonce']) || !wp_verify_nonce(wp_unslash(sanitize_key($_POST['efs_settings_nonce'])), 'efs_settings_save')) 
            {
                /* Invalid nonce, handle the error */
                wp_die(esc_html__('Nonce verification failed.', 'encrypted-file-sharing'));
            }

            if (isset($_POST['efs_storage_option'])) 
            {
                update_option('efs_storage_option', sanitize_text_field(wp_unslash($_POST['efs_storage_option'])));
            }

            /* Save AWS region */
            if (isset($_POST['efs_aws_region']))
            {
                update_option('efs_aws_region', sanitize_text_field(wp_unslash($_POST['efs_aws_region'])));
            }

            /* Save AWS access key */
            if (isset($_POST['efs_aws_access_key']))
            {
                update_option('efs_aws_access_key', sanitize_text_field(wp_unslash(sanitize_key($_POST['efs_aws_access_key']))));
            }

            /* Save AWS secret key */
            if (isset($_POST['efs_aws_secret_key']))
            {
                update_option('efs_aws_secret_key', sanitize_text_field(wp_unslash($_POST['efs_aws_secret_key'])));
            }

            /* Save selected S3 bucket */
            if (isset($_POST['efs_aws_bucket']))
            {
                update_option('efs_aws_bucket', sanitize_text_field(wp_unslash($_POST['efs_aws_bucket'])));
            }

            if (isset($_POST['efs_storage_option']))
            {
                update_option('efs_storage_option', sanitize_text_field(wp_unslash($_POST['efs_storage_option'])));
            }

            if (isset($_POST['efs_admin_email']))
            {
                update_option('efs_admin_email', sanitize_email(wp_unslash($_POST['efs_admin_email'])));
            }

            /* Handle admin notification checkbox */
            $efs_send_notifications = isset($_POST['efs_send_notifications']) ? 1 : 0;
            update_option('efs_send_notifications', $efs_send_notifications);

            /* Handle user notification checkbox */
            $efs_enable_user_notifications = isset($_POST['efs_enable_user_notifications']) ? 1 : 0;
            update_option('efs_enable_user_notifications', $efs_enable_user_notifications);

            /* Handle file expiry checkbox */
            $efs_enable_expiry = isset($_POST['efs_enable_expiry']) ? 1 : 0;
            update_option('efs_enable_expiry', $efs_enable_expiry);

            /* Handle file expiry period and unit */
            if (isset($_POST['efs_expiry_period']))
            {
                update_option('efs_expiry_period', intval($_POST['efs_expiry_period']));
            }

            if (isset($_POST['efs_expiry_unit']))
            {
                update_option('efs_expiry_unit', sanitize_text_field(wp_unslash($_POST['efs_expiry_unit'])));
            }

            /* Handle file privacy option */
            $efs_file_privacy = isset($_POST['efs_file_privacy']) ? 1 : 0;
            update_option('efs_file_privacy', $efs_file_privacy);

            /* Handle delete files after encryption checkbox */
            $efs_delete_files = isset($_POST['efs_delete_files']) ? 1 : 0;
            update_option('efs_delete_files', $efs_delete_files);

            /* Handle debug mode checkbox */
            $efs_debug_mode = isset($_POST['efs_debug_mode']) ? 1 : 0;
            update_option('efs_debug_mode', $efs_debug_mode);

            /* Handle uninstall data checkbox */
            $efs_uninstall_data = isset($_POST['efs_uninstall_data']) ? 1 : 0;
            update_option('efs_uninstall_data', $efs_uninstall_data);
        }

        /* Retrieve current options */
        $selected_storage = get_option('efs_storage_option', 'local');
        $selected_admin_email = get_option('efs_admin_email', get_option('admin_email'));
        $send_notifications = get_option('efs_send_notifications', 0);  /* Default to 0 if not set */
        $enable_expiry = get_option('efs_enable_expiry', 0);  /* Default to 0 if not set */
        $expiry_period = get_option('efs_expiry_period', 7); /* Default to 7 days */
        $expiry_unit = get_option('efs_expiry_unit', 'days'); /* Default to days */
        $file_privacy = get_option('efs_file_privacy', 0); /* Default to public */
        $efs_delete_files = get_option('efs_delete_files', 0); /* Default to 0 */
        
        /* Retrieve current AWS options */
        $aws_region = get_option('efs_aws_region', '');
        $aws_access_key = get_option('efs_aws_access_key', '');
        $aws_secret_key = get_option('efs_aws_secret_key', '');
        $aws_bucket = get_option('efs_aws_bucket', '');

        /* Get list of administrators */
        $users = get_users(array('role' => 'administrator'));

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('EFS Settings', 'encrypted-file-sharing') . '</h1>';

        /* Combine all settings into one form */
        echo '<form method="post" action="">';
        wp_nonce_field('efs_settings_save', 'efs_settings_nonce'); /* Add nonce field */
        echo '<h2>' . esc_html__('Amazon S3 Settings', 'encrypted-file-sharing') . '</h2>';
        
        /* AWS Region */
        echo '<label for="efs_aws_region">' . esc_html__('AWS Region', 'encrypted-file-sharing') . '</label>';
        echo '<input type="text" id="efs_aws_region" name="efs_aws_region" value="' . esc_attr($aws_region) . '"><br>';

        /* AWS Access Key */
        echo '<label for="efs_aws_access_key">' . esc_html__('AWS Access Key', 'encrypted-file-sharing') . '</label>';
        echo '<input type="text" id="efs_aws_access_key" name="efs_aws_access_key" value="' . esc_attr($aws_access_key) . '"><br>';

        /* AWS Secret Key (Password field) */
        echo '<label for="efs_aws_secret_key">' . esc_html__('AWS Secret Key', 'encrypted-file-sharing') . '</label>';
        echo '<input type="password" id="efs_aws_secret_key" name="efs_aws_secret_key" value="' . esc_attr($aws_secret_key) . '"><br>';

        /* Bucket Creation and Fetch Section */
        echo '<h3>' . esc_html__('Create New Bucket', 'encrypted-file-sharing') . '</h3>';
        /* echo '<label for="efs_new_bucket">' . esc_html__('New Bucket Name', 'encrypted-file-sharing') . '</label>'; */
        echo '<input type="text" id="efs_new_bucket" name="efs_new_bucket" placeholder="' . esc_html__('Enter bucket name', 'encrypted-file-sharing') . '">';
        echo '<button id="efs_create_bucket" type="button">' . esc_html__('Create Bucket', 'encrypted-file-sharing') . '</button>';
        echo '<div id="efs_create_bucket_message"></div>';
        
        /* S3 Bucket Checklist (will be populated by AJAX) */
        echo '<h3>' . esc_html__('Select S3 Buckets', 'encrypted-file-sharing') . '</h3>';
        echo '<div id="efs_bucket_checklist">';

        if ($buckets)
        {
            foreach ($buckets as $bucket)
            {
                echo '<label><input type="checkbox" name="efs_aws_buckets[]" value="' . esc_attr($bucket) . '" checked>' . esc_html($bucket) . '</label><br>';
            }
        }

        echo '</div>';
        echo '<button id="efs_fetch_buckets" type="button">' . esc_html__('Fetch Buckets', 'encrypted-file-sharing') . '</button>';

        /* Storage Option Settings */
        echo '<h2>' . esc_html__('Select Storage Option', 'encrypted-file-sharing') . '</h2>';
        echo '<select name="efs_storage_option">';
        echo '<option value="local"' . selected($selected_storage, 'local', false) . '>' . esc_html__('Local Media', 'encrypted-file-sharing') . '</option>';
        echo '<option value="amazon"' . selected($selected_storage, 'amazon', false) . '>' . esc_html__('Amazon S3', 'encrypted-file-sharing') . '</option>';
        echo '<option value="google"' . selected($selected_storage, 'google', false) . '>' . esc_html__('Google Drive', 'encrypted-file-sharing') . '</option>';
        echo '<option value="dropbox"' . selected($selected_storage, 'dropbox', false) . '>' . esc_html__('Dropbox', 'encrypted-file-sharing') . '</option>';
        echo '</select>';

        /* Admin Email Settings */
        echo '<h2>' . esc_html__('Select Admin to Receive Notifications', 'encrypted-file-sharing') . '</h2>';
        echo '<select name="efs_admin_email">';

        foreach ($users as $user)
        {
            echo '<option value="' . esc_attr($user->user_email) . '" ' . selected($selected_admin_email, $user->user_email, false) . '>';
            echo esc_html($user->display_name . ' (' . $user->user_email . ')');
            echo '</option>';
        }
        echo '</select>';

        /* Checkbox for sending notifications */
        echo '<h2>' . esc_html__('Enable Notifications', 'encrypted-file-sharing') . '</h2>';
        echo '<label for="efs_send_notifications">';
        echo '<input type="checkbox" id="efs_send_notifications" name="efs_send_notifications" value="1"' . checked(1, $send_notifications, false) . '>';
        echo esc_html__('Send notifications to selected admin', 'encrypted-file-sharing');
        echo '</label>';

        /* Checkbox for user notifications */
        echo '<h2>' . esc_html__('Enable User Notifications on Post Upload', 'encrypted-file-sharing') . '</h2>';
        echo '<label for="efs_enable_user_notifications">';
        echo '<input type="checkbox" id="efs_enable_user_notifications" name="efs_enable_user_notifications" value="1"' . checked(1, get_option('efs_enable_user_notifications', 0), false) . '>';
        echo esc_html__('Send notifications to users when a file is uploaded', 'encrypted-file-sharing');
        echo '</label>';

        /* Checkbox for enabling file expiry */
        echo '<h2>' . esc_html__('Enable File Expiry', 'encrypted-file-sharing') . '</h2>';
        echo '<label for="efs_enable_expiry">';
        echo '<input type="checkbox" id="efs_enable_expiry" name="efs_enable_expiry" value="1"' . checked(1, $enable_expiry, false) . '>';
        echo esc_html__('Enable expiration for downloaded files', 'encrypted-file-sharing');
        echo '</label>';

        /* Expiry period and unit */
        echo '<h2>' . esc_html__('Set File Expiry Period', 'encrypted-file-sharing') . '</h2>';
        echo '<label for="efs_expiry_period">';
        echo esc_html__('Expire files after', 'encrypted-file-sharing') . ' ';
        echo '<input type="number" id="efs_expiry_period" name="efs_expiry_period" value="' . esc_attr($expiry_period) . '" min="1">';
        echo '</label>';

        echo '<select name="efs_expiry_unit">';
        echo '<option value="minutes"' . selected($expiry_unit, 'minutes', false) . '>' . esc_html__('Minutes', 'encrypted-file-sharing') . '</option>';
        echo '<option value="hours"' . selected($expiry_unit, 'hours', false) . '>' . esc_html__('Hours', 'encrypted-file-sharing') . '</option>';
        echo '<option value="days"' . selected($expiry_unit, 'days', false) . '>' . esc_html__('Days', 'encrypted-file-sharing') . '</option>';
        echo '</select>';

        /* Checkbox for file privacy */
        echo '<h2>' . esc_html__('Set File Privacy', 'encrypted-file-sharing') . '</h2>';
        echo '<label for="efs_file_privacy">';
        echo '<input type="checkbox" id="efs_file_privacy" name="efs_file_privacy" value="1"' . checked(1, $file_privacy, false) . '>';
        echo esc_html__('Set files as private', 'encrypted-file-sharing');
        echo '</label>';

        /* Checkbox for deleting files after encryption */
        echo '<h2>' . esc_html__('Delete Files After Encryption', 'encrypted-file-sharing') . '</h2>';
        echo '<label for="efs_delete_files">';
        echo '<input type="checkbox" id="efs_delete_files" name="efs_delete_files" value="1"' . checked(1, get_option('efs_delete_files', 0), false) . '>';
        echo esc_html__('Delete files from media library after encryption', 'encrypted-file-sharing');
        echo '</label>';

        /* Checkbox for enabling/disabling debug mode */
        echo '<h2>' . esc_html__('Enable Debug Logging', 'encrypted-file-sharing') . '</h2>';
        echo '<label for="efs_debug_mode">';
        echo '<input type="checkbox" id="efs_debug_mode" name="efs_debug_mode" value="1"' . checked(1, get_option('efs_debug_mode', 0), false) . '>';
        echo esc_html__('Enable logging of encryption messages for debugging', 'encrypted-file-sharing');
        echo '</label>';

        /* Checkbox for uninstall behavior */
        echo '<h2>' . esc_html__('Uninstall Behavior', 'encrypted-file-sharing') . '</h2>';
        echo '<label for="efs_uninstall_data">';
        echo '<input type="checkbox" id="efs_uninstall_data" name="efs_uninstall_data" value="1" ' . checked(1, get_option('efs_uninstall_data', 0), false) . '>';
        echo esc_html__('Delete all data (database tables and posts) upon plugin uninstall', 'encrypted-file-sharing') . '</label>';

        submit_button(esc_html__('Save Settings', 'encrypted-file-sharing'), 'primary', 'save_settings_button');
        echo '</form>';


        /* Retrieve the selected storage option from the database */
        $selected_storage = get_option('efs_storage_option', 'local');  /* Default to 'local' if no option is set */

        /* Check if Amazon S3 is selected from the stored option */
        if ($selected_storage === 'amazon') 
        {
            global $efs_s3_file_handler;
            $result = $efs_s3_file_handler->efs_initialize_s3_client();

            /* Check if S3 client initialization was successful */
            if (!$result) 
            {
                /* Display error message if S3 is not connected */
                echo '<div class="notice notice-error is-dismissible">';
                echo '<p>' . esc_html__('S3 not connected. Please enter the correct API information to connect.', 'encrypted-file-sharing') . '</p>';
                echo '</div>';
            } 
            else 
            {
                /* Display success message if S3 is connected */
                echo '<div class="notice notice-success is-dismissible">';
                echo '<p>' . esc_html__('S3 client initialized successfully.', 'encrypted-file-sharing') . '</p>';
                echo '</div>';
            }
        }

        /* Display note about private folder location */
        if ($folder_exists)
        {
            echo '<div class="notice notice-success is-dismissible">';
            /* Translators: %s is the path to the private folder. */
            echo '<p>' . sprintf(esc_html__('The private folder is located at: %s', 'encrypted-file-sharing'), esc_html($private_dir)) . '</p>';
            echo '</div>';
        } 
        else
        {
            echo '<div class="notice notice-error is-dismissible">';
            echo '<p>' . esc_html__('The private folder does not exist. Please create it manually.', 'encrypted-file-sharing') . '</p>';
            echo '</div>';
        }

        echo '</div>';
    }  

}