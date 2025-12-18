<?php
/**
 * Plugin Name: SMS User Verification
 * Description: Create WordPress users via phone number verification using SMS.ir service
 * Version: 1.0
 * Author: Your Name
 */

// Prevent direct access
// This security measure ensures that the file cannot be accessed directly from the web
// It checks if ABSPATH is defined, which is a constant defined by WordPress
// If it's not defined, it means someone is trying to access this file directly
if (!defined('ABSPATH')) {
    exit;
}

// Define the main class for our SMS User Verification plugin
// This class handles all the functionality of the plugin
class SMSUserVerification {

    /**
     * Constructor method
     * This runs when the class is instantiated and sets up all necessary hooks
     */
    public function __construct() {
        // Initialize the plugin when WordPress is ready
        add_action('init', array($this, 'init'));
        
        // Enqueue scripts and styles for the frontend
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // AJAX handlers for sending verification SMS (both logged-in and non-logged-in users)
        add_action('wp_ajax_send_verification_sms', array($this, 'send_verification_sms'));
        add_action('wp_ajax_nopriv_send_verification_sms', array($this, 'send_verification_sms'));
        
        // AJAX handlers for verifying the SMS code (both logged-in and non-logged-in users)
        add_action('wp_ajax_verify_sms_code', array($this, 'verify_sms_code'));
        add_action('wp_ajax_nopriv_verify_sms_code', array($this, 'verify_sms_code'));
        
        // AJAX handlers for creating user after verification (both logged-in and non-logged-in users)
        add_action('wp_ajax_create_user_from_phone', array($this, 'create_user_from_phone'));
        add_action('wp_ajax_nopriv_create_user_from_phone', array($this, 'create_user_from_phone'));
        
        // Admin menu and settings hooks
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        
        // Hook into the standard WordPress registration form to add phone field
        add_action('register_form', array($this, 'add_phone_field_to_registration'));
        add_action('register_post', array($this, 'validate_phone_field'), 10, 3);
    }

    /**
     * Initialize the plugin
     * Currently empty, but can be used for initialization tasks
     */
    public function init() {
        // Initialization code goes here
        // This runs during the 'init' hook in WordPress
    }
    
    /**
     * Enqueue necessary scripts and styles for the frontend
     * This loads our custom JavaScript and CSS files
     */
    public function enqueue_scripts() {
        // Load jQuery (WordPress includes this by default)
        wp_enqueue_script('jquery');
        
        // Register and enqueue our custom JavaScript file
        wp_enqueue_script(
            // Unique handle for the script
            'sms-user-verification-js',
            // URL to the script file
            plugins_url('/assets/js/sms-verification.js', __FILE__),
            // Dependencies (requires jQuery)
            array('jquery'),
            // Version number
            '1.0',
            // Load in footer?
            true
        );
        
        // Localize script to pass PHP variables to JavaScript
        // This allows us to use WordPress AJAX URL and security nonce in JavaScript
        wp_localize_script('sms-user-verification-js', 'ajax_object', array(
            // WordPress AJAX endpoint
            'ajax_url' => admin_url('admin-ajax.php'),
            // Security nonce to prevent CSRF attacks
            'nonce' => wp_create_nonce('sms_verification_nonce')
        ));
        
        // Register and enqueue our custom CSS file
        wp_enqueue_style(
            // Unique handle for the stylesheet
            'sms-user-verification-css',
            // URL to the CSS file
            plugins_url('/assets/css/style.css', __FILE__),
            // Dependencies (none in this case)
            array(),
            // Version number
            '1.0'
        );
    }

    /**
     * Add settings page to WordPress admin menu
     * This creates an options page under the Settings menu
     */
    public function add_admin_menu() {
        add_options_page(
            // Page title
            'SMS User Verification Settings',
            // Menu title
            'SMS User Verification',
            // Required capability to access the page
            'manage_options',
            // Menu slug (unique identifier)
            'sms-user-verification',
            // Callback function to display the page content
            array($this, 'settings_page')
        );
    }

