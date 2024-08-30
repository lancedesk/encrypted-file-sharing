<?php

class EFS_Encryption
{
    public function __construct()
    {
        /* TODO: Implement encryption */
    }

    /**
     * Encrypt the file using OpenSSL.
     *
     * @param string $file_path The file path to encrypt.
     * @param string $encryption_key A key to encrypt the file.
     * @return string|false The path to the encrypted file on success, false on failure.
    */

    private function encrypt_file($file_path, $encryption_key)
    {
        $output_file = $file_path . '.enc';
        $iv = openssl_random_pseudo_bytes(16);

        $file_data = file_get_contents($file_path);
        if ($file_data === false) {
            return false;
        }

        $encrypted_data = openssl_encrypt($file_data, 'AES-256-CBC', $encryption_key, 0, $iv);
        if ($encrypted_data === false) {
            return false;
        }

        /* Store the encrypted file and IV */
        file_put_contents($output_file, $iv . $encrypted_data);

        /* Remove the original file for security */
        unlink($file_path);

        return $output_file;
    }

}