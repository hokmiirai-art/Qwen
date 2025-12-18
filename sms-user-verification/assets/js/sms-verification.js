jQuery(document).ready(function($) {
    // مدیریت ارسال پیامک تأیید
    $('#send_verification_code, #send_verification_btn').click(function() {
        var phoneNumber = $('#phone_number, #phone_number_field').val();
        
        if (!phoneNumber) {
            alert('لطفاً شماره تلفن خود را وارد کنید');
            return;
        }
        
        // غیرفعال کردن دکمه در حین درخواست
        $(this).prop('disabled', true);
        $(this).text('در حال ارسال...');
        
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
                    $('#verification_status, #verification_message').html('<span style="color: red;">خطا: ' + response.data.message + '</span>');
                }
            },
            error: function(xhr, status, error) {
                console.log('خطا در ارسال AJAX:', error);
                $('#verification_status, #verification_message').html('<span style="color: red;">خطایی در ارسال پیامک رخ داد</span>');
            },
            complete: function() {
                // فعال کردن دوباره دکمه
                $('#send_verification_code, #send_verification_btn').prop('disabled', false);
                $('#send_verification_code, #send_verification_btn').text('ارسال کد تأیید');
            }
        });
    });
    
    // مدیریت تأیید کد پیامک
    $('#verify_code_btn').click(function() {
        var phoneNumber = $('#phone_number_field').val();
        var verificationCode = $('#verification_code_field').val();
        
        if (!phoneNumber) {
            alert('لطفاً شماره تلفن خود را وارد کنید');
            return;
        }
        
        if (!verificationCode) {
            alert('لطفاً کد تأیید را وارد کنید');
            return;
        }
        
        // غیرفعال کردن دکمه در حین درخواست
        $(this).prop('disabled', true);
        $(this).text('در حال تأیید...');
        
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
                    
                    // ایجاد کاربر به صورت خودکار پس از تأیید موفق
                    createUser(phoneNumber, verificationCode);
                } else {
                    $('#result_message').html('<span style="color: red;">خطا: ' + response.data.message + '</span>');
                }
            },
            error: function(xhr, status, error) {
                console.log('خطا در تأیید AJAX:', error);
                $('#result_message').html('<span style="color: red;">خطایی در تأیید کد رخ داد</span>');
            },
            complete: function() {
                // فعال کردن دوباره دکمه
                $('#verify_code_btn').prop('disabled', false);
                $('#verify_code_btn').text('تأیید کد');
            }
        });
    });
    
    // مدیریت ارسال فرم برای ثبت‌نام
    $('form[name="registerform"], #sms-verification-form').submit(function(e) {
        var phoneNumber = $('#phone_number, #phone_number_field').val();
        var verificationCode = $('#verification_code, #verification_code_field').val();
        
        if (!phoneNumber) {
            alert('لطفاً شماره تلفن خود را وارد کنید');
            e.preventDefault();
            return false;
        }
        
        if (!verificationCode) {
            alert('لطفاً کد تأیید را وارد کنید');
            e.preventDefault();
            return false;
        }
        
        // ما کاربر را از طریق AJAX پس از تأیید موفق ایجاد خواهیم کرد
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
                jQuery('#result_message').html('<span style="color: green;">کاربر با موفقیت ایجاد شد! می‌توانید وارد شوید.</span>');
                
                // تغییر مسیر به صفحه ورود پس از یک مکث
                setTimeout(function() {
                    window.location.href = '/wp-login.php';
                }, 2000);
            } else {
                jQuery('#result_message').html('<span style="color: red;">خطا: ' + response.data.message + '</span>');
            }
        },
        error: function(xhr, status, error) {
            console.log('خطا در ایجاد کاربر AJAX:', error);
            jQuery('#result_message').html('<span style="color: red;">خطایی در ایجاد کاربر رخ داد</span>');
        }
    });
}

// افزودن مدیریت برای درخواست AJAX جهت ایجاد کاربر پس از تأیید
jQuery(document).ready(function($) {
    // مدیریت درخواست AJAX برای ایجاد کاربر پس از تأیید
    // این پس از تأیید موفق فعال می‌شود
});