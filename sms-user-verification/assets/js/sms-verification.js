jQuery(document).ready(function($) {
    // Handle sending verification SMS
    $('#send_verification_code, #send_verification_btn').click(function() {
        var phoneNumber = $('#phone_number, #phone_number_field').val();
        
        if (!phoneNumber) {
            alert('Please enter your phone number');
            return;
        }
        
        // Disable button during request
        $(this).prop('disabled', true);
        $(this).text('Sending...');
        
        $.ajax({
            url: ajax_object.ajax_url,
            type: 'POST',
            data: {
                action: 'send_verification_sms',
                phone_number: phoneNumber,
                nonce: ajax_object.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#verification_status, #verification_message').html('<span style="color: green;">' + response.data.message + '</span>');
                } else {
                    $('#verification_status, #verification_message').html('<span style="color: red;">Error: ' + response.data.message + '</span>');
                }
            },
            error: function() {
                $('#verification_status, #verification_message').html('<span style="color: red;">An error occurred while sending the SMS</span>');
            },
            complete: function() {
                // Re-enable button
                $('#send_verification_code, #send_verification_btn').prop('disabled', false);
                $('#send_verification_code, #send_verification_btn').text('Send Verification Code');
            }
        });
    });
    
    // Handle verifying the SMS code
    $('#verify_code_btn').click(function() {
        var phoneNumber = $('#phone_number_field').val();
        var verificationCode = $('#verification_code_field').val();
        
        if (!phoneNumber) {
            alert('Please enter your phone number');
            return;
        }
        
        if (!verificationCode) {
            alert('Please enter the verification code');
            return;
        }
        
        // Disable button during request
        $(this).prop('disabled', true);
        $(this).text('Verifying...');
        
        $.ajax({
            url: ajax_object.ajax_url,
            type: 'POST',
            data: {
                action: 'verify_sms_code',
                phone_number: phoneNumber,
                verification_code: verificationCode,
                nonce: ajax_object.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#result_message').html('<span style="color: green;">' + response.data.message + '</span>');
                    
                    // Automatically create the user after successful verification
                    createUser(phoneNumber, verificationCode);
                } else {
                    $('#result_message').html('<span style="color: red;">Error: ' + response.data.message + '</span>');
                }
            },
            error: function() {
                $('#result_message').html('<span style="color: red;">An error occurred during verification</span>');
            },
            complete: function() {
                // Re-enable button
                $('#verify_code_btn').prop('disabled', false);
                $('#verify_code_btn').text('Verify Code');
            }
        });
    });
    
    // Handle form submission for registration
    $('form[name="registerform"], #sms-verification-form').submit(function(e) {
        var phoneNumber = $('#phone_number, #phone_number_field').val();
        var verificationCode = $('#verification_code, #verification_code_field').val();
        
        if (!phoneNumber) {
            alert('Please enter your phone number');
            e.preventDefault();
            return false;
        }
        
        if (!verificationCode) {
            alert('Please enter the verification code');
            e.preventDefault();
            return false;
        }
        
        // We'll handle the user creation via AJAX after successful verification
        e.preventDefault();
        
        createUser(phoneNumber, verificationCode);
    });
});

function createUser(phoneNumber, verificationCode) {
    jQuery.ajax({
        url: ajax_object.ajax_url,
        type: 'POST',
        data: {
            action: 'create_user_from_phone',
            phone_number: phoneNumber,
            verification_code: verificationCode,
            nonce: ajax_object.nonce
        },
        success: function(response) {
            if (response.success) {
                jQuery('#result_message').html('<span style="color: green;">User created successfully! You can now log in.</span>');
                
                // Redirect to login page after a delay
                setTimeout(function() {
                    window.location.href = '/wp-login.php';
                }, 2000);
            } else {
                jQuery('#result_message').html('<span style="color: red;">Error: ' + response.data.message + '</span>');
            }
        },
        error: function() {
            jQuery('#result_message').html('<span style="color: red;">An error occurred while creating the user</span>');
        }
    });
}

// Add AJAX handler for creating user after verification
jQuery(document).ready(function($) {
    // Handle the AJAX request for creating a user after verification
    // This is triggered after successful verification
});