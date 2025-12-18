<?php
/**
 * Plugin Name: SMS User Verification
 * Description: Create WordPress users via phone number verification using SMS.ir service
 * Version: 1.1
 * Author: Your Name
 */

// جلوگیری از دسترسی مستقیم به فایل
if (!defined('ABSPATH')) {
    exit;
}

// تعریف ثابت‌های پلاگین
define('SMS_USER_VERIFICATION_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SMS_USER_VERIFICATION_PLUGIN_PATH', plugin_dir_path(__FILE__));

/**
 * کلاس اصلی پلاگین تأیید کاربر از طریق پیامک
 * تمام عملکردهای پلاگین را مدیریت می‌کند شامل:
 * - صفحه تنظیمات مدیریت
 * - فرم تأیید در سمت کاربر
 * - ارتباط با سرویس پیامکی SMS.ir
 * - ایجاد کاربر بعد از تأیید
 */
class SMSUserVerification {

    /**
     * متد سازنده
     * تمام هوک‌ها و اکشن‌های مورد نیاز برای پلاگین را مقداردهی اولیه می‌کند
     */
    public function __construct() {
        // مقداردهی اولیه پلاگین در زمان راه‌اندازی وردپرس
        add_action('init', array($this, 'init'));
        
        // اضافه کردن اسکریپت‌ها و استایل‌های سمت کاربر
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // مدیریت‌های AJAX برای ارسال و تأیید کدهای پیامک
        add_action('wp_ajax_send_verification_sms', array($this, 'send_verification_sms'));
        add_action('wp_ajax_nopriv_send_verification_sms', array($this, 'send_verification_sms')); // برای کاربران وارد نشده
        add_action('wp_ajax_verify_sms_code', array($this, 'verify_sms_code'));
        add_action('wp_ajax_nopriv_verify_sms_code', array($this, 'verify_sms_code')); // برای کاربران وارد نشده
        add_action('wp_ajax_create_user_from_phone', array($this, 'create_user_from_phone'));
        add_action('wp_ajax_nopriv_create_user_from_phone', array($this, 'create_user_from_phone')); // برای کاربران وارد نشده
        
        // منوی مدیریت و تنظیمات
        add_action('admin_menu', array($this, 'add_main_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        
        // یکپارچه‌سازی با فرم ثبت‌نام وردپرس
        add_action('register_form', array($this, 'add_phone_field_to_registration'));
        add_action('register_post', array($this, 'validate_phone_field'), 10, 3);
        
        // افزودن منو اصلی
        add_action('admin_menu', array($this, 'add_main_menu'));
        
        // ایجاد جدول لاگ‌ها در زمان فعال‌سازی
        register_activation_hook(__FILE__, array($this, 'create_log_table'));
    }

    /**
     * مقداردهی اولیه اجزای پلاگین
     * در حال حاضر خالی است اما می‌تواند برای کارهای مقداردهی آینده استفاده شود
     */
    public function init() {
        // مقداردهی اولیه پلاگین
    }
    
    /**
     * بارگذاری اسکریپت‌ها و استایل‌های سمت کاربر
     * فایل‌های جاوااسکریپت و CSS مورد نیاز برای فرم تأیید را بارگذاری می‌کند
     */
    public function enqueue_scripts() {
        // اطمینان از بارگذاری جی‌کوئری
        wp_enqueue_script('jquery');
        
        // بارگذاری فایل جاوااسکریپت برای عملکرد سمت کاربر
        wp_enqueue_script(
            'sms-user-verification-js',
            plugins_url('/assets/js/sms-verification.js', __FILE__),
            array('jquery'), // وابستگی‌ها
            '1.0',          // نسخه
            true            // بارگذاری در فوتر
        );
        
        // ارسال URL آژکس و nonce به جاوااسکریپت برای امنیت
        wp_localize_script('sms-user-verification-js', 'ajax_object', array(
            'ajax_url' => admin_url('admin-ajax.php'), // نقطه پایانی آژکس وردپرس
            'nonce' => wp_create_nonce('sms_verification_nonce') // توکن امنیتی
        ));
        
        // بارگذاری فایل CSS برای استایل‌دهی
        wp_enqueue_style(
            'sms-user-verification-css',
            plugins_url('/assets/css/style.css', __FILE__),
            array(), // وابستگی‌ها
            '1.0'    // نسخه
        );
    }

    /**
     * افزودن منو اصلی پلاگین
     * یک صفحه گزینه‌ها در منوی اصلی مدیریت وردپرس ایجاد می‌کند
     */
    public function add_main_menu() {
        // افزودن صفحه منو در سطح بالایی (نه زیر منو)
        add_menu_page(
            'SMS User Verification',           // عنوان صفحه
            'SMS Verification',                // عنوان منو
            'manage_options',                  // سطح دسترسی مورد نیاز
            'sms-user-verification',           // اسلاگ منو
            array($this, 'main_admin_page'),   // تابع فراخوانی برای نمایش صفحه
            'dashicons-phone',                 // آیکون منو
            30                                 // موقعیت در منو
        );
    }

    /**
     * ثبت فیلدهای تنظیمات
     * تنظیمات پلاگین را در وردپرس ثبت می‌کند
     */
    public function register_settings() {
        // ثبت تنظیمات ما در پایگاه داده وردپرس
        register_setting('sms_user_verification_settings', 'sms_api_key');       // کلید API سایت sms.ir
        register_setting('sms_user_verification_settings', 'sms_line_number');   // شماره خط سرویس پیامک
        register_setting('sms_user_verification_settings', 'default_user_role'); // نقش پیش‌فرض کاربران جدید
        register_setting('sms_user_verification_settings', 'sms_template_id');   // شناسه الگوی پیامک
    }

    /**
     * ایجاد جدول لاگ برای ذخیره رویدادهای پلاگین
     * این جدول برای ذخیره رویدادهای ثبت‌نام، ارسال پیامک و سایر فعالیت‌ها استفاده می‌شود
     */
    public function create_log_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'sms_verification_logs';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            phone_number varchar(20) NOT NULL,
            event_type varchar(50) NOT NULL,
            description text NOT NULL,
            status varchar(20) DEFAULT 'pending',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * صفحه اصلی مدیریت پلاگین با زبانه‌ها
     * صفحه کامل HTML را برای صفحه تنظیمات و لاگ‌های پلاگین خروجی می‌دهد
     */
    public function main_admin_page() {
        // بررسی اجازه دسترسی
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        // گرفتن تب فعلی
        $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'settings';
        
        // ذخیره تنظیمات اگر فرم ارسال شده باشد
        if (isset($_POST['submit_settings'])) {
            $this->save_settings();
        }
        
        ?>
        <div class="wrap">
            <h1>SMS User Verification</h1>
            
            <!-- زبانه‌های صفحه مدیریت -->
            <nav class="nav-tab-wrapper">
                <a href="?page=sms-user-verification&tab=settings" class="nav-tab <?php echo $active_tab == 'settings' ? 'nav-tab-active' : ''; ?>">تنظیمات</a>
                <a href="?page=sms-user-verification&tab=logs" class="nav-tab <?php echo $active_tab == 'logs' ? 'nav-tab-active' : ''; ?>">گزارشات</a>
            </nav>
            
            <div class="tab-content">
                <?php 
                // نمایش پیام‌های موفقیت یا خطا
                settings_errors('sms_user_verification_messages');
                
                if ($active_tab == 'settings') { ?>
                    <form method="post" action="">
                        <?php wp_nonce_field('update-options'); ?>
                        <table class="form-table">
                            <!-- فیلد کلید API -->
                            <tr valign="top">
                                <th scope="row">کلید API</th>
                                <td>
                                    <input type="text" name="sms_api_key" value="<?php echo esc_attr(get_option('sms_api_key')); ?>" size="50" />
                                    <p class="description">کلید API از سایت sms.ir را وارد کنید</p>
                                </td>
                            </tr>
                            <!-- فیلد شماره خط -->
                            <tr valign="top">
                                <th scope="row">شماره خط</th>
                                <td>
                                    <input type="text" name="sms_line_number" value="<?php echo esc_attr(get_option('sms_line_number')); ?>" size="20" placeholder="مثلاً 3000xxxxxx" />
                                    <p class="description">شماره خط خدماتی از سایت sms.ir</p>
                                </td>
                            </tr>
                            <!-- فیلد ID الگوی پیامک -->
                            <tr valign="top">
                                <th scope="row">ID الگوی پیامک</th>
                                <td>
                                    <input type="text" name="sms_template_id" value="<?php echo esc_attr(get_option('sms_template_id')); ?>" size="20" placeholder="ID الگوی تأیید کد" />
                                    <p class="description">ID الگوی تأیید کد در سایت sms.ir</p>
                                </td>
                            </tr>
                            <!-- فیلد نقش پیش‌فرض کاربر -->
                            <tr valign="top">
                                <th scope="row">نقش پیش‌فرض کاربر</th>
                                <td>
                                    <select name="default_user_role">
                                        <?php
                                        // گرفتن تمام نقش‌های موجود در وردپرس
                                        $roles = wp_roles()->get_names();
                                        // چرخه زدن روی تمام نقش‌ها و ایجاد عناصر گزینه
                                        foreach ($roles as $role_key => $role_name) {
                                            // تنظیم صفت انتخاب شده اگر این نقش ذخیره شده است
                                            $selected = (get_option('default_user_role') === $role_key) ? 'selected' : '';
                                            echo '<option value="' . esc_attr($role_key) . '" ' . $selected . '>' . esc_html($role_name) . '</option>';
                                        }
                                        ?>
                                    </select>
                                    <p class="description">نقش پیش‌فرض برای کاربران جدید</p>
                                </td>
                            </tr>
                        </table>
                        
                        <?php submit_button('ذخیره تنظیمات', 'primary', 'submit_settings'); ?>
                    </form>
                <?php } elseif ($active_tab == 'logs') { ?>
                    <h2>گزارشات ثبت‌نام و ارسال پیامک</h2>
                    
                    <?php
                    // نمایش لاگ‌ها
                    $logs = $this->get_verification_logs();
                    
                    if (!empty($logs)) {
                        echo '<table class="wp-list-table widefat fixed striped">';
                        echo '<thead>';
                        echo '<tr>';
                        echo '<th>ID</th>';
                        echo '<th>شماره تلفن</th>';
                        echo '<th>نوع رویداد</th>';
                        echo '<th>شرح</th>';
                        echo '<th>وضعیت</th>';
                        echo '<th>تاریخ</th>';
                        echo '</tr>';
                        echo '</thead>';
                        echo '<tbody>';
                        
                        foreach ($logs as $log) {
                            echo '<tr>';
                            echo '<td>' . $log->id . '</td>';
                            echo '<td>' . esc_html($log->phone_number) . '</td>';
                            echo '<td>' . esc_html($log->event_type) . '</td>';
                            echo '<td>' . esc_html($log->description) . '</td>';
                            echo '<td>' . esc_html($log->status) . '</td>';
                            echo '<td>' . esc_html($log->created_at) . '</td>';
                            echo '</tr>';
                        }
                        
                        echo '</tbody>';
                        echo '</table>';
                        
                        // اضافه کردن دکمه پاک کردن لاگ‌ها
                        echo '<form method="post" style="margin-top: 20px;">';
                        wp_nonce_field('clear_logs_nonce');
                        echo '<input type="hidden" name="clear_logs" value="1" />';
                        submit_button('پاک کردن تمام گزارشات', 'delete', 'submit', false);
                        echo '</form>';
                    } else {
                        echo '<p>هیچ گزارشی یافت نشد.</p>';
                    }
                    ?>
                <?php } ?>
            </div>
        </div>
        <?php
    }

    /**
     * ذخیره تنظیمات پلاگین
     */
    private function save_settings() {
        if (!isset($_POST['submit_settings']) || !wp_verify_nonce($_POST['_wpnonce'], 'update-options')) {
            return;
        }
        
        update_option('sms_api_key', sanitize_text_field($_POST['sms_api_key']));
        update_option('sms_line_number', sanitize_text_field($_POST['sms_line_number']));
        update_option('sms_template_id', sanitize_text_field($_POST['sms_template_id']));
        update_option('default_user_role', sanitize_text_field($_POST['default_user_role']));
        
        // نمایش پیام موفقیت
        add_settings_error('sms_user_verification_messages', 'sms_settings_updated', 'تنظیمات با موفقیت ذخیره شد.', 'updated');
    }

    /**
     * گرفتن لاگ‌های تأیید
     * 
     * @return array آرایه از لاگ‌ها
     */
    private function get_verification_logs() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'sms_verification_logs';
        
        $logs = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC LIMIT 100");
        
        return $logs;
    }

    /**
     * ثبت رویداد در جدول لاگ
     * 
     * @param string $phone_number شماره تلفن
     * @param string $event_type نوع رویداد
     * @param string $description شرح رویداد
     * @param string $status وضعیت
     */
    private function log_event($phone_number, $event_type, $description, $status = 'completed') {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'sms_verification_logs';
        
        $wpdb->insert(
            $table_name,
            array(
                'phone_number' => $phone_number,
                'event_type' => $event_type,
                'description' => $description,
                'status' => $status,
                'created_at' => current_time('mysql')
            ),
            array(
                '%s',
                '%s',
                '%s',
                '%s',
                '%s'
            )
        );
    }

    /**
     * افزودن فیلد شماره تلفن به فرم ثبت‌نام وردپرس
     * خروجی HTML برای فیلدهای شماره تلفن و کد تأیید را فراهم می‌کند
     */
    public function add_phone_field_to_registration() {
        ?>
        <p>
            <label for="phone_number"><?php _e('شماره تلفن'); ?><br/>
                <input type="tel" name="phone_number" id="phone_number" class="input" value="<?php echo ( ! empty($_POST['phone_number']) ) ? esc_attr($_POST['phone_number']) : ''; ?>" size="25" />
            </label>
        </p>
        <p>
            <button type="button" id="send_verification_code" class="button button-secondary">ارسال کد تأیید</button>
            <span id="verification_message"></span>
        </p>
        <p>
            <label for="verification_code"><?php _e('کد تأیید'); ?><br/>
                <input type="text" name="verification_code" id="verification_code" class="input" value="" size="25" maxlength="6" />
            </label>
        </p>
        <?php
    }

    /**
     * اعتبارسنجی شماره تلفن و کد تأیید در زمان ثبت‌نام
     * بررسی می‌کند که شماره تلفن و کد تأیید ارائه شده و معتبر باشند
     * 
     * @param string $user_login نام کاربری بدون فرمت
     * @param string $user_email ایمیل بدون فرمت
     * @param WP_Error $errors شیء خطا برای افزودن خطاها
     */
    public function validate_phone_field($user_login, $user_email, $errors) {
        // بررسی اینکه آیا شماره تلفن ارائه شده است
        if (!isset($_POST['phone_number']) || empty($_POST['phone_number'])) {
            $errors->add('phone_number_error', __('<strong>خطا</strong>: شماره تلفن الزامی است.'));
            return;
        }

        // بررسی اینکه آیا کد تأیید ارائه شده است
        if (!isset($_POST['verification_code']) || empty($_POST['verification_code'])) {
            $errors->add('verification_code_error', __('<strong>خطا</strong>: کد تأیید الزامی است.'));
            return;
        }

        // پالایش ورودی‌ها
        $phone_number = sanitize_text_field($_POST['phone_number']);
        $verification_code = sanitize_text_field($_POST['verification_code']);

        // تأیید کد با کد ذخیره شده
        $stored_code = get_transient('sms_verification_' . md5($phone_number));
        if (!$stored_code || $stored_code !== $verification_code) {
            $errors->add('verification_code_invalid', __('<strong>خطا</strong>: کد تأیید نامعتبر است.'));
            
            // ثبت خطا در جدول لاگ
            $this->log_event($phone_number, 'validation_failed', 'Invalid verification code entered', 'failed');
            return;
        }
        
        // ثبت موفقیت در جدول لاگ
        $this->log_event($phone_number, 'validation_success', 'Verification code validated successfully', 'completed');
    }

    /**
     * مدیریت آژکس برای ارسال کد تأیید پیامک
     * شماره تلفن را اعتبارسنجی کرده و کد تأیید را از طریق API sms.ir ارسال می‌کند
     */
    public function send_verification_sms() {
        // تأیید nonce برای امنیت
        if (!wp_verify_nonce($_POST['nonce'], 'sms_verification_nonce')) {
            $this->log_event('', 'security_failed', 'Invalid nonce in send_verification_sms', 'failed');
            wp_die('بررسی امنیتی شکست خورد');
        }

        $phone_number = sanitize_text_field($_POST['phone_number']);

        // اعتبارسنجی فرمت شماره تلفن (اعتبارسنجی اولیه)
        if (!preg_match('/^(\\+98|0)?9\\d{9}$/', $phone_number) && !preg_match('/^(\\+98|0)?09\\d{9}$/', $phone_number)) {
            $this->log_event($phone_number, 'validation_failed', 'Invalid phone number format', 'failed');
            wp_send_json_error(array('message' => 'فرمت شماره تلفن نامعتبر است'));
            return;
        }

        // تولید یک کد تأیید تصادفی 6 رقمی
        $verification_code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);

        // ذخیره موقت کد (معتبر برای 10 دقیقه)
        set_transient('sms_verification_' . md5($phone_number), $verification_code, 10 * MINUTE_IN_SECONDS);

        // ارسال پیامک از طریق API sms.ir
        $result = $this->send_sms_via_smsir($phone_number, $verification_code);

        if ($result['success']) {
            // ثبت موفقیت ارسال پیامک
            $this->log_event($phone_number, 'sms_sent', 'Verification code sent successfully via SMS.ir', 'completed');
            wp_send_json_success(array('message' => 'کد تأیید با موفقیت ارسال شد'));
        } else {
            // ثبت خطا در ارسال پیامک
            $this->log_event($phone_number, 'sms_failed', 'Failed to send verification code: ' . $result['message'], 'failed');
            wp_send_json_error(array('message' => $result['message']));
        }
    }

