jQuery(document).ready(function($) {
    // Document Ready Handler
    // This function runs when the DOM is fully loaded
    // It ensures all elements are available before attaching event handlers
    
    // EVENT HANDLER: Send Verification SMS
    // This handles both buttons that might trigger SMS sending
    // Covers both the registration form button and shortcode form button
    $('#send_verification_code, #send_verification_btn').click(function() {
        // Get the phone number from either input field
        // Uses selector that works with both registration form and shortcode form
        var phoneNumber = $('#phone_number, #phone_number_field').val();
        
        // Validate that phone number is provided
        if (!phoneNumber) {
            alert('Please enter your phone number');
            return; // Exit the function if no phone number
        }
        
        // Disable the button and show loading state
        // Prevents multiple clicks during request processing
        $(this).prop('disabled', true);
        $(this).text('Sending...');
        
        // AJAX Request to send verification SMS
        $.ajax({
            // WordPress AJAX endpoint
            url: ajax_object.ajax_url,
            
            // HTTP method - must be POST for security
            type: 'POST',
            
            // Data to send to the server
            data: {
                // Action hook name - corresponds to WordPress hook
                action: 'send_verification_sms',
                
                // Phone number to send SMS to
                phone_number: phoneNumber,
                
                // Security nonce to prevent CSRF attacks
                nonce: ajax_object.nonce
            },
            
            // Success callback - runs when request succeeds
            success: function(response) {
                if (response.success) {
                    // Show success message
                    $('#verification_status, #verification_message').html('<span style="color: green;">' + response.data.message + '</span>');
                } else {
                    // Show error message
                    $('#verification_status, #verification_message').html('<span style="color: red;">Error: ' + response.data.message + '</span>');
                }
            },
            
            // Error callback - runs when request fails
            error: function() {
                // Show generic error message
                $('#verification_status, #verification_message').html('<span style="color: red;">An error occurred while sending the SMS</span>');
            },
            
            // Complete callback - runs regardless of success/failure
            complete: function() {
                // Re-enable the button and restore original text
                $('#send_verification_code, #send_verification_btn').prop('disabled', false);
                $('#send_verification_code, #send_verification_btn').text('Send Verification Code');
            }
        });
    });
    
    // EVENT HANDLER: Verify SMS Code
    // Handles verification of the received SMS code
    $('#verify_code_btn').click(function() {
        // Get phone number and verification code from respective fields
        var phoneNumber = $('#phone_number_field').val();
        var verificationCode = $('#verification_code_field').val();
        
        // Validate phone number
        if (!phoneNumber) {
            alert('Please enter your phone number');
            return; // Exit if no phone number
        }
        
        // Validate verification code
        if (!verificationCode) {
            alert('Please enter the verification code');
            return; // Exit if no verification code
        }
        
        // Disable button and show loading state
        $(this).prop('disabled', true);
        $(this).text('Verifying...');
        
        // AJAX Request to verify the code
        $.ajax({
            // WordPress AJAX endpoint
            url: ajax_object.ajax_url,
            
            // HTTP method - must be POST for security
            type: 'POST',
            
            // Data to send to the server
            data: {
                // Action hook name - corresponds to WordPress hook
                action: 'verify_sms_code',
                
                // Phone number for verification
                phone_number: phoneNumber,
                
                // Verification code entered by user
                verification_code: verificationCode,
                
                // Security nonce to prevent CSRF attacks
                nonce: ajax_object.nonce
            },
            
            // Success callback
            success: function(response) {
                if (response.success) {
                    // Show success message
                    $('#result_message').html('<span style="color: green;">' + response.data.message + '</span>');
                    
                    // Automatically create user after successful verification
                    createUser(phoneNumber, verificationCode);
                } else {
                    // Show error message
                    $('#result_message').html('<span style="color: red;">Error: ' + response.data.message + '</span>');
                }
            },
            
            // Error callback
            error: function() {
                // Show generic error message
                $('#result_message').html('<span style="color: red;">An error occurred during verification</span>');
            },
            
            // Complete callback
            complete: function() {
                // Re-enable button and restore original text
                $('#verify_code_btn').prop('disabled', false);
                $('#verify_code_btn').text('Verify Code');
            }
        });
    });
    
    // EVENT HANDLER: Form Submission
    // Handles submission of registration form or shortcode form
    $('form[name="registerform"], #sms-verification-form').submit(function(e) {
        // Get phone number and verification code values
        var phoneNumber = $('#phone_number, #phone_number_field').val();
        var verificationCode = $('#verification_code, #verification_code_field').val();
        
        // Validate phone number
        if (!phoneNumber) {
            alert('Please enter your phone number');
            e.preventDefault(); // Prevent form submission
            return false;
        }
        
        // Validate verification code
        if (!verificationCode) {
            alert('Please enter the verification code');
            e.preventDefault(); // Prevent form submission
            return false;
        }
        
        // Prevent default form submission
        // We'll handle user creation via AJAX instead
        e.preventDefault();
        
        // Create user via AJAX call
        createUser(phoneNumber, verificationCode);
    });
});

// FUNCTION: Create User
// Makes an AJAX call to create a WordPress user after successful verification
function createUser(phoneNumber, verificationCode) {
    jQuery.ajax({
        // WordPress AJAX endpoint
        url: ajax_object.ajax_url,
        
        // HTTP method - must be POST for security
        type: 'POST',
        
        // Data to send to the server
        data: {
            // Action hook name - corresponds to WordPress hook
            action: 'create_user_from_phone',
            
            // Phone number for user creation
            phone_number: phoneNumber,
            
            // Verification code for security check
            verification_code: verificationCode,
            
            // Security nonce to prevent CSRF attacks
            nonce: ajax_object.nonce
        },
        
        // Success callback
        success: function(response) {
            if (response.success) {
                // Show success message
                jQuery('#result_message').html('<span style="color: green;">User created successfully! You can now log in.</span>');
                
                // Redirect to login page after 2 seconds
                setTimeout(function() {
                    window.location.href = '/wp-login.php';
                }, 2000);
            } else {
                // Show error message
                jQuery('#result_message').html('<span style="color: red;">Error: ' + response.data.message + '</span>');
            }
        },
        
        // Error callback
        error: function() {
            // Show generic error message
            jQuery('#result_message').html('<span style="color: red;">An error occurred while creating the user</span>');
        }
    });
}

// Additional document ready handler
// This is redundant and could be combined with the first one
jQuery(document).ready(function($) {
    // Handle the AJAX request for creating a user after verification
    // This is triggered after successful verification
});