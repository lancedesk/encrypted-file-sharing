<?php 

class EFS_File_Display
{

/**
 * Constructor to initialize actions and hooks.
*/

public function __construct() {
    /* Register shortcode */
    add_shortcode('efs_user_files', [$this, 'render_user_files_shortcode']);
}

/**
 * Render the user files shortcode.
*/

public function render_user_files_shortcode($atts) {
    /* Start output buffering */
    ob_start();

    /* Ensure user is logged in */
    if (is_user_logged_in()) {
        $current_user_id = get_current_user_id();
        
        /* Query for post IDs where the current user is a recipient */
        global $wpdb;
        $table_name = $wpdb->prefix . 'efs_recipients';

        /* Get all post IDs associated with the current user */
        $post_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT post_id FROM $table_name WHERE recipient_id = %d",
                $current_user_id
            )
        );

        if (!empty($post_ids)) {
            $args = [
                'post_type'      => 'efs_file',
                'posts_per_page' => -1,
                'post__in'       => $post_ids,
                'orderby'        => 'post_date',
                'order'          => 'DESC'
            ];

            $query = new WP_Query($args);

            if ($query->have_posts()) {
                echo '<div class="efs-user-files">';
                
                /* Display files categorized by their categories */
                $categories = get_terms([
                    'taxonomy'   => 'category',
                    'hide_empty' => true
                ]);

                foreach ($categories as $category) {
                    echo '<h2>' . esc_html($category->name) . '</h2>';
                    echo '<ul>';

                    while ($query->have_posts()) {
                        $query->the_post();
                        
                        if (has_term($category->term_id, 'category')) {
                            $file_url = get_post_meta(get_the_ID(), '_efs_file_url', true);

                            /* Extract the file path from the URL */
                            $file_path = parse_url($file_url, PHP_URL_PATH);  /* Extract just the path part */
                            $file_name = basename($file_path);  /* Get the file name, e.g., mom-pdf.pdf.enc */

                            /* Check and strip the .enc extension */
                            if (substr($file_name, -4) === '.enc') {
                                $file_name = substr($file_name, 0, -4);  /* Strip the .enc part */
                            }

                            /* Retrieve file extension separately if needed */
                            $file_type = pathinfo($file_name, PATHINFO_EXTENSION);

                            $icon = $this->get_file_type_icon($file_type);

                            /* Convert file URL to file path */
                            $upload_dir = wp_upload_dir();
                            $relative_path = str_replace($upload_dir['baseurl'], '', $file_url);
                            $file_path = $upload_dir['basedir'] . $relative_path;

                            /* Check if the file path contains 'private_uploads' */
                            $is_secure = strpos($file_path, 'private_uploads') !== false;

                            /* Get file size */
                            if ($is_secure) {
                                /* Handle secure file path */
                                $file_size = file_exists($relative_path) ? $this->format_file_size(filesize($relative_path)) : __('Unknown size', 'encrypted-file-sharing');
                            } else {
                                /* Handle WordPress uploads file path */
                                $file_size = file_exists($file_path) ? $this->format_file_size(filesize($file_path)) : __('Unknown size', 'encrypted-file-sharing');
                            }

                            /* Excerpt and description logic */
                            $excerpt = get_the_excerpt();
                            $description = get_the_content();

                            /* Get upload date and format it to show time */
                            $upload_date = get_the_date('F j, Y \a\t g:i A');

                            echo '<li>';
                            
                            /* Display file type icon */
                            echo '<span class="file-icon">' . $icon . '</span>';
                            
                            /* Display title */
                            echo '<p class="file-title">' . get_the_title() . '</p>';

                            /* Display upload/creation date */
                            echo '<p class="file-date">' . __('Uploaded on: ', 'encrypted-file-sharing') . esc_html($upload_date) . '</p>';
                            
                            /* Display file size */
                            echo '<p class="file-size">' . __('File size: ', 'encrypted-file-sharing') . esc_html($file_size) . '</p>';
                            
                            /* Show excerpt or description */
                            if (!empty($excerpt)) {
                                echo '<p>' . esc_html($excerpt) . '</p>';
                            } elseif (!empty($description)) {
                                echo '<p>' . esc_html(wp_trim_words($description, 20)) . '</p>';
                            }

                            /* Download button */
                            echo '<a href="#" class="download-btn" data-file-id="' . esc_attr(get_the_ID()) . '">' . __('Download', 'encrypted-file-sharing') . '</a>';
                            echo '</li>';
                        }
                    }

                    echo '</ul>';
                }

                echo '</div>';
            } else {
                echo '<p>' . __('No files found for you.', 'encrypted-file-sharing') . '</p>';
            }

            wp_reset_postdata();
        } else {
            echo '<p>' . __('No files found for you.', 'encrypted-file-sharing') . '</p>';
        }
    } else {
        echo '<p>' . __('You need to be logged in to view your files.', 'encrypted-file-sharing') . '</p>';
    }

    /* Return output buffer content */
    return ob_get_clean();
}

