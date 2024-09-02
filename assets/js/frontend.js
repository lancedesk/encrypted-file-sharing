jQuery(document).ready(function($) {
    /* Handle click event for decrypt/download button */
    $('.download-btn').on('click', function(e) {
        e.preventDefault();
        var fileId = $(this).data('file-id');

        /* Create a form to submit the download request */
        var downloadForm = $('<form>', {
            action: efsAdminAjax.ajax_url, /* Use localized variable for the AJAX URL */
            method: 'POST',
            style: 'display: none'
        }).append($('<input>', {
            type: 'hidden',
            name: 'action',
            value: 'efs_handle_download'
        })).append($('<input>', {
            type: 'hidden',
            name: 'file_id',
            value: fileId
        })).append($('<input>', {
            type: 'hidden',
            name: 'security',
            value: efsAdminAjax.nonce /* Use the global variable for the security nonce */
        }));

        /* Append form to body and submit */
        $('body').append(downloadForm);
        downloadForm.submit();
    });
});