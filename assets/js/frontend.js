jQuery(document).ready(function($)
{
    /* Handle click event for decrypt/download button */
    $('.download-btn').on('click', function(e)
    {
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

/* Handle click event for file details button */
document.addEventListener('DOMContentLoaded', function()
{
    /* Get all modals, info buttons and close buttons */
    var modals = document.querySelectorAll('.modal');
    var infoButtons = document.querySelectorAll('.info-btn');
    var closeButtons = document.querySelectorAll('.close');

    /* Handle click event for file details button */
    infoButtons.forEach(function(button)
    {
        button.addEventListener('click', function(e)
        {
            e.preventDefault();
            var fileId = button.getAttribute('data-file-id');
            var modal = document.getElementById('fileDetailsModal-' + fileId);
            if (modal)
            {
                modal.style.display = 'block';
            }
        });
    });

    /* Handle click event for close button */
    closeButtons.forEach(function(button)
    {
        button.addEventListener('click', function()
        {
            var modalId = button.getAttribute('data-modal-id');
            var modal = document.getElementById(modalId);
            if (modal)
            {
                modal.style.display = 'none';
            }
        });
    });

    /* Handle click event for closing modal by clicking outside */
    window.addEventListener('click', function(e)
    {
        if (e.target.classList.contains('modal'))
        {
            e.target.style.display = 'none';
        }
    });
});