=== SMS User Verification ===
Contributors: yourname
Tags: sms, verification, user registration, phone number, authentication
Requires at least: 5.0
Tested up to: 6.4
Stable tag: 1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Create WordPress users via phone number verification using SMS.ir service.

== Description ==

This plugin allows users to register on your WordPress site using only their phone number. 
After entering their phone number, they receive a verification code via SMS through the SMS.ir service.
Once verified, a WordPress user account is created with the role selected in the settings.

Key Features:
* Phone number-based user registration
* SMS verification using SMS.ir API
* Configurable default user roles
* Integration with WordPress registration form
* Shortcode for custom registration forms

== Installation ==

1. Upload the entire `sms-user-verification` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Settings > SMS User Verification to configure your SMS.ir API credentials
4. Enter your API key, line number, and verification template ID
5. Select the default user role for new registrations

== Frequently Asked Questions ==

= What SMS.ir credentials do I need? =

You need three pieces of information from your SMS.ir panel:
1. API Key - Found in your SMS.ir account settings
2. Line Number - Your dedicated SMS number
3. Template ID - For your verification code template (must be pre-configured in SMS.ir)

= How do I set up the verification template in SMS.ir? =

In your SMS.ir panel, create a new verification template with a placeholder for the verification code.
For example: "Your verification code is {0}. Enter this code to verify your phone number."

== Changelog ==

= 1.0 =
* Initial release
* Phone number verification via SMS.ir
* User creation after successful verification
* Configurable user roles