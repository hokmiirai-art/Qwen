<?php
/**
 * Plugin Name: SMS User Verification
 * Description: Create WordPress users via phone number verification using SMS.ir service
 * Version: 1.0
 * Author: Your Name
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main class for the SMS User Verification plugin
 * Handles all functionality including:
 * - Admin settings page
 * - Frontend verification form
 * - SMS communication with SMS.ir
 * - User creation after verification
 */
class SMSUserVerification {

    /**
     * Constructor method
     * Initializes all hooks and actions needed for the plugin
     */
    public function __construct() {
        // Initialize the plugin during WordPress initialization
        add_action('init', array($this, 'init'));
        
        // Enqueue frontend scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // AJAX handlers for sending and verifying SMS codes
        add_action('wp_ajax_send_verification_sms', array($this, 'send_verification_sms'));
        add_action('wp_ajax_nopriv_send_verification_sms', array($this, 'send_verification_sms')); // For non-logged-in users
        add_action('wp_ajax_verify_sms_code', array($this, 'verify_sms_code'));
        add_action('wp_ajax_nopriv_verify_sms_code', array($this, 'verify_sms_code')); // For non-logged-in users
        add_action('wp_ajax_create_user_from_phone', array($this, 'create_user_from_phone'));
        add_action('wp_ajax_nopriv_create_user_from_phone', array($this, 'create_user_from_phone')); // For non-logged-in users
        
        // Admin menu and settings
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        
        // Integration with WordPress registration form
        add_action('register_form', array($this, 'add_phone_field_to_registration'));
        add_action('register_post', array($this, 'validate_phone_field'), 10, 3);
    }

    /**
     * Initialize plugin components
     * Currently empty but can be used for future initialization tasks
     */
    public function init() {
        // Initialize the plugin
    }
    
    /**
     * Enqueue frontend scripts and styles
     * Loads JavaScript and CSS files needed for the verification form
     */
    public function enqueue_scripts() {
        // Make sure jQuery is loaded
        wp_enqueue_script('jquery');
        
        // Enqueue the JavaScript file for frontend functionality
        wp_enqueue_script(
            'sms-user-verification-js',
            plugins_url('/assets/js/sms-verification.js', __FILE__),
            array('jquery'), // Dependencies
            '1.0',          // Version
            true            // Load in footer
        );
        
        // Pass AJAX URL and nonce to JavaScript for security
        wp_localize_script('sms-user-verification-js', 'ajax_object', array(
            'ajax_url' => admin_url('admin-ajax.php'), // WordPress AJAX endpoint
            'nonce' => wp_create_nonce('sms_verification_nonce') // Security token
        ));
        
        // Enqueue the CSS file for styling
        wp_enqueue_style(
            'sms-user-verification-css',
            plugins_url('/assets/css/style.css', __FILE__),
            array(), // Dependencies
            '1.0'    // Version
        );
    }

    /**
     * Add admin menu item
     * Creates an options page under the Settings menu in WordPress admin
     */
    public function add_admin_menu() {
        // Add submenu page under Settings
        add_options_page(
            'SMS User Verification Settings', // Page title
            'SMS User Verification',         // Menu title
            'manage_options',                // Capability required
            'sms-user-verification',         // Menu slug
            array($this, 'settings_page')    // Callback function to render the page
        );
    }

    /**
     * Register settings fields
     * Registers the plugin's settings in WordPress
     */
    public function register_settings() {
        // Register our settings in WordPress database
        register_setting('sms_user_verification_settings', 'sms_api_key');       // SMS.ir API key
        register_setting('sms_user_verification_settings', 'sms_line_number');   // SMS.ir line number
        register_setting('sms_user_verification_settings', 'default_user_role'); // Default user role for new registrations
        register_setting('sms_user_verification_settings', 'sms_template_id');   // SMS.ir template ID
    }

