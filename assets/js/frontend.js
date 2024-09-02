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
                security: efs_download_nonce /* Use the global variable for security nonce */
            },
            success: function(response) {
                if (response.success) {
                    /* Trigger download */
                    var blob = new Blob([response.data], { type: 'application/octet-stream' });
                    var link = document.createElement('a');
                    link.href = window.URL.createObjectURL(blob);
                    link.download = response.filename;
                    link.click();
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