    /**
     * مدیریت آژکس برای تأیید کد پیامک
     * بررسی می‌کند که آیا کد وارد شده با کد ارسال شده از طریق پیامک مطابقت دارد
     */
    public function verify_sms_code() {
        // تأیید nonce برای امنیت جهت جلوگیری از حملات CSRF
        if (!wp_verify_nonce($_POST['nonce'], 'sms_verification_nonce')) {
            $this->log_event('', 'security_failed', 'Invalid nonce in verify_sms_code', 'failed');
            wp_die('بررسی امنیتی شکست خورد');
        }

        // پالایش ورودی‌ها
        $phone_number = sanitize_text_field($_POST['phone_number']);
        $verification_code = sanitize_text_field($_POST['verification_code']);

        // بازیابی کد تأیید ذخیره شده از ترانزیت‌های وردپرس
        $stored_code = get_transient('sms_verification_' . md5($phone_number));

        // بررسی مطابقت کد وارد شده با کد ذخیره شده
        if ($stored_code && $stored_code === $verification_code) {
            // کدها مطابقت دارند - حذف ذخیره موقت کد
            delete_transient('sms_verification_' . md5($phone_number)); // حذف کد پس از تأیید موفقیت آمیز
            // ثبت موفقیت تأیید
            $this->log_event($phone_number, 'verification_success', 'SMS code verified successfully', 'completed');
            // بازگشت پاسخ موفقیت
            wp_send_json_success(array('message' => 'تأیید با موفقیت انجام شد'));
        } else {
            // کدها مطابقت ندارند یا کد ذخیره شده منقضی شده است
            $this->log_event($phone_number, 'verification_failed', 'Invalid verification code entered', 'failed');
            wp_send_json_error(array('message' => 'کد تأیید نامعتبر است'));
        }
    }