    /**
     * Render the admin settings page
     * Outputs the complete HTML for the admin settings page
     */
    public function settings_page() {
        ?>
        <div class="wrap">
            <h1>SMS User Verification Settings</h1>
            <form method="post" action="options.php">
                <?php settings_fields('sms_user_verification_settings'); ?>
                <?php do_settings_sections('sms_user_verification_settings'); ?>
                <table class="form-table">
                    <!-- API Key field -->
                    <tr valign="top">
                        <th scope="row">API Key</th>
                        <td><input type="text" name="sms_api_key" value="<?php echo esc_attr(get_option('sms_api_key')); ?>" size="50" /></td>
                    </tr>
                    <!-- Line Number field -->
                    <tr valign="top">
                        <th scope="row">Line Number</th>
                        <td><input type="text" name="sms_line_number" value="<?php echo esc_attr(get_option('sms_line_number')); ?>" size="20" placeholder="e.g. 3000xxxxxx" /></td>
                    </tr>
                    <!-- SMS Template ID field -->
                    <tr valign="top">
                        <th scope="row">SMS Template ID</th>
                        <td><input type="text" name="sms_template_id" value="<?php echo esc_attr(get_option('sms_template_id')); ?>" size="20" placeholder="Template ID for verification code" /></td>
                    </tr>
                    <!-- Default User Role field -->
                    <tr valign="top">
                        <th scope="row">Default User Role</th>
                        <td>
                            <select name="default_user_role">
                                <?php
                                // Get all available user roles in WordPress
                                $roles = wp_roles()->get_names();
                                // Loop through all roles and create option elements
                                foreach ($roles as $role_key => $role_name) {
                                    // Set selected attribute if this is the saved role
                                    $selected = (get_option('default_user_role') === $role_key) ? 'selected' : '';
                                    echo '<option value="' . esc_attr($role_key) . '" ' . $selected . '>' . esc_html($role_name) . '</option>';
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Add phone number field to WordPress registration form
     * Outputs the HTML for the phone number and verification code fields
     */
    public function add_phone_field_to_registration() {
        ?>
        <p>
            <label for="phone_number"><?php _e('Phone Number'); ?><br/>
                <input type="tel" name="phone_number" id="phone_number" class="input" value="<?php echo ( ! empty($_POST['phone_number']) ) ? esc_attr($_POST['phone_number']) : ''; ?>" size="25" />
            </label>
        </p>
        <p>
            <button type="button" id="send_verification_code" class="button button-secondary">Send Verification Code</button>
            <span id="verification_message"></span>
        </p>
        <p>
            <label for="verification_code"><?php _e('Verification Code'); ?><br/>
                <input type="text" name="verification_code" id="verification_code" class="input" value="" size="25" maxlength="6" />
            </label>
        </p>
        <?php
    }

    /**
     * Validate phone number and verification code during registration
     * Checks if both phone number and verification code are provided and valid
     * 
     * @param string $user_login The sanitized username
     * @param string $user_email The sanitized email
     * @param WP_Error $errors Error object to add errors to
     */
    public function validate_phone_field($user_login, $user_email, $errors) {
        // Check if phone number is provided
        if (!isset($_POST['phone_number']) || empty($_POST['phone_number'])) {
            $errors->add('phone_number_error', __('<strong>Error</strong>: Phone number is required.'));
            return;
        }

        // Check if verification code is provided
        if (!isset($_POST['verification_code']) || empty($_POST['verification_code'])) {
            $errors->add('verification_code_error', __('<strong>Error</strong>: Verification code is required.'));
            return;
        }

        // Sanitize the inputs
        $phone_number = sanitize_text_field($_POST['phone_number']);
        $verification_code = sanitize_text_field($_POST['verification_code']);

        // Verify the code against the stored one
        $stored_code = get_transient('sms_verification_' . md5($phone_number));
        if (!$stored_code || $stored_code !== $verification_code) {
            $errors->add('verification_code_invalid', __('<strong>Error</strong>: Invalid verification code.'));
            return;
        }
    }

    /**
     * AJAX handler for sending SMS verification code
     * Validates phone number and sends verification code via SMS.ir API
     */
    public function send_verification_sms() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sms_verification_nonce')) {
            wp_die('Security check failed');
        }

        $phone_number = sanitize_text_field($_POST['phone_number']);

        // Validate phone number format (basic validation)
        if (!preg_match('/^(\+98|0)?9\d{9}$/', $phone_number) && !preg_match('/^(\+98|0)?09\d{9}$/', $phone_number)) {
            wp_send_json_error(array('message' => 'Invalid phone number format'));
            return;
        }

        // Generate a random 6-digit verification code
        $verification_code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);

