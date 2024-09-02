jQuery(document).ready(function($) {
    /* Handle click event for decrypt / download button */
    $('.download-btn').on('click', function(e) {
        e.preventDefault();
        var fileId = $(this).data('file-id');

        $.ajax({
            url: efsAdminAjax.ajax_url, /* Use localized variable for AJAX URL */
            type: 'POST',
            data: {
                action: 'efs_handle_download',
                file_id: fileId,
                security: efsAdminAjax.nonce /* Use the global variable for security nonce */
            },
            success: function(response) {
                if (response.success) {
                    /* The file download will be triggered automatically by PHP */
                    alert('Download started.');
                } else {
                    alert(response.data.message);  /* Show error if decryption/download fails */
                }
            },
            error: function() {
                alert('Failed to download file. Please try again.');
            }
        });
    });

});