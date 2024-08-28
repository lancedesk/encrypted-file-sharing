jQuery(document).ready(function ($) {
    console.log("File loaded!");
    /* Handle bucket creation */
    $('#efs_create_bucket').on('click', function() {
        console.log('Button clicked');  /* Check if the button click is detected */
        var newBucket = $('#efs_new_bucket').val();
        var region = $('#efs_aws_region').val();

        if (newBucket === '') {
            console.log('No bucket name provided');
            $('#efs_create_bucket_message').html('<span style="color: red;">Please enter a bucket name.</span>');
            return;
        }

        /* Ensure AWS credentials are provided */
        if (!region) {
            alert('Please provide AWS region.');
            return;
        }

        console.log('Starting AJAX request');  /* Ensure this logs before the AJAX request */
        $.ajax({
            url: efsAdminAjax.ajax_url, /* WordPress admin AJAX URL */
            type: 'POST',
            data: {
                action: 'efs_create_s3_bucket',
                bucket_name: newBucket,
                region: region,
                _ajax_nonce: efs_s3_params.efs_s3_nonce  /* Include nonce for security */
            },
            success: function(response) {
                console.log('Response received:', response);  /* Log the response */
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

    /* Handle fetching of buckets */
    $('#efs_fetch_buckets').on('click', function() {
        fetchBuckets();
    });


    function fetchBuckets() {
        var region = $('#efs_aws_region').val();

        /* Ensure AWS credentials are provided */
        if (!region) {
            alert('Please provide AWS region.');
            return;
        }

        $.ajax({
            url: efsAdminAjax.ajax_url, /* WordPress admin AJAX URL */
            type: 'POST',
            data: {
                action: 'efs_fetch_s3_buckets',
                region: region,
                _ajax_nonce: efs_s3_params.efs_s3_nonce  /* Include nonce for security */
            },
            success: function(response) {
                if (response.success) {
                    var bucketList = response.data.buckets;
                    var $select = $('#efs_aws_bucket');
                    $select.empty();
                    $.each(bucketList, function(index, bucket) {
                        $select.append('<option value="' + bucket + '">' + bucket + '</option>');
                    });
                    $('#efs_fetch_buckets_message').html('<span style="color: green;">Buckets fetched successfully.</span>');
                } else {
                    $('#efs_fetch_buckets_message').html('<span style="color: red;">Failed to fetch buckets.</span>');
                }
            },
            error: function() {
                $('#efs_fetch_buckets_message').html('<span style="color: red;">Failed to fetch buckets. Please try again.</span>');
            }
        });
    }
});