    /**
     * ایجاد کاربر از طریق شماره تلفن
     * پس از تأیید موفقیت آمیز، کاربر وردپرس جدیدی ایجاد می‌کند
     */
    public function create_user_from_phone() {
        // تأیید nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sms_verification_nonce')) {
            $this->log_event('', 'security_failed', 'Invalid nonce in create_user_from_phone', 'failed');
            wp_die('بررسی امنیتی شکست خورد');
        }

        $phone_number = sanitize_text_field($_POST['phone_number']);
        $verification_code = sanitize_text_field($_POST['verification_code']);

        // تأیید مجدد کد برای اطمینان از امنیت
        $stored_code = get_transient('sms_verification_' . md5($phone_number));
        if (!$stored_code || $stored_code !== $verification_code) {
            $this->log_event($phone_number, 'user_creation_failed', 'Invalid or expired verification code during user creation', 'failed');
            wp_send_json_error(array('message' => 'کد تأیید نامعتبر یا منقضی شده است'));
            return;
        }

        // در این مرحله، می‌دانیم که شماره تلفن تأیید شده است
        // حالا کاربر را ایجاد می‌کنیم
        
        // تولید نام کاربری از شماره تلفن یا ایجاد یک نام کاربری منحصر به فرد
        $username = $this->generate_username_from_phone($phone_number);
        
