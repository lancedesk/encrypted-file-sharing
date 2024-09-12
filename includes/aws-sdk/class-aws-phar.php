<?php
/**
 * Download and extract the AWS PHAR file
*/

class EFS_Aws_Phar
{
    private $aws_phar_url;
    private $local_directory;

    function __construct()
    {
        $this->aws_phar_url = 'https://lancedesk.tech/aws-sdk/aws.phar';
        $this->local_directory = __DIR__ . '/aws-sdk';

        /* Ensure WP_Filesystem is available */
        if ( ! function_exists('get_filesystem_method') || ! get_filesystem_method() )
        {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }

        $this->download_and_extract_phar();
        $this->include_autoloader();
    }

    /**
     * Download and extract the PHAR file
    */

    private function download_and_extract_phar()
    {
        global $wp_filesystem;

        $phar_file = $this->local_directory . '/aws.phar';
        $local_extracted_dir = $this->local_directory;

        /* Ensure the directory exists */
        if ( ! $wp_filesystem->is_dir($local_extracted_dir) )
        {
            $wp_filesystem->mkdir($local_extracted_dir);
        }

        /* Check if the PHAR file exists */
        if ( ! $wp_filesystem->exists($phar_file) )
        {
            $response = wp_remote_get($this->aws_phar_url);
            if ( is_wp_error($response) || wp_remote_retrieve_response_code($response) != 200 )
            {
                throw new Exception('Failed to download PHAR file.');
            }

            $phar_content = wp_remote_retrieve_body($response);
            if ( $phar_content === false ) {
                throw new Exception('Failed to retrieve PHAR content.');
            }

            $wp_filesystem->put_contents($phar_file, $phar_content);
        }

        /* Extract the PHAR file */
        $phar = new Phar($phar_file);
        $phar->extractTo($local_extracted_dir);
    }

    /* Include the autoloader */
    private function include_autoloader()
    {
        $autoloader = $this->local_directory . '/vendor/autoload.php';

        if ( file_exists($autoloader) )
        {
            require_once $autoloader;
        }
        else
        {
            throw new Exception('Autoloader not found.');
        }
    }
}