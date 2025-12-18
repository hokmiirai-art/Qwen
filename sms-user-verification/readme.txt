=== SMS User Verification ===
Contributors: yourname
Tags: sms, verification, phone, login
Requires at least: 4.6
Tested up to: 6.4
Stable tag: 1.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Create WordPress users via phone number verification using SMS.ir service.

== Description ==

This plugin allows users to register and login using only their phone number. It sends a verification code via SMS.ir service and creates a WordPress user account after successful verification.

Features:
* Phone number based registration
* SMS verification using SMS.ir API
* Configurable default user role
* Integration with WordPress registration form
* Shortcode for custom forms
* Detailed logging of registration events
* Main menu navigation in admin panel
* Tabbed interface for settings and logs

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/sms-user-verification` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Use the SMS Verification screen to configure the plugin

== Frequently Asked Questions ==

= How do I configure the SMS.ir settings? =

Go to the SMS Verification page and enter your API key, line number, and template ID.

= Can I change the default user role? =

Yes, you can select the default user role in the settings page.

== Changelog ==

= 1.1 =
* Added Persian comments throughout the code
* Added detailed error/success notifications
* Added tabbed interface for settings and logs
* Moved menu to main navigation instead of submenu
* Added logging functionality for registration events
* Added clear logs functionality

= 1.0 =
* Initial release