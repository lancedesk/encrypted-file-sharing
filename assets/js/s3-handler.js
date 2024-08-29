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
        console.log("Admin Ajax URL is: ", efsAdminAjax.ajax_url);

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
            console.log('No AWS region provided for fetching buckets');  /* Log if no region is provided */
            alert('Please provide AWS region.');
            return;
        }
    
        console.log('Starting fetch buckets AJAX request');  /* Log before AJAX request for fetching buckets */
        $.ajax({
            url: efsAdminAjax.ajax_url, /* WordPress admin AJAX URL */
            type: 'POST',
            data: {
                action: 'efs_fetch_s3_buckets',
                region: region,
                _ajax_nonce: efs_s3_params.efs_s3_nonce  /* Include nonce for security */
            },
            success: function(response) {
                console.log('Fetch buckets response received:', response); /* Log the response */
                if (response.success) {
                    console.log('Buckets fetched successfully'); /* Log success message */
                    var bucketList = response.data;
                    var $checklist = $('#efs_bucket_checklist'); /* The div where checkboxes are displayed */
                    $checklist.empty(); /* Clear any existing content */
    
                    $.each(bucketList, function(index, bucket) {
                        console.log('Bucket found:', bucket);  /* Log each bucket */
                        var checkbox = '<label><input type="checkbox" name="efs_aws_buckets[]" value="' + bucket + '">' + bucket + '</label><br>';
                        $checklist.append(checkbox); /* Append each checkbox to the checklist div */
                    });
    
                    $('#efs_fetch_buckets_message').html('<span style="color: green;">Buckets fetched successfully.</span>');
                } else {
                    console.log('Failed to fetch buckets: ' + response.data.message); /* Log failure message */
                    $('#efs_fetch_buckets_message').html('<span style="color: red;">Failed to fetch buckets.</span>');
                }
            },
            error: function() {
                console.log('AJAX error while fetching buckets'); /* Log AJAX error */
                $('#efs_fetch_buckets_message').html('<span style="color: red;">Failed to fetch buckets. Please try again.</span>');
            }
        });
    }    
});