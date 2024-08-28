jQuery(document).ready(function ($) {
    /* Handle bucket creation */
    $('#efs_create_bucket').on('click', function() {
        var newBucket = $('#efs_new_bucket').val();
        var region = $('#efs_aws_region').val();
        var accessKey = $('#efs_aws_access_key').val();
        var secretKey = $('#efs_aws_secret_key').val();

        if (newBucket === '') {
            $('#efs_create_bucket_message').html('<span style="color: red;">Please enter a bucket name.</span>');
            return;
        }

        /* Ensure AWS credentials are provided */
        if (!region || !accessKey || !secretKey) {
            alert('Please provide AWS region, access key, and secret key.');
            return;
        }

        $.ajax({
            url: efsAdminAjax.ajax_url, /* WordPress admin AJAX URL */
            type: 'POST',
            data: {
                action: 'efs_create_s3_bucket',
                bucket_name: newBucket,
                region: region,
                access_key: accessKey,
                secret_key: secretKey,
                _ajax_nonce: efs_s3_params.efs_s3_nonce  /* Include nonce for security */
            },
            success: function(response) {
                if (response.success) {
                    $('#efs_create_bucket_message').html('<span style="color: green;">Bucket created successfully!</span>');
                    $('#efs_new_bucket').val(''); /* Clear input field */
                    fetchBuckets(); /* Fetch updated list of buckets */
                } else {
                    $('#efs_create_bucket_message').html('<span style="color: red;">' + response.data.message + '</span>');
                }
            },
            error: function() {
                $('#efs_create_bucket_message').html('<span style="color: red;">Failed to create bucket. Please try again.</span>');
            }
        });
    });


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