    /**
     * Register settings fields for the admin page
     * This tells WordPress about our custom options
     */
    public function register_settings() {
        // Register the API key setting
        register_setting('sms_user_verification_settings', 'sms_api_key');
        
        // Register the SMS line number setting
        register_setting('sms_user_verification_settings', 'sms_line_number');
        
        // Register the default user role setting
        register_setting('sms_user_verification_settings', 'default_user_role');
        
        // Register the SMS template ID setting
        register_setting('sms_user_verification_settings', 'sms_template_id');
    }

    /**
     * Display the settings page HTML
     * This creates the form for configuring the plugin
     */
    public function settings_page() {
        ?>
        <!-- Start of admin settings page -->
        <div class="wrap">
            <h1>SMS User Verification Settings</h1>
            <form method="post" action="options.php">
                <?php 
                // Output security fields for the registered setting group
                settings_fields('sms_user_verification_settings'); 
                
                // Output settings sections (not used in this implementation)
                do_settings_sections('sms_user_verification_settings'); 
                ?>
                
                <!-- Table to organize settings fields -->
                <table class="form-table">
                    <!-- API Key setting -->
                    <tr valign="top">
                        <th scope="row">API Key</th>
                        <td>
                            <input 
                                type="text" 
                                name="sms_api_key" 
                                value="<?php echo esc_attr(get_option('sms_api_key')); ?>" 
                                size="50" 
                            />
                        </td>
                    </tr>
                    
                    <!-- Line Number setting -->
                    <tr valign="top">
                        <th scope="row">Line Number</th>
                        <td>
                            <input 
                                type="text" 
                                name="sms_line_number" 
                                value="<?php echo esc_attr(get_option('sms_line_number')); ?>" 
                                size="20" 
                                placeholder="e.g. 3000xxxxxx" 
                            />
                        </td>
                    </tr>
                    
                    <!-- SMS Template ID setting -->
                    <tr valign="top">
                        <th scope="row">SMS Template ID</th>
                        <td>
                            <input 
                                type="text" 
                                name="sms_template_id" 
                                value="<?php echo esc_attr(get_option('sms_template_id')); ?>" 
                                size="20" 
                                placeholder="Template ID for verification code" 
                            />
                        </td>
                    </tr>
                    
                    <!-- Default User Role setting -->
                    <tr valign="top">
                        <th scope="row">Default User Role</th>
                        <td>
                            <select name="default_user_role">
                                <?php
                                // Get all available user roles in WordPress
                                $roles = wp_roles()->get_names();
                                
                                // Loop through each role and create an option element
                                foreach ($roles as $role_key => $role_name) {
                                    // Check if this role is the currently selected one
                                    $selected = (get_option('default_user_role') === $role_key) ? 'selected' : '';
                                    
                                    // Output the option element with proper escaping
                                    echo '<option value="' . esc_attr($role_key) . '" ' . $selected . '>' . esc_html($role_name) . '</option>';
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                </table>
                
                <!-- Submit button to save settings -->
                <?php submit_button(); ?>
            </form>
        </div>
        <!-- End of admin settings page -->
        <?php
    }

    /**
     * Add phone number field to the standard WordPress registration form
     * This hooks into the registration form and adds our custom fields
     */
    public function add_phone_field_to_registration() {
        ?>
        <!-- Phone number input field -->
        <p>
            <label for="phone_number"><?php _e('Phone Number'); ?><br/>
                <input 
                    type="tel" 
                    name="phone_number" 
                    id="phone_number" 
                    class="input" 
                    value="<?php echo ( ! empty($_POST['phone_number']) ) ? esc_attr($_POST['phone_number']) : ''; ?>" 
                    size="25" 
                />
            </label>
        </p>
        
        <!-- Button to send verification code -->
        <p>
            <button type="button" id="send_verification_code" class="button button-secondary">Send Verification Code</button>
            <span id="verification_message"></span>
        </p>
        
        <!-- Verification code input field -->
        <p>
            <label for="verification_code"><?php _e('Verification Code'); ?><br/>
                <input 
                    type="text" 
                    name="verification_code" 
                    id="verification_code" 
                    class="input" 
                    value="" 
                    size="25" 
                    maxlength="6" 
                />
            </label>
        </p>
        <?php
    }

    /**
     * Validate phone number and verification code during registration
     * This runs during the registration process to validate our custom fields
     * 
     * @param string $user_login The username entered
     * @param string $user_email The email entered
     * @param WP_Error $errors Error object to add errors to
     */
    public function validate_phone_field($user_login, $user_email, $errors) {
        // Check if phone number was provided
        if (!isset($_POST['phone_number']) || empty($_POST['phone_number'])) {
            $errors->add('phone_number_error', __('<strong>Error</strong>: Phone number is required.'));
            return;
        }

        // Check if verification code was provided
        if (!isset($_POST['verification_code']) || empty($_POST['verification_code'])) {
            $errors->add('verification_code_error', __('<strong>Error</strong>: Verification code is required.'));
            return;
        }

        // Sanitize the phone number input to prevent XSS attacks
        $phone_number = sanitize_text_field($_POST['phone_number']);
        
        // Sanitize the verification code input
        $verification_code = sanitize_text_field($_POST['verification_code']);

        // Verify the code by checking against the stored temporary code
        $stored_code = get_transient('sms_verification_' . md5($phone_number));
        
        // If no stored code exists or the entered code doesn't match
        if (!$stored_code || $stored_code !== $verification_code) {
            $errors->add('verification_code_invalid', __('<strong>Error</strong>: Invalid verification code.'));
            return;
        }
    }

    /**
     * AJAX handler for sending verification SMS
     * This method is called via AJAX when user clicks "Send Verification Code"
     */
    public function send_verification_sms() {
        // Verify the security nonce to prevent CSRF attacks
        if (!wp_verify_nonce($_POST['nonce'], 'sms_verification_nonce')) {
            wp_die('Security check failed');
        }

        // Sanitize the phone number input
        $phone_number = sanitize_text_field($_POST['phone_number']);

        // Validate phone number format (Iranian mobile numbers)
        // This regex matches formats like: 09xxxxxxxxx, +989xxxxxxxxx, etc.
        if (!preg_match('/^(\\+98|0)?9\\d{9}$/', $phone_number) && !preg_match('/^(\\+98|0)?09\\d{9}$/', $phone_number)) {
            wp_send_json_error(array('message' => 'Invalid phone number format'));
            return;
        }

        // Generate a random 6-digit verification code
        // Using str_pad to ensure it's always 6 digits (with leading zeros if needed)
        $verification_code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);

        // Store the code temporarily in WordPress transients (expires in 10 minutes)
        // Using MD5 hash of phone number to make the transient key unique
        set_transient('sms_verification_' . md5($phone_number), $verification_code, 10 * MINUTE_IN_SECONDS);

        // Send SMS using SMS.ir API
        $result = $this->send_sms_via_smsir($phone_number, $verification_code);

        // Send JSON response back to the AJAX call
        if ($result['success']) {
            wp_send_json_success(array('message' => 'Verification code sent successfully'));
        } else {
            wp_send_json_error(array('message' => $result['message']));
        }
    }

