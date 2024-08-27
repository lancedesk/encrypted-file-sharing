jQuery(document).ready(function ($) {
    $('#efs_fetch_buckets').on('click', function () {
        var region = $('#efs_aws_region').val();
        var accessKey = $('#efs_aws_access_key').val();
        var secretKey = $('#efs_aws_secret_key').val();

        if (!region || !accessKey || !secretKey) {
            alert('Please provide AWS region, access key, and secret key.');
            return;
        }

        $.ajax({
            url: efsAdminAjax.ajax_url, /* WordPress admin AJAX URL */
            method: 'POST',
            data: {
                action: 'efs_fetch_s3_buckets',
                region: region,
                access_key: accessKey,
                secret_key: secretKey,
                _ajax_nonce: efs_s3_params.efs_s3_nonce  /* Include the localized nonce */
            },
            success: function (response) {
                if (response.success) {
                    var bucketDropdown = $('#efs_aws_bucket');
                    bucketDropdown.empty(); /* Clear existing options */

                    $.each(response.data, function (index, bucketName) {
                        bucketDropdown.append(new Option(bucketName, bucketName));
                    });
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function () {
                alert('AJAX request failed.');
            }
        });
    });
});