        // Store the code temporarily (valid for 10 minutes)
        set_transient('sms_verification_' . md5($phone_number), $verification_code, 10 * MINUTE_IN_SECONDS);

        // Send SMS using SMS.ir API
        $result = $this->send_sms_via_smsir($phone_number, $verification_code);

        if ($result['success']) {
            wp_send_json_success(array('message' => 'Verification code sent successfully'));
        } else {
            wp_send_json_error(array('message' => $result['message']));
        }
    }

    /**
     * AJAX handler for verifying SMS code
     * Checks if entered code matches the one sent via SMS
     */
    public function verify_sms_code() {
        // Verify nonce for security to prevent CSRF attacks
        if (!wp_verify_nonce($_POST['nonce'], 'sms_verification_nonce')) {
            wp_die('Security check failed');
        }

        // Sanitize inputs
        $phone_number = sanitize_text_field($_POST['phone_number']);
        $verification_code = sanitize_text_field($_POST['verification_code']);

        // Retrieve the stored verification code from WordPress transients
        $stored_code = get_transient('sms_verification_' . md5($phone_number));

        // Check if the entered code matches the stored code
        if ($stored_code && $stored_code === $verification_code) {
            // Codes match - remove the temporary code storage
            delete_transient('sms_verification_' . md5($phone_number)); // Remove the code after successful verification
            // Return success response
            wp_send_json_success(array('message' => 'Verification successful'));
        } else {
            // Codes don't match or stored code has expired
            wp_send_json_error(array('message' => 'Invalid verification code'));
        }
    }

    public function create_user_from_phone() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sms_verification_nonce')) {
            wp_die('Security check failed');
        }

        $phone_number = sanitize_text_field($_POST['phone_number']);
        $verification_code = sanitize_text_field($_POST['verification_code']);

        // Verify the code again to ensure security
        $stored_code = get_transient('sms_verification_' . md5($phone_number));
        if (!$stored_code || $stored_code !== $verification_code) {
            wp_send_json_error(array('message' => 'Invalid or expired verification code'));
            return;
        }

        // At this point, we know the phone number is verified
        // Now create the user
        
        // Generate username from phone number or create a unique one
        $username = $this->generate_username_from_phone($phone_number);
        
        // Generate a random password (not really used since login is via phone)
        $password = wp_generate_password();
        
        // Use the phone number as email (with domain) or generate a unique email
        $email = $phone_number . '@smslogin.local';

        // Get the default role from settings
        $default_role = get_option('default_user_role', 'subscriber');
        
        // Create the user
        $user_id = wp_create_user($username, $password, $email);
        
        if (is_wp_error($user_id)) {
            wp_send_json_error(array('message' => $user_id->get_error_message()));
            return;
        }
        
        // Update user meta with phone number
        update_user_meta($user_id, 'phone_number', $phone_number);
        
        // Set the user role
        $user = new WP_User($user_id);
        $user->set_role($default_role);
        
        // Remove the verification code as it's no longer needed
        delete_transient('sms_verification_' . md5($phone_number));
        
        // Return success
        wp_send_json_success(array(
            'message' => 'User created successfully!',
            'user_id' => $user_id
        ));
    }
    
    /**
     * Generate a unique username from the phone number
     * Ensures the username is unique and follows WordPress username rules
     * 
     * @param string $phone_number The verified phone number
     * @return string A unique username based on the phone number
     */
    private function generate_username_from_phone($phone_number) {
        // Clean the phone number to create a valid username
        // Only keep numeric characters
        $clean_phone = preg_replace('/[^0-9]/', '', $phone_number);
        
        // Ensure username starts with a letter by prefixing with 'user_'
        $username = 'user_' . $clean_phone;
        
        // Check if username exists and increment if needed
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
     * Communicates with SMS.ir to send the verification code
     * 
     * @param string $mobile The mobile number to send the SMS to
     * @param string $verification_code The verification code to send
     * @return array Result array with success status and message
     */
    private function send_sms_via_smsir($mobile, $verification_code) {
        // Get API credentials from WordPress settings
        $api_key = get_option('sms_api_key');
        $line_number = get_option('sms_line_number');
        $template_id = get_option('sms_template_id');

        // Check if all required API settings are configured
        if (empty($api_key) || empty($line_number) || empty($template_id)) {
            return array('success' => false, 'message' => 'SMS.ir API credentials are not fully configured');
        }

        // Prepare data for sending SMS using the correct SMS.ir v1 API format
        $url = 'https://api.sms.ir/v1/send/verify';
        
        // Parameters for verification template (format: array of objects with name/value)
        // This structure matches the SMS.ir API requirements
        $params = array(
            array(
                'Parameter' => 'Code',  // This should match your template parameter name in SMS.ir
                'ParameterValue' => $verification_code  // The verification code to send
            )
        );

        // Prepare the data payload according to SMS.ir API specification
        $data = array(
            'Mobile' => $mobile,           // Recipient's phone number
            'TemplateId' => intval($template_id), // Template ID from SMS.ir
            'Parameters' => $params        // Array of parameters for the template
        );

        // Prepare the request arguments for wp_remote_post
        $args = array(
            'method' => 'POST',           // Use POST method for sending data
            'headers' => array(
                'Content-Type' => 'application/json',  // Specify JSON content type
                'X-API-KEY' => $api_key               // Include API key in headers
            ),
            'body' => json_encode($data),              // Send data as JSON
            'timeout' => 30                            // Wait up to 30 seconds for response
        );

        // Send the request to SMS.ir API
        $response = wp_remote_post($url, $args);

        // Check if the request was successful (no WP_Error)
        if (is_wp_error($response)) {
            // If there was an error, return error details
            return array('success' => false, 'message' => $response->get_error_message());
        }

        // Get the response body and decode JSON
        $response_body = wp_remote_retrieve_body($response);
        $response_data = json_decode($response_body, true);

        // Check if the SMS was sent successfully according to SMS.ir response
        // Different versions of the API might use different success indicators
        if ($response_data && isset($response_data['ok']) && $response_data['ok'] === true) {
            // Success: SMS was sent
            return array('success' => true, 'message' => 'SMS sent successfully');
        } else {
            // Failure: Extract error message from response
            $error_message = isset($response_data['message']) ? $response_data['message'] : 'Unknown error occurred';
            return array('success' => false, 'message' => $error_message);
        }
    }
}

