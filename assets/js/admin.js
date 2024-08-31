jQuery(document).ready(function($) {
    console.log("Default Admin Ajax URL is: ", ajaxurl);

    var mediaUploader;
    $("#upload_file_button").click(function(e) {
        e.preventDefault();
        console.log("Upload button clicked.");
        console.log("Storage option: ", efsAdminAjax.efsStorageOption); /* Access localized variable */

        if (mediaUploader) {
            mediaUploader.open();
            return;
        }

        mediaUploader = wp.media.frames.file_frame = wp.media({
            title: efsSelectFileTitle,
            button: {
                text: efsSelectFileButtonText
            },
            multiple: false
        });

        mediaUploader.on("select", function() {
            var attachment = mediaUploader.state().get("selection").first().toJSON();
            console.log("File selected URL:", attachment.url);
            console.log("File selected:", attachment);

            var fileUrl = attachment.url;
            var fileId = attachment.id;

            console.log("File selected ID:", fileId);

            /* Prepare AJAX request based on storage option */
            var formData = new FormData();

            /* Append file details and relevant info */
            formData.append("file_url", fileUrl); /* Append file URL */
            formData.append("file_id", fileId); /* Append file ID */          
            formData.append("nonce", efsNonce); /* Nonce for security */

            /* Append the expiration date */
            var expirationDate = $("#expiration_date_field").val();
            formData.append("expiration_date", expirationDate);

            var uploadAction = "efs_upload_to_local";

            switch (efsStorageOption) {
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
                        alert(efsUploadFailedMessage);
                    }
                },
                error: function(xhr, status, error) {
                    console.error("AJAX request failed:", status, error);
                    console.log("XHR response:", xhr);
                    console.log("XHR response text:", xhr.responseText);
                    console.log("XHR response JSON:", xhr.responseJSON);
                    console.log("Status:", status);
                    console.log("Error:", error);
                    alert(efsErrorMessage);
                }
            });
        });
        mediaUploader.open();
    });
});