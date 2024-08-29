<?phprequire_once 'class-file-handler.php'; /* Include the file handler class */require_once 'class-user-selection.php'; /* Include the user selection class */class EFS_File_CPT{    private $file_handler; /* File handler instance */    private $user_selection; /* User selection instance */    /**     * Constructor to initialize actions and hooks.     */    public function __construct()    {        $this->file_handler = new EFS_File_Handler(); /* Instantiate the file handler */        $this->user_selection = new EFS_User_Selection(); /* Instantiate user selection */        /* Hook for initializing the custom post type */        add_action('init', array($this, 'register_file_cpt'));        /* Hook for adding admin menus */        add_action('admin_menu', array($this, 'add_settings_menu'));        /* Hook for adding meta boxes */        add_action('add_meta_boxes', array($this, 'add_file_meta_box'));        add_action('add_meta_boxes', array($this, 'add_expiry_date_meta_box'));        /* Hook for saving meta box data */        add_action('save_post', array($this, 'save_file_meta_box_data'));        add_action('save_post', array($this, 'save_expiry_meta_box_data'));    }    /**     * Register the custom post type for files.    */    public function register_file_cpt()    {        $labels = array(            'name'               => __('Files', 'encrypted-file-sharing'),            'singular_name'      => __('File', 'encrypted-file-sharing'),            'menu_name'          => __('File Manager', 'encrypted-file-sharing'),            'name_admin_bar'     => __('File', 'encrypted-file-sharing'),            'add_new'            => __('Add New File', 'encrypted-file-sharing'),            'add_new_item'       => __('Add New File', 'encrypted-file-sharing'),            'edit_item'          => __('Edit File', 'encrypted-file-sharing'),            'new_item'           => __('New File', 'encrypted-file-sharing'),            'view_item'          => __('View File', 'encrypted-file-sharing'),            'all_items'          => __('All Files', 'encrypted-file-sharing'),            'search_items'       => __('Search Files', 'encrypted-file-sharing'),        );        $args = array(            'labels'             => $labels,            'public'             => false,            'publicly_queryable' => false,            'show_ui'            => true,            'show_in_menu'       => true,            'query_var'          => true,            'capability_type'    => 'post',            'has_archive'        => false,            'hierarchical'       => false,            'supports'           => array('title', 'editor', 'author', 'thumbnail', 'excerpt'),            'taxonomies'         => array('category'), /* Support for categories */            'menu_icon'          => 'dashicons-media-document', /* Icon for the post type */        );        register_post_type('efs_file', $args);    }    /**     * Add settings submenu under CPT menu.    */    public function add_settings_menu()    {        add_submenu_page(            'edit.php?post_type=efs_file', /* Parent slug (the slug of the CPT menu) */            __('EFS Settings', 'encrypted-file-sharing'), // Page title */            __('Settings', 'encrypted-file-sharing'), /* Menu title */            'manage_options', /* Capability */            'efs-settings', /* Menu slug */            array($this, 'settings_page_content') /* Callback function */        );    }    /**     * Display content of the settings page.    */    public function settings_page_content()    {        /* Fetch the list of S3 buckets */        $buckets = $this->file_handler->get_stored_s3_buckets(); /* Use file_handler instance */        /* Check if the private folder exists */        $private_dir = ABSPATH . '../private_uploads/';        $folder_exists = file_exists($private_dir);         /* Handle form submission for AWS settings, storage, admin email, and other settings */        if ($_SERVER['REQUEST_METHOD'] === 'POST')         {            /* Save AWS region */            if (isset($_POST['efs_aws_region'])) {                update_option('efs_aws_region', sanitize_text_field($_POST['efs_aws_region']));            }            /* Save AWS access key */            if (isset($_POST['efs_aws_access_key'])) {                update_option('efs_aws_access_key', sanitize_text_field($_POST['efs_aws_access_key']));            }            /* Save AWS secret key */            if (isset($_POST['efs_aws_secret_key'])) {                update_option('efs_aws_secret_key', sanitize_text_field($_POST['efs_aws_secret_key']));            }            /* Save selected S3 bucket */            if (isset($_POST['efs_aws_bucket'])) {                update_option('efs_aws_bucket', sanitize_text_field($_POST['efs_aws_bucket']));            }            if (isset($_POST['efs_storage_option'])) {                update_option('efs_storage_option', sanitize_text_field($_POST['efs_storage_option']));            }            if (isset($_POST['efs_admin_email'])) {                update_option('efs_admin_email', sanitize_email($_POST['efs_admin_email']));            }            /* Handle notification checkbox */            $efs_send_notifications = isset($_POST['efs_send_notifications']) ? 1 : 0;            update_option('efs_send_notifications', $efs_send_notifications);            /* Handle file expiry checkbox */            $efs_enable_expiry = isset($_POST['efs_enable_expiry']) ? 1 : 0;            update_option('efs_enable_expiry', $efs_enable_expiry);            /* Handle file expiry period and unit */            if (isset($_POST['efs_expiry_period'])) {                update_option('efs_expiry_period', intval($_POST['efs_expiry_period']));            }            if (isset($_POST['efs_expiry_unit'])) {                update_option('efs_expiry_unit', sanitize_text_field($_POST['efs_expiry_unit']));            }            /* Handle file privacy option */            $efs_file_privacy = isset($_POST['efs_file_privacy']) ? 1 : 0;            update_option('efs_file_privacy', $efs_file_privacy);        }        /* Retrieve current options */        $selected_storage = get_option('efs_storage_option', 'local');        $selected_admin_email = get_option('efs_admin_email', get_option('admin_email'));        $send_notifications = get_option('efs_send_notifications', 0);  /* Default to 0 if not set */        $enable_expiry = get_option('efs_enable_expiry', 0);  /* Default to 0 if not set */        $expiry_period = get_option('efs_expiry_period', 7); /* Default to 7 days */        $expiry_unit = get_option('efs_expiry_unit', 'days'); /* Default to days */        $file_privacy = get_option('efs_file_privacy', 0); /* Default to public */                /* Retrieve current AWS options */        $aws_region = get_option('efs_aws_region', '');        $aws_access_key = get_option('efs_aws_access_key', '');        $aws_secret_key = get_option('efs_aws_secret_key', '');        $aws_bucket = get_option('efs_aws_bucket', '');        /* Get list of administrators */        $users = get_users(array('role' => 'administrator'));        echo '<div class="wrap">';        echo '<h1>' . __('EFS Settings', 'encrypted-file-sharing') . '</h1>';        /* AWS Settings Form */        echo '<form method="post" action="">';        echo '<h2>' . __('Amazon S3 Settings', 'encrypted-file-sharing') . '</h2>';                /* AWS Region */        echo '<label for="efs_aws_region">' . __('AWS Region', 'encrypted-file-sharing') . '</label>';        echo '<input type="text" id="efs_aws_region" name="efs_aws_region" value="' . esc_attr($aws_region) . '"><br>';        /* AWS Access Key */        echo '<label for="efs_aws_access_key">' . __('AWS Access Key', 'encrypted-file-sharing') . '</label>';        echo '<input type="text" id="efs_aws_access_key" name="efs_aws_access_key" value="' . esc_attr($aws_access_key) . '"><br>';        /* AWS Secret Key (Password field) */        echo '<label for="efs_aws_secret_key">' . __('AWS Secret Key', 'encrypted-file-sharing') . '</label>';        echo '<input type="password" id="efs_aws_secret_key" name="efs_aws_secret_key" value="' . esc_attr($aws_secret_key) . '"><br>';        /* Bucket Creation and Fetch Section */        echo '<h3>' . __('Create New Bucket', 'encrypted-file-sharing') . '</h3>';        /* echo '<label for="efs_new_bucket">' . __('New Bucket Name', 'encrypted-file-sharing') . '</label>'; */        echo '<input type="text" id="efs_new_bucket" name="efs_new_bucket" placeholder="' . __('Enter bucket name', 'encrypted-file-sharing') . '">';        echo '<button id="efs_create_bucket" type="button">' . __('Create Bucket', 'encrypted-file-sharing') . '</button>';        echo '<div id="efs_create_bucket_message"></div>';                /* S3 Bucket Checklist (will be populated by AJAX) */        echo '<h3>' . __('Select S3 Buckets', 'encrypted-file-sharing') . '</h3>';        echo '<div id="efs_bucket_checklist">';        if ($buckets) {            foreach ($buckets as $bucket) {                echo '<label><input type="checkbox" name="efs_aws_buckets[]" value="' . esc_attr($bucket) . '" checked>' . esc_html($bucket) . '</label><br>';            }        }        echo '</div>';        echo '<button id="efs_fetch_buckets" type="button">' . __('Fetch Buckets', 'encrypted-file-sharing') . '</button>';        submit_button(__('Save AWS Settings', 'encrypted-file-sharing'), 'primary', 'save_aws_settings_button');        echo '</form>';        /* Storage Option Settings */        echo '<form method="post" action="">';        echo '<h2>' . __('Select Storage Option', 'encrypted-file-sharing') . '</h2>';        echo '<select name="efs_storage_option">';        echo '<option value="local"' . selected($selected_storage, 'local', false) . '>' . __('Local Media', 'encrypted-file-sharing') . '</option>';        echo '<option value="amazon"' . selected($selected_storage, 'amazon', false) . '>' . __('Amazon S3', 'encrypted-file-sharing') . '</option>';        echo '<option value="google"' . selected($selected_storage, 'google', false) . '>' . __('Google Drive', 'encrypted-file-sharing') . '</option>';        echo '<option value="dropbox"' . selected($selected_storage, 'dropbox', false) . '>' . __('Dropbox', 'encrypted-file-sharing') . '</option>';        echo '</select>';        submit_button(__('Save Storage Settings', 'encrypted-file-sharing'), 'primary', 'save_storage_settings_button');        echo '</form>';        /* Admin Email Settings */        echo '<form method="post" action="">';        echo '<h2>' . __('Select Admin to Receive Notifications', 'encrypted-file-sharing') . '</h2>';        echo '<select name="efs_admin_email">';        foreach ($users as $user) {            echo '<option value="' . esc_attr($user->user_email) . '" ' . selected($selected_admin_email, $user->user_email, false) . '>';            echo esc_html($user->display_name . ' (' . $user->user_email . ')');            echo '</option>';        }        echo '</select>';        /* Checkbox for sending notifications */        echo '<h2>' . __('Enable Notifications', 'encrypted-file-sharing') . '</h2>';        echo '<label for="efs_send_notifications">';        echo '<input type="checkbox" id="efs_send_notifications" name="efs_send_notifications" value="1"' . checked(1, $send_notifications, false) . '>';        echo __('Send notifications to selected admin', 'encrypted-file-sharing');        echo '</label>';        /* Checkbox for enabling file expiry */        echo '<h2>' . __('Enable File Expiry', 'encrypted-file-sharing') . '</h2>';        echo '<label for="efs_enable_expiry">';        echo '<input type="checkbox" id="efs_enable_expiry" name="efs_enable_expiry" value="1"' . checked(1, $enable_expiry, false) . '>';        echo __('Enable expiration for downloaded files', 'encrypted-file-sharing');        echo '</label>';        /* Expiry period and unit */        echo '<h2>' . __('Set File Expiry Period', 'encrypted-file-sharing') . '</h2>';        echo '<label for="efs_expiry_period">';        echo __('Expire files after', 'encrypted-file-sharing') . ' ';        echo '<input type="number" id="efs_expiry_period" name="efs_expiry_period" value="' . esc_attr($expiry_period) . '" min="1">';        echo '</label>';        echo '<select name="efs_expiry_unit">';        echo '<option value="minutes"' . selected($expiry_unit, 'minutes', false) . '>' . __('Minutes', 'encrypted-file-sharing') . '</option>';        echo '<option value="hours"' . selected($expiry_unit, 'hours', false) . '>' . __('Hours', 'encrypted-file-sharing') . '</option>';        echo '<option value="days"' . selected($expiry_unit, 'days', false) . '>' . __('Days', 'encrypted-file-sharing') . '</option>';        echo '</select>';        /* Checkbox for file privacy */        echo '<h2>' . __('Set File Privacy', 'encrypted-file-sharing') . '</h2>';        echo '<label for="efs_file_privacy">';        echo '<input type="checkbox" id="efs_file_privacy" name="efs_file_privacy" value="1"' . checked(1, $file_privacy, false) . '>';        echo __('Set files as private', 'encrypted-file-sharing');        echo '</label>';        submit_button(__('Save Settings', 'encrypted-file-sharing'), 'primary', 'save_settings_button');        echo '</form>';         /* Display note about private folder location */        if ($folder_exists)        {            echo '<div class="notice notice-success is-dismissible">';            echo '<p>' . sprintf(__('The private folder is located at: %s', 'encrypted-file-sharing'), esc_html($private_dir)) . '</p>';            echo '</div>';        }         else        {            echo '<div class="notice notice-error is-dismissible">';            echo '<p>' . __('The private folder does not exist. Please create it manually.', 'encrypted-file-sharing') . '</p>';            echo '</div>';        }        echo '</div>';    }        /**     * Add meta box for file uploads.     */    public function add_file_meta_box()    {        add_meta_box(            'efs_file_upload',            __('File Upload', 'encrypted-file-sharing'),            array($this, 'render_file_meta_box'),            'efs_file',            'side',            'high'        );    }    /**     * Render the file upload meta box.     */    public function render_file_meta_box($post)    {        /* Nonce field for verification */        wp_nonce_field('efs_file_meta_box', 'efs_file_meta_box_nonce');        /* Get existing file URL */        $file_url = get_post_meta($post->ID, '_efs_file_url', true);        echo '<p>';        echo '<label for="efs_file_url">' . __('File URL:', 'encrypted-file-sharing') . '</label>';        echo '<input type="text" id="efs_file_url" name="efs_file_url" value="' . esc_attr($file_url) . '" size="25" />';        echo '<button type="button" class="button" id="upload_file_button">' . __('Upload/Select File', 'encrypted-file-sharing') . '</button>';        echo '</p>';        /* Enqueue media uploader scripts */        echo '<script type="text/javascript">        jQuery(document).ready(function($) {            var mediaUploader;            $("#upload_file_button").click(function(e) {                e.preventDefault();                if (mediaUploader) {                    mediaUploader.open();                    return;                }                mediaUploader = wp.media.frames.file_frame = wp.media({                    title: "' . __('Select File', 'encrypted-file-sharing') . '",                    button: {                        text: "' . __('Use this file', 'encrypted-file-sharing') . '"                    },                    multiple: false                });                mediaUploader.on("select", function() {                    var attachment = mediaUploader.state().get("selection").first().toJSON();                                        /* Send the file to the server for S3 upload */                    var formData = new FormData();                    formData.append("action", "upload_to_s3");                    formData.append("file_url", attachment.url);                    formData.append("file_id", attachment.id);                    $.ajax({                        url: efsAdminAjax.ajax_url,  /* Use localized ajax_url */                        type: "POST",                        data: formData,                        contentType: false,                        processData: false,                        success: function(response) {                            if (response.success) {                                $("#efs_file_url").val(response.data.presigned_url);                            } else {                                alert("' . __('File upload failed', 'encrypted-file-sharing') . '");                            }                        }                    });                });                mediaUploader.open();            });        });        </script>';    }    /**     * Save the file URL meta box data.    */    public function save_file_meta_box_data($post_id)    {        /* Check if our nonce is set. */        if (!isset($_POST['efs_file_meta_box_nonce'])) {            return;        }        /* Verify that the nonce is valid. */        if (!wp_verify_nonce($_POST['efs_file_meta_box_nonce'], 'efs_file_meta_box')) {            return;        }        /* Check if this is an autosave. */        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {            return;        }        /* Check the user's permissions. */        if ('efs_file' !== $_POST['post_type']) {            return;        }        if (!current_user_can('edit_post', $post_id)) {            return;        }        /* Sanitize user input. */        $file_url = isset($_POST['efs_file_url']) ? sanitize_text_field($_POST['efs_file_url']) : '';        /* Handle the file upload if a file is provided */        if (isset($_FILES['file']) && !empty($_FILES['file']['name'])) {            $upload_result = $this->file_handler->handle_file_upload($_FILES['file']);            if ($upload_result) {                /* Update the meta field with the new file URL if upload was successful */                update_post_meta($post_id, '_efs_file_url', $upload_result);            }        } elseif (!empty($file_url)) {            /* Update file URL if it's provided in the form */            update_post_meta($post_id, '_efs_file_url', $file_url);        }    }    /**     * Render the expiry date meta box.    */    public function render_expiry_date_meta_box($post)    {        /* Nonce field for verification */        wp_nonce_field('efs_expiry_meta_box', 'efs_expiry_meta_box_nonce');        /* Get existing expiry date */        $expiry_date = get_post_meta($post->ID, '_efs_file_expiry_date', true);        echo '<p>';        echo '<label for="efs_file_expiry_date">' . __('Expiry Date:', 'encrypted-file-sharing') . '</label>';        echo '<input type="date" id="efs_file_expiry_date" name="efs_file_expiry_date" value="' . esc_attr($expiry_date) . '" />';        echo '</p>';    }    /**     * Save the expiry date meta box data.    */    public function save_expiry_meta_box_data($post_id)    {        /* Check if our nonce is set. */        if (!isset($_POST['efs_expiry_meta_box_nonce'])) {            return;        }        /* Verify that the nonce is valid. */        if (!wp_verify_nonce($_POST['efs_expiry_meta_box_nonce'], 'efs_expiry_meta_box')) {            return;        }        /* Check if this is an autosave. */        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {            return;        }        /* Check the user's permissions. */        if (!current_user_can('edit_post', $post_id)) {            return;        }        /* Sanitize and save the expiry date */        $expiry_date = isset($_POST['efs_file_expiry_date']) ? sanitize_text_field($_POST['efs_file_expiry_date']) : '';        update_post_meta($post_id, '_efs_file_expiry_date', $expiry_date);    }}