    /**
     * AJAX handler for verifying the SMS code
     * This method is called via AJAX when user enters the verification code
     */
    public function verify_sms_code() {
        // Verify the security nonce to prevent CSRF attacks
        if (!wp_verify_nonce($_POST['nonce'], 'sms_verification_nonce')) {
            wp_die('Security check failed');
        }

        // Sanitize inputs
        $phone_number = sanitize_text_field($_POST['phone_number']);
        $verification_code = sanitize_text_field($_POST['verification_code']);

        // Retrieve the stored verification code from transients
        $stored_code = get_transient('sms_verification_' . md5($phone_number));

        // Check if the entered code matches the stored code
        if ($stored_code && $stored_code === $verification_code) {
            // Code is valid - remove the temporary code as it's no longer needed
            delete_transient('sms_verification_' . md5($phone_number));
            
            // Send success response
            wp_send_json_success(array('message' => 'Verification successful'));
        } else {
            // Code is invalid or doesn't exist
            wp_send_json_error(array('message' => 'Invalid verification code'));
        }
    }

    /**
     * AJAX handler for creating user after successful verification
     * This method creates a WordPress user account after phone verification
     */
    public function create_user_from_phone() {
        // Verify the security nonce to prevent CSRF attacks
        if (!wp_verify_nonce($_POST['nonce'], 'sms_verification_nonce')) {
            wp_die('Security check failed');
        }

        // Sanitize inputs
        $phone_number = sanitize_text_field($_POST['phone_number']);
        $verification_code = sanitize_text_field($_POST['verification_code']);

        // Verify the code again to ensure security
        // This double-check ensures the verification was recent and valid
        $stored_code = get_transient('sms_verification_' . md5($phone_number));
        
        if (!$stored_code || $stored_code !== $verification_code) {
            wp_send_json_error(array('message' => 'Invalid or expired verification code'));
            return;
        }

        // At this point, we know the phone number is verified
        // Now create the user account
        
        // Generate a username from the phone number
        // This creates a unique username based on the phone number
        $username = $this->generate_username_from_phone($phone_number);
        
        // Generate a random password
        // Since login is via phone number, the password is less important
        $password = wp_generate_password();
        
        // Create a dummy email address using the phone number
        // This is required by WordPress but won't be used for login
        $email = $phone_number . '@smslogin.local';

        // Get the default role from settings
        // This allows admin to configure what role new users get
        $default_role = get_option('default_user_role', 'subscriber');
        
        // Create the user in WordPress database
        $user_id = wp_create_user($username, $password, $email);
        
        // Check if user creation was successful
        if (is_wp_error($user_id)) {
            wp_send_json_error(array('message' => $user_id->get_error_message()));
            return;
        }
        
        // Update user meta with the actual phone number
        // This stores the real phone number associated with the user
        update_user_meta($user_id, 'phone_number', $phone_number);
        
        // Set the user role as configured in settings
        $user = new WP_User($user_id);
        $user->set_role($default_role);
        
        // Remove the verification code as it's no longer needed
        delete_transient('sms_verification_' . md5($phone_number));
        
        // Return success response with user ID
        wp_send_json_success(array(
            'message' => 'User created successfully!',
            'user_id' => $user_id
        ));
    }
    
