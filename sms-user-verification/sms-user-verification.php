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

class SMSUserVerification {

    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_send_verification_sms', array($this, 'send_verification_sms'));
        add_action('wp_ajax_nopriv_send_verification_sms', array($this, 'send_verification_sms'));
        add_action('wp_ajax_verify_sms_code', array($this, 'verify_sms_code'));
        add_action('wp_ajax_nopriv_verify_sms_code', array($this, 'verify_sms_code'));
        add_action('wp_ajax_create_user_from_phone', array($this, 'create_user_from_phone'));
        add_action('wp_ajax_nopriv_create_user_from_phone', array($this, 'create_user_from_phone'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        
        // Hook into registration form if needed
        add_action('register_form', array($this, 'add_phone_field_to_registration'));
        add_action('register_post', array($this, 'validate_phone_field'), 10, 3);
    }

    public function init() {
        // Initialize the plugin
    }
    
    public function enqueue_scripts() {
        wp_enqueue_script('jquery');
        wp_enqueue_script(
            'sms-user-verification-js',
            plugins_url('/assets/js/sms-verification.js', __FILE__),
            array('jquery'),
            '1.0',
            true
        );
        wp_localize_script('sms-user-verification-js', 'ajax_object', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sms_verification_nonce')
        ));
        
        wp_enqueue_style(
            'sms-user-verification-css',
            plugins_url('/assets/css/style.css', __FILE__),
            array(),
            '1.0'
        );
    }

    public function add_admin_menu() {
        add_options_page(
            'SMS User Verification Settings',
            'SMS User Verification',
            'manage_options',
            'sms-user-verification',
            array($this, 'settings_page')
        );
    }

    public function register_settings() {
        register_setting('sms_user_verification_settings', 'sms_api_key');
        register_setting('sms_user_verification_settings', 'sms_line_number');
        register_setting('sms_user_verification_settings', 'default_user_role');
        register_setting('sms_user_verification_settings', 'sms_template_id');
    }

    public function settings_page() {
        ?>
        <div class="wrap">
            <h1>SMS User Verification Settings</h1>
            <form method="post" action="options.php">
                <?php settings_fields('sms_user_verification_settings'); ?>
                <?php do_settings_sections('sms_user_verification_settings'); ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">API Key</th>
                        <td><input type="text" name="sms_api_key" value="<?php echo esc_attr(get_option('sms_api_key')); ?>" size="50" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Line Number</th>
                        <td><input type="text" name="sms_line_number" value="<?php echo esc_attr(get_option('sms_line_number')); ?>" size="20" placeholder="e.g. 3000xxxxxx" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">SMS Template ID</th>
                        <td><input type="text" name="sms_template_id" value="<?php echo esc_attr(get_option('sms_template_id')); ?>" size="20" placeholder="Template ID for verification code" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Default User Role</th>
                        <td>
                            <select name="default_user_role">
                                <?php
                                $roles = wp_roles()->get_names();
                                foreach ($roles as $role_key => $role_name) {
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

    public function validate_phone_field($user_login, $user_email, $errors) {
        if (!isset($_POST['phone_number']) || empty($_POST['phone_number'])) {
            $errors->add('phone_number_error', __('<strong>Error</strong>: Phone number is required.'));
            return;
        }

        if (!isset($_POST['verification_code']) || empty($_POST['verification_code'])) {
            $errors->add('verification_code_error', __('<strong>Error</strong>: Verification code is required.'));
            return;
        }

        $phone_number = sanitize_text_field($_POST['phone_number']);
        $verification_code = sanitize_text_field($_POST['verification_code']);

        // Verify the code
        $stored_code = get_transient('sms_verification_' . md5($phone_number));
        if (!$stored_code || $stored_code !== $verification_code) {
            $errors->add('verification_code_invalid', __('<strong>Error</strong>: Invalid verification code.'));
            return;
        }
    }

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

    public function verify_sms_code() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sms_verification_nonce')) {
            wp_die('Security check failed');
        }

        $phone_number = sanitize_text_field($_POST['phone_number']);
        $verification_code = sanitize_text_field($_POST['verification_code']);

        // Retrieve stored code
        $stored_code = get_transient('sms_verification_' . md5($phone_number));

        if ($stored_code && $stored_code === $verification_code) {
            // Code is valid
            delete_transient('sms_verification_' . md5($phone_number)); // Remove the code after successful verification
            wp_send_json_success(array('message' => 'Verification successful'));
        } else {
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
    
    private function generate_username_from_phone($phone_number) {
        // Clean the phone number to create a valid username
        $clean_phone = preg_replace('/[^0-9]/', '', $phone_number);
        
        // Ensure username starts with a letter
        $username = 'user_' . $clean_phone;
        
        // Check if username exists and increment if needed
        $original_username = $username;
        $counter = 1;
        
        while (username_exists($username)) {
            $username = $original_username . '_' . $counter;
            $counter++;
        }
        
        return $username;
    }

    private function send_sms_via_smsir($mobile, $verification_code) {
        $api_key = get_option('sms_api_key');
        $line_number = get_option('sms_line_number');
        $template_id = get_option('sms_template_id');

        if (empty($api_key) || empty($line_number) || empty($template_id)) {
            return array('success' => false, 'message' => 'SMS.ir API credentials are not fully configured');
        }

        // Prepare data for sending SMS using the correct SMS.ir v1 API format
        $url = 'https://api.sms.ir/v1/send/verify';
        
        // Parameters for verification template (format: array of objects with name/value)
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

        if (is_wp_error($response)) {
            return array('success' => false, 'message' => $response->get_error_message());
        }

        $response_body = wp_remote_retrieve_body($response);
        $response_data = json_decode($response_body, true);

        if ($response_data && isset($response_data['ok']) && $response_data['ok'] === true) {
            return array('success' => true, 'message' => 'SMS sent successfully');
        } else {
            $error_message = isset($response_data['message']) ? $response_data['message'] : 'Unknown error occurred';
            return array('success' => false, 'message' => $error_message);
        }
    }
}

// Initialize the plugin
new SMSUserVerification();

// Create a shortcode for the verification form
function sms_verification_shortcode() {
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
    return ob_get_clean();
}
add_shortcode('sms_verification', 'sms_verification_shortcode');