        // تولید یک رمز عبور تصادفی (در واقع استفاده نمی‌شود چون ورود با تلفن است)
        $password = wp_generate_password();
        
        // استفاده از شماره تلفن به عنوان ایمیل (با دامنه) یا تولید یک ایمیل منحصر به فرد
        $email = $phone_number . '@smslogin.local';

        // گرفتن نقش پیش‌فرض از تنظیمات
        $default_role = get_option('default_user_role', 'subscriber');
        
        // ایجاد کاربر
        $user_id = wp_create_user($username, $password, $email);
        
        if (is_wp_error($user_id)) {
            $this->log_event($phone_number, 'user_creation_failed', 'Failed to create user: ' . $user_id->get_error_message(), 'failed');
            wp_send_json_error(array('message' => $user_id->get_error_message()));
            return;
        }
        
        // به‌روزرسانی متا داده کاربر با شماره تلفن
        update_user_meta($user_id, 'phone_number', $phone_number);
        
        // تنظیم نقش کاربر
        $user = new WP_User($user_id);
        $user->set_role($default_role);
        
        // حذف کد تأیید چون دیگر نیازی نیست
        delete_transient('sms_verification_' . md5($phone_number));
        
        // ثبت موفقیت ایجاد کاربر
        $this->log_event($phone_number, 'user_created', 'User created successfully with ID: ' . $user_id, 'completed');
        