// Initialize the plugin when this file is loaded
new SMSUserVerification();

/**
 * Shortcode function for the verification form
 * Creates a standalone form that can be placed anywhere on the site
 * 
 * @return string HTML form for SMS verification
 */
function sms_verification_shortcode() {
    // Start output buffering to capture HTML content
    ob_start();
    ?>
    <div id="sms-verification-form">
        <h3>Register with Phone Number</h3>
        <p>
            <label for="phone_number_field">Phone Number:<br/>
                <input type="tel" id="phone_number_field" name="phone_number" value="" size="25" placeholder="Enter your phone number" />
            </label>
        </p>
        <p>
            <button type="button" id="send_verification_btn" class="button button-primary">Send Verification Code</button>
            <span id="verification_status"></span>
        </p>
        <p>
            <label for="verification_code_field">Verification Code:<br/>
                <input type="text" id="verification_code_field" name="verification_code" value="" size="25" maxlength="6" placeholder="Enter verification code" />
            </label>
        </p>
        <p>
            <button type="button" id="verify_code_btn" class="button button-secondary">Verify Code</button>
        </p>
        <p id="result_message"></p>
    </div>
    <?php
    // Return the captured HTML content
    return ob_get_clean();
}
// Register the shortcode [sms_verification]
add_shortcode('sms_verification', 'sms_verification_shortcode');