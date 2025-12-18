# SMS User Verification Plugin - Complete Documentation

## Overview
This WordPress plugin enables phone number-based user registration using SMS verification through the SMS.ir service. Users can register by entering their phone number, receiving a verification code via SMS, and completing the registration process.

## Plugin Structure

### 1. Main Plugin File: `sms-user-verification.php`

#### Class: `SMSUserVerification`

**Constructor (`__construct`)**
- Sets up all WordPress hooks and actions
- Registers AJAX handlers for both logged-in and non-logged-in users
- Hooks into registration form and validation processes
- Adds admin menu and settings registration

**Initialization Methods:**

- `init()`: Placeholder for initialization tasks
- `enqueue_scripts()`: Loads JavaScript and CSS assets, localizes AJAX parameters

**Admin Management:**

- `add_admin_menu()`: Creates settings page under WordPress admin
- `register_settings()`: Registers plugin options in WordPress settings API
- `settings_page()`: Outputs HTML for admin configuration page

**Registration Integration:**

- `add_phone_field_to_registration()`: Adds phone number fields to standard registration form
- `validate_phone_field()`: Validates phone number and verification code during registration

**AJAX Handlers:**

- `send_verification_sms()`: Processes phone number, validates format, generates code, stores temporarily, and sends SMS via SMS.ir API
- `verify_sms_code()`: Verifies entered code against stored code
- `create_user_from_phone()`: Creates WordPress user account after successful verification

**Utility Methods:**

- `generate_username_from_phone()`: Creates unique username from phone number
- `send_sms_via_smsir()`: Makes HTTP request to SMS.ir API to send verification code

### 2. JavaScript File: `assets/js/sms-verification.js`

**Document Ready Handler:**
- Ensures DOM is loaded before attaching event handlers
- Handles SMS sending, code verification, and user creation

**Event Handlers:**
- `#send_verification_code, #send_verification_btn`: Triggers SMS sending
- `#verify_code_btn`: Verifies the entered code
- Form submission handlers: Prevents default submission, processes registration via AJAX

**Core Functions:**
- `createUser()`: Makes AJAX call to create WordPress user after verification

### 3. CSS File: `assets/css/style.css`
- Basic styling for the verification forms

## Detailed Code Breakdown

### PHP Backend Processing

#### Security Measures Implemented:

1. **Nonce Verification**: Every AJAX request includes a security nonce to prevent CSRF attacks
2. **Input Sanitization**: All user inputs are sanitized using WordPress functions
3. **Direct Access Prevention**: Plugin file checks for ABSPATH constant
4. **Transients for Temporary Storage**: Verification codes stored temporarily with expiration

#### Phone Number Validation:

```php
if (!preg_match('/^(\\+98|0)?9\\d{9}$/', $phone_number) && !preg_match('/^(\\+98|0)?09\\d{9}$/', $phone_number))
```
This regex validates Iranian mobile number formats:
- `09xxxxxxxxx` - Standard Iranian format
- `+989xxxxxxxxx` - International format
- `00989xxxxxxxxx` - Alternative international format

#### SMS.ir API Integration:

The `send_sms_via_smsir()` method implements the correct SMS.ir v1 API format:

```php
private function send_sms_via_smsir($mobile, $verification_code) {
    $api_key = get_option('sms_api_key');
    $line_number = get_option('sms_line_number');
    $template_id = get_option('sms_template_id');

    if (empty($api_key) || empty($line_number) || empty($template_id)) {
        return array('success' => false, 'message' => 'SMS.ir API credentials are not fully configured');
    }

    $url = 'https://api.sms.ir/v1/send/verify';
    
    $params = array(
        array(
            'Parameter' => 'Code',  // This should match your template parameter name
            'ParameterValue' => $verification_code
        )
    );

    $data = array(
        'Mobile' => $mobile,
        'TemplateId' => intval($template_id),
        'Parameters' => $params
    );

    $args = array(
        'method' => 'POST',
        'headers' => array(
            'Content-Type' => 'application/json',
            'X-API-KEY' => $api_key
        ),
        'body' => json_encode($data),
        'timeout' => 30
    );

    $response = wp_remote_post($url, $args);
    
    // Process response...
}
```

Key points about the API integration:
- Uses the correct v1 API endpoint: `https://api.sms.ir/v1/send/verify`
- Sends JSON data with proper headers
- Includes API key in the X-API-KEY header
- Formats parameters as an array of objects with 'Parameter' and 'ParameterValue' keys
- Handles both success and error responses properly

#### User Creation Process:

After successful verification, the system:

1. Generates a unique username from the phone number
2. Creates a random password (for WordPress compliance)
3. Creates a dummy email using the phone number
4. Sets the user role based on admin configuration
5. Stores the actual phone number in user meta
6. Removes the temporary verification code

### JavaScript Frontend Processing

#### AJAX Communication:

All communication with the backend happens through WordPress AJAX:

```javascript
$.ajax({
    url: ajax_object.ajax_url,  // WordPress AJAX endpoint
    type: 'POST',
    data: {
        action: 'send_verification_sms',  // Maps to PHP function
        phone_number: phoneNumber,
        nonce: ajax_object.nonce          // Security verification
    },
    success: function(response) { /* Handle response */ },
    error: function() { /* Handle errors */ },
    complete: function() { /* Cleanup */ }
});
```

#### Form Validation:

Client-side validation includes:
- Checking if phone number is provided
- Checking if verification code is provided
- Disabling buttons during requests to prevent multiple submissions

## Admin Configuration

The plugin provides an admin interface at `Settings > SMS User Verification` where administrators can configure:

1. **API Key**: Your SMS.ir API key
2. **Line Number**: Your SMS.ir line number (e.g., 3000xxxxxx)
3. **SMS Template ID**: The template ID for your verification SMS
4. **Default User Role**: The role assigned to newly created users

## Shortcode Usage

The plugin provides a `[sms_verification]` shortcode that can be used in posts or pages to display the verification form anywhere on the site.

## Troubleshooting Common Issues

### 1. SMS Not Being Sent
- Check API credentials in admin settings
- Verify template ID matches your SMS.ir template
- Ensure the parameter name in your template matches 'Code' in the code
- Check if your SMS.ir account has sufficient credit

### 2. Verification Code Not Working
- Verify the code hasn't expired (10-minute timeout)
- Check if the entered code matches exactly what was sent
- Confirm phone number format is consistent

### 3. User Not Created After Verification
- Check WordPress error logs
- Verify all required fields are being passed correctly
- Ensure no conflicts with other registration plugins

### 4. Security Considerations
- Regularly rotate API keys
- Monitor verification attempts for abuse
- Consider implementing rate limiting for sensitive actions

## SMS.ir API Requirements

To use this plugin, you need:
1. An SMS.ir account
2. API key from your SMS.ir dashboard
3. A dedicated line number
4. A verification template created in your SMS.ir account

Your verification template in SMS.ir should include a parameter named "Code" (or update the code to match your template parameter name) to properly insert the verification code into the message.

Example template in SMS.ir:
"Your verification code is: [Code]"