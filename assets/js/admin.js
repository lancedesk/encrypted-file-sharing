jQuery(document).ready(function($) {
    console.log("Default Admin Ajax URL is: ", ajaxurl);

    $("#upload_file_button").click(function(e) {
        e.preventDefault();
        console.log("Upload button clicked.");
        console.log("Storage option: ", efsAdminAjax.efsStorageOption); /* Access localized variable */

        /* Open the media uploader */
        var file_frame = wp.media.frames.file_frame = wp.media({
            title: efsAdminAjax.efsSelectFileTitle, /* Localized variable */
            button: {
                text: efsAdminAjax.efsSelectFileButtonText /* Localized variable */
            },
            multiple: false
        });

        file_frame.on("select", function() {
            /* Get the selected file */
            var attachment = file_frame.state().get('selection').first().toJSON();
            
            /* Check if the file object exists */
            if (attachment && attachment.id) {
                console.log("File ID:", attachment.id);
                console.log("Attachment attributes: ", attachment);
                console.log("Uploaded To Post ID:", attachment.uploadedTo);

                /* Pass currentPostId (from PHP) as the post_id */
                console.log("Uploaded To Post ID:", currentPostId); /* currentPostId instead of attachment.uploadedTo */

                /* Prepare AJAX request to send file & post IDs */
                var formData = new FormData();
                formData.append("file_id", attachment.id);
                formData.append("post_id", currentPostId); /* Manually pass current post ID for context */
                formData.append("nonce", efsAdminAjax.nonce);

                /* Set the upload action based on storage option */
                var uploadAction = "efs_upload_to_local"; /* Default local storage action */

                switch (efsAdminAjax.efsStorageOption) {
                    case "amazon":
                        uploadAction = "upload_to_s3";
                        break;
                    case "google":
                        uploadAction = "upload_to_google";
                        break;
                    case "dropbox":
                        uploadAction = "upload_to_dropbox";
                        break;
                }

                formData.append("action", uploadAction);

                /* Send AJAX request */
                $.ajax({
                    url: ajaxurl,  /* Use WordPress admin-ajax.php */
                    type: "POST",
                    data: formData,
                    contentType: false,
                    processData: false,
                    success: function(response) {
                        if (response.success) {
                            console.log("File upload successful.");

                            if (response.data.presigned_url) {
                                $("#efs_file_url").val(response.data.presigned_url);
                                console.log("Presigned URL received:", response.data.presigned_url);
                            } else {
                                $("#efs_file_url").val(response.data.file_url);
                                console.log("File URL received:", response.data.file_url);
                            }
                        } else {
                            console.error("File upload failed:", response.data.message);
                            alert(efsAdminAjax.efsUploadFailedMessage); /* Localized variable */
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error("AJAX request failed:", status, error);
                        alert(efsAdminAjax.efsErrorMessage); /* Localized variable */
                    }
                });
            } else {
                console.error("Selected file ID is undefined.");
            }
        });

        /* Open the media uploader */
        file_frame.open();
    });
});