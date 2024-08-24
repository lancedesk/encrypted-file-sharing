<?php

class EFS_File_Display {

    /**
     * Constructor to initialize actions and hooks.
    */

    public function __construct() {
        /* Register shortcode */
        add_shortcode('efs_user_files', array($this, 'render_user_files_shortcode'));

        /* Register Elementor widget */
        add_action('elementor/widgets/widgets_registered', array($this, 'register_elementor_widget'));
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
                            echo '<li><a href="' . esc_url($file_url) . '">' . get_the_title() . '</a></li>';
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
     * Register the Elementor widget.
    */

    public function register_elementor_widget() {
        \Elementor\Plugin::instance()->widgets_manager->register_widget_type(new EFS_User_Files_Widget());
    }
}

/**
 * Elementor Widget for displaying user files.
*/

class EFS_User_Files_Widget extends \Elementor\Widget_Base {

    public function get_name() {
        return 'efs_user_files';
    }

    public function get_title() {
        return __('EFS User Files', 'encrypted-file-sharing');
    }

    public function get_icon() {
        return 'eicon-code';
    }

    public function get_categories() {
        return ['basic'];
    }

    protected function _register_controls() {
        /* Widget controls */
    }

    protected function render() {
        /* Output buffering to capture shortcode content */
        echo do_shortcode('[efs_user_files]');
    }

    protected function _content_template() {
        /* Render widget content in Elementor editor */
        ?>
        <# if ( 'undefined' !== typeof elementorFrontend ) { #>
            <div class="elementor-widget-container">
                <?php echo do_shortcode('[efs_user_files]'); ?>
            </div>
        <# } #>
        <?php
    }
}