/**
 * Format file size to a human-readable format.
*/

public function format_file_size($size)
{
    if ($size >= 1073741824) {
        $size = number_format($size / 1073741824, 2) . ' GB';
    } elseif ($size >= 1048576) {
        $size = number_format($size / 1048576, 2) . ' MB';
    } elseif ($size >= 1024) {
        $size = number_format($size / 1024, 2) . ' KB';
    } else {
        $size = $size . ' bytes';
    }

    return $size;
}

/**
 * Helper function to get file type icons.
 *
 * @param string $file_type The file extension.
 * @return string Icon HTML
*/

private function get_file_type_icon($file_type) 
{
    /* Use dynamic base path to the icons folder */
    $base_path = plugin_dir_url(__FILE__) . '../../assets/images/';
    $icon_dimension = 'width="32px" height="32px"'; /* Add width and height dimensions */

    switch (strtolower($file_type)) 
    {
        case 'pdf':
            return '<img src="' . esc_url($base_path . 'pdf.png') . '" alt="PDF Icon" ' . $icon_dimension . ' />';
        case 'doc':
        case 'docx':
            return '<img src="' . esc_url($base_path . 'doc.png') . '" alt="Word Icon" ' . $icon_dimension . ' />';
        case 'mp3':
        case 'wav':
            return '<img src="' . esc_url($base_path . 'mp3.png') . '" alt="Audio Icon" ' . $icon_dimension . ' />';
        case 'avi':
            return '<img src="' . esc_url($base_path . 'avi.png') . '" alt="AVI Icon" ' . $icon_dimension . ' />';
        case 'csv':
            return '<img src="' . esc_url($base_path . 'csv.png') . '" alt="CSV Icon" ' . $icon_dimension . ' />';
        case 'jpg':
        case 'jpeg':
            return '<img src="' . esc_url($base_path . 'jpg.png') . '" alt="JPG Icon" ' . $icon_dimension . ' />';
        case 'mp4':
            return '<img src="' . esc_url($base_path . 'mp4.png') . '" alt="MP4 Icon" ' . $icon_dimension . ' />';
        case 'ppt':
        case 'pptx':
            return '<img src="' . esc_url($base_path . 'ppt.png') . '" alt="PowerPoint Icon" ' . $icon_dimension . ' />';
        case 'rtf':
            return '<img src="' . esc_url($base_path . 'rtf.png') . '" alt="RTF Icon" ' . $icon_dimension . ' />';
        case 'zip':
            return '<img src="' . esc_url($base_path . 'zip.png') . '" alt="ZIP Icon" ' . $icon_dimension . ' />';
        default:
            return '<img src="' . esc_url($base_path . 'default.png') . '" alt="File Icon" ' . $icon_dimension . ' />';
    }
}


}