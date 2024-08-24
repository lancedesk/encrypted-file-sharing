<?php 
class EFS_File_Display {

/**
 * Constructor to initialize actions and hooks.
*/

public function __construct() {
    /* Register shortcode */
    add_shortcode('efs_user_files', array($this, 'render_user_files_shortcode'));
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

        /* Query for files associated with the logged-in user */
        $args = array(
            'post_type'      => 'efs_file',
            'posts_per_page' => -1,
            'meta_query'     => array(
                array(
                    'key'     => '_efs_user_selection',
                    'value'   => $current_user_id,
                    'compare' => 'LIKE'
                )
            )
        );

        $query = new WP_Query($args);

        if ($query->have_posts()) {
            echo '<div class="efs-user-files">';
            
            /* Display files categorized by their categories */
            $categories = get_terms(array(
                'taxonomy'   => 'category',
                'hide_empty' => true
            ));

            foreach ($categories as $category) {
                echo '<h2>' . esc_html($category->name) . '</h2>';
                echo '<ul>';

                while ($query->have_posts()) {
                    $query->the_post();
                    
                    if (has_term($category->term_id, 'category')) {
                        $file_url = get_post_meta(get_the_ID(), '_efs_file_url', true);
                        $file_type = pathinfo($file_url, PATHINFO_EXTENSION);
                        $icon = $this->get_file_type_icon($file_type);

                        /* Excerpt and description logic */
                        $excerpt = get_the_excerpt();
                        $description = get_the_content();

                        echo '<li>';
                        
                        /* Display file type icon */
                        echo '<span class="file-icon">' . $icon . '</span>';
                        
                        /* Display title */
                        echo '<a href="' . esc_url($file_url) . '">' . get_the_title() . '</a>';
                        
                        /* Show excerpt or description */
                        if (!empty($excerpt)) {
                            echo '<p>' . esc_html($excerpt) . '</p>';
                        } elseif (!empty($description)) {
                            echo '<p>' . esc_html(wp_trim_words($description, 20)) . '</p>';
                        }

                        /* Download button */
                        echo '<a href="' . esc_url($file_url) . '" class="download-btn">' . __('Download', 'encrypted-file-sharing') . '</a>';
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
        echo '<p>' . __('You need to be logged in to view your files.', 'encrypted-file-sharing') . '</p>';
    }

    /* Return output buffer content */
    return ob_get_clean();
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
    $base_path = plugins_url('assets/images/', dirname(__FILE__));

    switch (strtolower($file_type)) 
    {
        case 'pdf':
            return '<img src="' . esc_url($base_path . 'pdf.png') . '" alt="PDF Icon" />';
        case 'doc':
        case 'docx':
            return '<img src="' . esc_url($base_path . 'doc.png') . '" alt="Word Icon" />';
        case 'mp3':
        case 'wav':
            return '<img src="' . esc_url($base_path . 'mp3.png') . '" alt="Audio Icon" />';
        case 'avi':
            return '<img src="' . esc_url($base_path . 'avi.png') . '" alt="AVI Icon" />';
        case 'csv':
            return '<img src="' . esc_url($base_path . 'csv.png') . '" alt="CSV Icon" />';
        case 'jpg':
        case 'jpeg':
            return '<img src="' . esc_url($base_path . 'jpg.png') . '" alt="JPG Icon" />';
        case 'mp4':
            return '<img src="' . esc_url($base_path . 'mp4.png') . '" alt="MP4 Icon" />';
        case 'ppt':
        case 'pptx':
            return '<img src="' . esc_url($base_path . 'ppt.png') . '" alt="PowerPoint Icon" />';
        case 'rtf':
            return '<img src="' . esc_url($base_path . 'rtf.png') . '" alt="RTF Icon" />';
        case 'zip':
            return '<img src="' . esc_url($base_path . 'zip.png') . '" alt="ZIP Icon" />';
        default:
            return '<img src="' . esc_url($base_path . 'default.png') . '" alt="File Icon" />';
    }
}

}