    /**
     * Generate a valid username from a phone number
     * This ensures the username follows WordPress requirements
     * 
     * @param string $phone_number The phone number to convert
     * @return string The generated username
     */
    private function generate_username_from_phone($phone_number) {
        // Clean the phone number to keep only digits
        $clean_phone = preg_replace('/[^0-9]/', '', $phone_number);
        
        // Ensure username starts with a letter by prefixing with 'user_'
        $username = 'user_' . $clean_phone;
        
        // Check if username already exists and increment if needed
        // This prevents duplicate usernames if the same phone number tries to register twice
        $original_username = $username;
        $counter = 1;
        
        // Keep incrementing until we find a unique username
        while (username_exists($username)) {
            $username = $original_username . '_' . $counter;
            $counter++;
        }
        
        return $username;
    }

    /**
     * Send SMS via SMS.ir API
     * This method makes the HTTP request to SMS.ir to send the verification code
     * 
     * @param string $mobile The recipient's mobile number
     * @param string $verification_code The verification code to send
     * @return array Result array with success status and message
     */
    private function send_sms_via_smsir($mobile, $verification_code) {
        // Get API credentials from WordPress options
        $api_key = get_option('sms_api_key');
        $line_number = get_option('sms_line_number');
        $template_id = get_option('sms_template_id');

        // Check if all required API credentials are set
        if (empty($api_key) || empty($line_number) || empty($template_id)) {
            return array('success' => false, 'message' => 'SMS.ir API credentials are not fully configured');
        }

        // Prepare the API endpoint URL for SMS.ir v1 API
        $url = 'https://api.sms.ir/v1/send/verify';
        
        // Format parameters according to SMS.ir API specification
        // The parameter name should match what's defined in your SMS template
        $params = array(
            array(
                'Parameter' => 'Code',  // This should match your template parameter name
                'ParameterValue' => $verification_code
            )
        );

        // Prepare the request body data
        $data = array(
            'Mobile' => $mobile,           // Recipient's phone number
            'TemplateId' => intval($template_id),  // Template ID from SMS.ir
            'Parameters' => $params        // The verification code parameter
        );

        // Configure the HTTP request arguments
        $args = array(
            'method' => 'POST',  // Use POST method for sending data
            'headers' => array(
                'Content-Type' => 'application/json',  // Specify JSON content type
                'X-API-KEY' => $api_key               // Include API key in headers
            ),
            'body' => json_encode($data),  // Encode data as JSON
            'timeout' => 30                  // Set timeout to 30 seconds
        );

        // Make the HTTP request to SMS.ir API
        $response = wp_remote_post($url, $args);

        // Check if the request resulted in an error
        if (is_wp_error($response)) {
            return array('success' => false, 'message' => $response->get_error_message());
        }

        // Get the response body
        $response_body = wp_remote_retrieve_body($response);
        
        // Decode the JSON response
        $response_data = json_decode($response_body, true);

        // Check if the SMS was sent successfully
        // SMS.ir API returns 'ok' => true when successful
        if ($response_data && isset($response_data['ok']) && $response_data['ok'] === true) {
            return array('success' => true, 'message' => 'SMS sent successfully');
        } else {
            // Extract error message from response or use default
            $error_message = isset($response_data['message']) ? $response_data['message'] : 'Unknown error occurred';
            return array('success' => false, 'message' => $error_message);
        }
    }
}

