<?php 

class EFS_File_Display
{

	/**
	 * Constructor to initialize actions and hooks.
	*/

	public function __construct()
    {
		/* Register shortcode */
		add_shortcode('efs_user_files', [$this, 'efs_render_user_files_shortcode']);
	}

	/**
	 * Render the user files shortcode.
	*/

	public function efs_render_user_files_shortcode($atts)
    {
		/* Start output buffering */
		ob_start();

		/* Ensure user is logged in */
		if (is_user_logged_in())
        {
            global $wpdb;
			$current_user_id = get_current_user_id();

            /* Get all post IDs associated with the current user as a recipient */
            /* phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table query, caching not applicable */
            $post_ids = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT post_id FROM {$wpdb->prefix}efs_recipients WHERE recipient_id = %d",
                    $current_user_id
                )
            );

			if (!empty($post_ids))
            {
				$args = [
					'post_type'      => 'efs_file',
					'posts_per_page' => -1,
					'post__in'       => $post_ids,
					'orderby'        => 'post_date',
					'order'          => 'DESC'
				];

				$query = new WP_Query($args);

				if ($query->have_posts())
                {
					echo '<div class="efs-user-files">';
					
					/* Display files categorized by their categories */
					$categories = get_terms([
						'taxonomy'   => 'category',
						'hide_empty' => true
					]);

					foreach ($categories as $category)
                    {
						echo '<h2>' . esc_html($category->name) . '</h2>';

						while ($query->have_posts()) {
							$query->the_post();
							
							if (has_term($category->term_id, 'category'))
                            {
								$file_url = get_post_meta(get_the_ID(), '_efs_file_url', true);

								/* Extract the file path from the URL */
								$file_path = wp_parse_url($file_url, PHP_URL_PATH);  /* Extract just the path part */
								$file_name = basename($file_path);  /* Get the file name, e.g., mom-pdf.pdf.enc */

								/* Check and strip the .enc extension */
								if (substr($file_name, -4) === '.enc') {
									$file_name = substr($file_name, 0, -4);  /* Strip the .enc part */
								}

								/* Retrieve file extension separately if needed */
								$file_type = pathinfo($file_name, PATHINFO_EXTENSION);

								$icon = $this->efs_get_file_type_icon($file_type);

								/* Convert file URL to file path */
								$upload_dir = wp_upload_dir();
								$relative_path = str_replace($upload_dir['baseurl'], '', $file_url);
								$file_path = $upload_dir['basedir'] . $relative_path;

								/* Check if the file path contains 'private_uploads' */
								$is_secure = strpos($file_path, 'private_uploads') !== false;

								/* Get file size */
								if ($is_secure)
                                {
									/* Handle secure file path */
									$file_size = file_exists($relative_path) ? $this->efs_format_file_size(filesize($relative_path)) : __('Unknown size', 'encrypted-file-sharing');
								}
                                else
                                {
									/* Handle WordPress uploads file path */
									$file_size = file_exists($file_path) ? $this->efs_format_file_size(filesize($file_path)) : __('Unknown size', 'encrypted-file-sharing');
								}

								/* Get upload date and format it to show time */
								$upload_date = get_the_date('F j, Y \a\t g:i A');

                                echo '<div class="efs-file-row">';
								
                                    /* Display file type icon */
                                    /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $icon is safely escaped */
                                    echo '<span class="file-icon">' . $icon . '</span>';
                                    
                                    /* Display file name */
                                    echo '<div class="file-name"><p>' . esc_html(get_the_title()) . '</p></div>';

                                    /* Display upload/creation date */
                                    echo '<div class="file-date"><p>' . esc_html($upload_date) . '</p></div>';

                                    /* Wrap file details (info, download) */
                                    echo '<div class="file-details">';
                                        /* Eye icon for more info (with modal or popup trigger) */
                                        echo '<a href="#" class="info-btn" data-file-id="' . esc_attr(get_the_ID()) . '"><i class="fas fa-eye"></i></a>';

                                        /* Download button */
                                        echo '<a href="#" class="download-btn" data-file-id="' . esc_attr(get_the_ID()) . '"><i class="fas fa-download"></i></a>';
                                    echo '</div>'; /* Close file-details div */

                                    /* Modal content */
                                    $modal_content = $this->efs_get_modal_content(get_the_ID(), $file_size);
                                    /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $modal_content content are safely escaped */
                                    echo $modal_content;
                                    
                                echo '</div>';
							}
						}

					}

					echo '</div>';
				} else {
                    echo "<div class='efs-no-files-found'>";
                        echo '<p><i class="fas fa-folder-open"></i> ' . esc_html__('No files found for you.', 'encrypted-file-sharing') . '</p>';
                    echo "<div>";
				}

				wp_reset_postdata();
			} else {
				echo "<div class='efs-no-files-found'>";
                    echo '<p><i class="fas fa-folder-open"></i> ' . esc_html__('No files found for you.', 'encrypted-file-sharing') . '</p>';
                echo "<div>";
			}
		} else {
            echo "<div class='efs-login-first'>";
                echo '<p><i class="fas fa-user-lock"></i> ' . esc_html__('You need to be logged in to view your files.', 'encrypted-file-sharing') . '</p>';
            echo "<div>";
		}

		/* Return output buffer content */
		return ob_get_clean();
	}

    /**
     * Get the modal content for the file details.
     *
     * @param int $file_id The file ID.
     * @param string $file_size The file size.
     * @return string Modal content HTML.
    */

    public function efs_get_modal_content($file_id, $file_size)
    {
        global $efs_admin_columns;

        /* Get the file details */
        $excerpt = get_the_excerpt($file_id);
        $description = esc_html(get_post_field('post_content', $file_id));
        $expiration = $efs_admin_columns->efs_get_expiration_date_display($file_id);
        $upload_date = get_the_date('F j, Y \a\t g:i A', $file_id);
        
        /* Build the modal content with a unique ID */
        $modal_content = '<div id="fileDetailsModal-' . esc_attr($file_id) . '" class="modal" style="display: none;">';
        $modal_content .= '<div class="modal-content">';
        $modal_content .= '<span class="close" data-modal-id="fileDetailsModal-' . esc_attr($file_id) . '">&times;</span>';
        $modal_content .= '<p><strong>File Size:</strong> ' . esc_html($file_size) . '</p>';
        $modal_content .= '<p><strong>Expiration:</strong> ' . esc_html($expiration) . '</p>';
        $modal_content .= '<p><strong>Description:</strong> ' . (!empty($excerpt) ? esc_html($excerpt) : wp_trim_words($description, 20)) . '</p>';
        $modal_content .= '<p><strong>Uploaded:</strong> ' . esc_html($upload_date) . '</p>';
        $modal_content .= '</div></div>';
    
        return $modal_content;
    }    

	/**
	 * Format file size to a human-readable format.
	*/

	public function efs_format_file_size($size)
	{
		if ($size >= 1073741824)
        {
			$size = number_format($size / 1073741824, 2) . ' GB';
		}
        elseif ($size >= 1048576)
        {
			$size = number_format($size / 1048576, 2) . ' MB';
		}
        elseif ($size >= 1024)
        {
			$size = number_format($size / 1024, 2) . ' KB';
		}
        else
        {
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

    private function efs_get_file_type_icon($file_type) 
    {
        /* Use dynamic base path to the icons folder */
        $base_path = plugin_dir_url(__FILE__) . '../../assets/images/';
        $icon_dimension = 'width="16px" height="16px"'; /* Add width and height dimensions */

        $allowed_html = array(
            'img' => array(
                'src'    => true,
                'alt'    => true,
                'width'  => true,
                'height' => true,
            ),
        );

        switch (strtolower($file_type)) 
        {
            case 'pdf':
                $icon = '<img src="' . esc_url($base_path . 'pdf.png') . '" alt="PDF Icon" ' . $icon_dimension . ' />';
                break;
            case 'doc':
            case 'docx':
                $icon = '<img src="' . esc_url($base_path . 'doc.png') . '" alt="Word Icon" ' . $icon_dimension . ' />';
                break;
            case 'mp3':
            case 'wav':
                $icon = '<img src="' . esc_url($base_path . 'mp3.png') . '" alt="Audio Icon" ' . $icon_dimension . ' />';
                break;
            case 'avi':
                $icon = '<img src="' . esc_url($base_path . 'avi.png') . '" alt="AVI Icon" ' . $icon_dimension . ' />';
                break;
            case 'csv':
                $icon = '<img src="' . esc_url($base_path . 'csv.png') . '" alt="CSV Icon" ' . $icon_dimension . ' />';
                break;
            case 'jpg':
            case 'jpeg':
                $icon = '<img src="' . esc_url($base_path . 'jpg.png') . '" alt="JPG Icon" ' . $icon_dimension . ' />';
                break;
            case 'mp4':
                $icon = '<img src="' . esc_url($base_path . 'mp4.png') . '" alt="MP4 Icon" ' . $icon_dimension . ' />';
                break;
            case 'ppt':
            case 'pptx':
                $icon = '<img src="' . esc_url($base_path . 'ppt.png') . '" alt="PowerPoint Icon" ' . $icon_dimension . ' />';
                break;
            case 'rtf':
                $icon = '<img src="' . esc_url($base_path . 'rtf.png') . '" alt="RTF Icon" ' . $icon_dimension . ' />';
                break;
            case 'zip':
                $icon = '<img src="' . esc_url($base_path . 'zip.png') . '" alt="ZIP Icon" ' . $icon_dimension . ' />';
                break;
            default:
                $icon = '<img src="' . esc_url($base_path . 'default.png') . '" alt="File Icon" ' . $icon_dimension . ' />';
                break;
        }

        /* Sanitize and return the HTML */
        return wp_kses($icon, $allowed_html);
    }

}