        // بازگشت موفقیت
        wp_send_json_success(array(
            'message' => 'کاربر با موفقیت ایجاد شد!',
            'user_id' => $user_id
        ));
    }
    
    /**
     * تولید یک نام کاربری منحصر به فرد از شماره تلفن
     * اطمینان می‌دهد که نام کاربری منحصر به فرد است و قوانین نام کاربری وردپرس را رعایت می‌کند
     * 
     * @param string $phone_number شماره تلفن تأیید شده
     * @return string یک نام کاربری منحصر به فرد بر اساس شماره تلفن
     */
    private function generate_username_from_phone($phone_number) {
        // پاک کردن شماره تلفن برای ایجاد یک نام کاربری معتبر
        // فقط کاراکترهای عددی را نگه می‌دارد
        $clean_phone = preg_replace('/[^0-9]/', '', $phone_number);
        
        // اطمینان از اینکه نام کاربری با حرف شروع می‌شود با پیشوند دادن 'user_'
        $username = 'user_' . $clean_phone;
        
        // بررسی اینکه آیا نام کاربری وجود دارد و در صورت نیاز افزایش می‌دهد
        $original_username = $username;
        $counter = 1;
        
        // تا زمانی که یک نام کاربری منحصر به فرد پیدا شود، ادامه می‌دهد
        while (username_exists($username)) {
            $username = $original_username . '_' . $counter;
            $counter++;
        }
        
        return $username;
    }

    /**
     * ارسال پیامک از طریق API SMS.ir
     * با سرویس SMS.ir ارتباط برقرار می‌کند و کد تأیید را ارسال می‌کند
     * 
     * @param string $mobile شماره موبایلی که پیامک به آن ارسال می‌شود
     * @param string $verification_code کد تأییدی که باید ارسال شود
     * @return array آرایه نتیجه با وضعیت موفقیت و پیام
     */
    private function send_sms_via_smsir($mobile, $verification_code) {
        // گرفتن اطلاعات احراز هویت API از تنظیمات وردپرس
        $api_key = get_option('sms_api_key');
        $line_number = get_option('sms_line_number');
        $template_id = get_option('sms_template_id');

        // بررسی اینکه آیا تمام تنظیمات API مورد نیاز پیکربندی شده‌اند
        if (empty($api_key) || empty($line_number) || empty($template_id)) {
            return array('success' => false, 'message' => 'اطلاعات احراز هویت API سایت sms.ir کامل نیست');
        }

        // آماده‌سازی داده‌ها برای ارسال پیامک با استفاده از فرمت صحیح API sms.ir v1
        $url = 'https://api.sms.ir/v1/send/verify';
        
        // پارامترهای الگوی تأیید (فرمت: آرایه ای از اشیاء با نام/مقدار)
        // این ساختار با الزامات API سایت sms.ir مطابقت دارد
        $params = array(
            array(
                'Parameter' => 'Code',  // این باید با نام پارامتر الگوی شما در سایت sms.ir مطابقت داشته باشد
                'ParameterValue' => $verification_code  // کد تأییدی که باید ارسال شود
            )
        );

        // آماده‌سازی بار داده طبق مشخصات API سایت sms.ir
        $data = array(
            'Mobile' => $mobile,           // شماره گیرنده
            'TemplateId' => intval($template_id), // شناسه الگو از سایت sms.ir
            'Parameters' => $params        // آرایه پارامترهای الگو
        );

        // آماده‌سازی آرگومان‌های درخواست برای wp_remote_post
        $args = array(
            'method' => 'POST',           // استفاده از روش POST برای ارسال داده
            'headers' => array(
                'Content-Type' => 'application/json',  // مشخص کردن نوع محتوای JSON
                'X-API-KEY' => $api_key               // شامل کلید API در هدرها
            ),
            'body' => json_encode($data),              // ارسال داده به صورت JSON
            'timeout' => 30                            // انتظار تا 30 ثانیه برای پاسخ
        );

        // ارسال درخواست به API سایت sms.ir
        $response = wp_remote_post($url, $args);

        // بررسی اینکه آیا درخواست موفقیت آمیز بوده (بدون WP_Error)
        if (is_wp_error($response)) {
            // اگر خطا وجود داشته باشد، جزئیات خطا را برگردان
            return array('success' => false, 'message' => $response->get_error_message());
        }

        // گرفتن بدنه پاسخ و دیکد کردن JSON
        $response_body = wp_remote_retrieve_body($response);
        $response_data = json_decode($response_body, true);

        // بررسی اینکه آیا پیامک با موفقیت ارسال شده طبق پاسخ API سایت sms.ir
        // نسخه‌های مختلف API ممکن است نشانگرهای موفقیت مختلفی داشته باشند
        if ($response_data && isset($response_data['ok']) && $response_data['ok'] === true) {
            // موفقیت: پیامک ارسال شد
            return array('success' => true, 'message' => 'پیامک با موفقیت ارسال شد');
        } else {
            // شکست: استخراج پیام خطا از پاسخ
            $error_message = isset($response_data['message']) ? $response_data['message'] : 'خطای ناشناخته رخ داد';
            return array('success' => false, 'message' => $error_message);
        }
    }
}