// Initialize the plugin by creating an instance of the class
// This starts the plugin functionality
new SMSUserVerification();

/**
 * Create a shortcode for the verification form
 * This allows the form to be embedded in posts/pages using [sms_verification]
 */
function sms_verification_shortcode() {
    // Start output buffering to capture the HTML output
    ob_start();
    ?>
    <!-- SMS Verification Form HTML -->
    <div id="sms-verification-form">
        <h3>Register with Phone Number</h3>
        
        <!-- Phone number input field -->
        <p>
            <label for="phone_number_field">Phone Number:<br/>
                <input 
                    type="tel" 
                    id="phone_number_field" 
                    name="phone_number" 
                    value="" 
                    size="25" 
                    placeholder="Enter your phone number" 
                />
            </label>
        </p>
        
        <!-- Button to send verification code -->
        <p>
            <button type="button" id="send_verification_btn" class="button button-primary">Send Verification Code</button>
            <span id="verification_status"></span>
        </p>
        
        <!-- Verification code input field -->
        <p>
            <label for="verification_code_field">Verification Code:<br/>
                <input 
                    type="text" 
                    id="verification_code_field" 
                    name="verification_code" 
                    value="" 
                    size="25" 
                    maxlength="6" 
                    placeholder="Enter verification code" 
                />
            </label>
        </p>
        
        <!-- Button to verify the code -->
        <p>
            <button type="button" id="verify_code_btn" class="button button-secondary">Verify Code</button>
        </p>
        
        <!-- Results message container -->
        <p id="result_message"></p>
    </div>
    <?php
    // Return the captured HTML output
    return ob_get_clean();
}

// Register the shortcode with WordPress
add_shortcode('sms_verification', 'sms_verification_shortcode');