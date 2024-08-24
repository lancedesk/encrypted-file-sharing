jQuery(document).ready(function($) {
    /* Handle click event for download buttons */
    $('.download-btn').click(function(e) {
        e.preventDefault();

        /* Get file ID from the data attribute of the button */
        var fileID = $(this).data('file-id');

        /* Prepare AJAX request data */
        var actionData = {
            action: 'efs_handle_download', /*  Action name */
            file_id: fileID, /*  File ID to download */
            security: efsAdminAjax.nonce /*  Security nonce */
        };

        /*  Perform AJAX request */
        $.ajax({
            url: efsAdminAjax.ajax_url,
            type: 'POST',
            data: actionData,
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                } else {
                    alert(response.data.message);
                }
            },
            error: function() {
                alert('An error occurred.');
            }
        });
    });
});