// مقداردهی اولیه پلاگین زمانی که این فایل بارگذاری می‌شود
new SMSUserVerification();

/**
 * تابع کوتاه‌کد برای فرم تأیید
 * یک فرم مستقل ایجاد می‌کند که می‌تواند در هر جای سایت قرار گیرد
 * 
 * @return string HTML فرم برای تأیید پیامک
 */
function sms_verification_shortcode() {
    // شروع بافر خروجی برای گرفتن محتوای HTML
    ob_start();
    ?>
    <div id="sms-verification-form">
        <h3>ثبت‌نام با شماره تلفن</h3>
        <p>
            <label for="phone_number_field">شماره تلفن:<br/>
                <input type="tel" id="phone_number_field" name="phone_number" value="" size="25" placeholder="شماره تلفن خود را وارد کنید" />
            </label>
        </p>
        <p>
            <button type="button" id="send_verification_btn" class="button button-primary">ارسال کد تأیید</button>
            <span id="verification_status"></span>
        </p>
        <p>
            <label for="verification_code_field">کد تأیید:<br/>
                <input type="text" id="verification_code_field" name="verification_code" value="" size="25" maxlength="6" placeholder="کد تأیید را وارد کنید" />
            </label>
        </p>
        <p>
            <button type="button" id="verify_code_btn" class="button button-secondary">تأیید کد</button>
        </p>
        <p id="result_message"></p>
    </div>
    <?php
    // بازگرداندن محتوای گرفته شده از بافر
    return ob_get_clean();
}
// ثبت کوتاه‌کد [sms_verification]
add_shortcode('sms_verification', 'sms_verification_shortcode');