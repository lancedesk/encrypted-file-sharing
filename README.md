# Encrypted File Sharing

**Encrypted File Sharing** is a WordPress plugin that allows site administrators to securely send files to specific users via the WordPress admin panel. The plugin ensures that files are encrypted and only accessible to the intended recipient. Both admins and users are notified about file uploads and downloads, and strict security measures, such as two-factor authentication (2FA) and remote file storage, are implemented.

## Features

- **Secure File Upload**: Admin can upload files via the WordPress admin dashboard for specific users.
- **Encrypted Files**: Files are encrypted to ensure only the intended recipient can access them.
- **Secure Remote Storage**: Files can be stored remotely on Amazon S3, Google Drive, or Dropbox, with pre-signed URLs for secure access.
- **Notifications**: Both admin and users receive notifications for file uploads and downloads.
- **Two-Factor Authentication (2FA)**: 2FA is required for both the admin and users to access the plugin.
- **Media Support**: Video and audio files can be streamed securely without downloading.
- **Frontend User Dashboard**: Users can log in and download files securely from their personalized front-end dashboard.
- **File Expiration**: Pre-signed URLs for files have an expiration date, ensuring the file cannot be accessed indefinitely.
- **Admin Panel Protection**: Extra protection for the plugin settings with password and/or token-based authentication.

## Installation

1. Download the `encrypted-file-sharing.zip` from GitHub.
2. Upload the plugin to your WordPress site via the admin dashboard under `Plugins > Add New`.
3. Activate the plugin via the `Plugins` menu in WordPress.
4. Configure the plugin by going to `Settings > Encrypted File Sharing`.

## Usage

1. **Admin**: 
    - Navigate to the plugin's admin panel.
    - Select a user to send a file to.
    - Upload a file (which will be stored on a remote location such as Amazon S3 or Google Drive).
    - User will be notified of the file upload.
2. **User**:
    - Login with 2FA protection.
    - Go to your user dashboard to see available files.
    - Download or stream the file securely.

## File Encryption

- All files are encrypted during upload and stored in an encrypted format. Only the intended recipient can access the files.
- Pre-signed URLs for file access ensure that unauthorized users cannot access the file, even if they somehow obtain the URL.

## Two-Factor Authentication (2FA)

- The plugin integrates with existing 2FA plugins (e.g., Google Authenticator, Duo) to enforce security on both admin and user sides.

## File Structure

```text
encrypted-file-sharing/
│
├── assets/
│   ├── css/
│   └── js/
│   └── images/
│
├── includes/
│   ├── classes/
│   │   ├── class-file-handler.php
│   │   ├── class-encryption.php
│   │   ├── class-notification.php
│   │   ├── class-user-dashboard.php
│   │   ├── class-2fa-auth.php
│   │   └── class-admin-protection.php
│   ├── api/
│   │   ├── amazon-s3.php
│   │   ├── google-drive.php
│   │   └── dropbox.php
│   └── functions/
│       ├── file-management.php
│       ├── user-permissions.php
│       ├── encryption-helper.php
│       └── email-notifications.php
│
├── templates/
│   ├── frontend/
│   │   ├── dashboard.php
│   │   └── file-list.php
│   └── admin/
│       ├── file-upload.php
│       ├── settings.php
│       └── user-selection.php
│
├── languages/
│   └── encrypted-file-sharing.pot
│
├── encrypted-file-sharing.php
├── README.md
└── LICENSE
