jQuery(document).ready(function($) {
    console.log("Default Admin Ajax URL is: ", ajaxurl);

    var mediaUploader;
    $("#upload_file_button").click(function(e) {
        e.preventDefault();
        console.log("Upload button clicked.");
        console.log("Storage option: ", efsAdminAjax.efsStorageOption); /* Access localized variable */

        /* If the media frame already exists, reopen it. */
        var file_frame = wp.media.frames.file_frame = wp.media({
            title: efsAdminAjax.efsSelectFileTitle, /* Localized variable */
            button: {
                text: efsAdminAjax.efsSelectFileButtonText /* Localized variable */
            },
            multiple: false
        });

        /* When a file is selected, grab the URL and set it as the text field's value */
        file_frame.on("select", function() {
            /* Get the selected file */
            var attachment = file_frame.state().get('selection').first().toJSON();

            console.log("File selected URL:", attachment.url);
            console.log("File selected:", attachment);

            // Check if the file object exists before accessing its properties
            if (attachment && attachment.url) 
            {

                $('#efs_file_url').val(attachment.url); /* Set the URL in the text input field */
            } 
            else 
            {
                console.error("Selected file object is undefined.");
            }
        
            var fileId = attachment.id;

            /* Use the attachment's ID to retrieve the actual file from the media library. */
            wp.media.attachment(fileId).fetch().then(function(response) {
                console.log("Fetching actual file from media library:", response);

                /* Prepare AJAX request based on storage option */
                var formData = new FormData();

                console.log("File Response:", response.attributes.file);
                
                /*
                * Since we can't get the actual file object from the media library directly, we use a workaround
                * The user can either upload or select a file from their computer (and bypass media URL retrieval)
                */
                
                /* Add the file object to the form data if available */
                formData.append("file", response.attributes.file);  /* Attach the actual file here */
                formData.append("file_id", fileId);
                formData.append("nonce", efsAdminAjax.nonce); /* Nonce for security from localized variable */

                /* Append the expiration date */
                var expirationDate = $("#expiration_date_field").val();
                formData.append("expiration_date", expirationDate);

                /* Set the upload action based on storage option */
                var uploadAction = "efs_upload_to_local";
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

                formData.append("upload_action", uploadAction);

                console.log("Preparing AJAX request with action:", uploadAction);
                console.log("Form data:", formData);

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
                            }
                            else {
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
                        console.log("XHR response:", xhr);
                        console.log("XHR response text:", xhr.responseText);
                        console.log("XHR response JSON:", xhr.responseJSON);
                        console.log("Status:", status);
                        console.log("Error:", error);
                        alert(efsAdminAjax.efsErrorMessage); /* Localized variable */
                    }
                });
            });
        });

        /* Open the media uploader */
        file_frame.open();
    });
});
