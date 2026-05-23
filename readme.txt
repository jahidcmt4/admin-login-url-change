=== Admin login URL Change ===
Contributors: jahidcse
Tags: change wp-login, login, remove wp-login, wordpress login, custom login, login customizer, custom login url, images protection, content, right click disabled, F12 disabled, Copy content, disabled, Ctrl + Shift + I, Ctrl + Shift + J, Ctrl + Shift + C, Ctrl + U, wp developers, SEO, css, html
Requires at least: 4.7
Tested up to: 7.0
Stable tag: 1.2.0
Requires PHP: 5.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Allows you to Change your WordPress WebSite Login URL Slug.

== Description ==

Admin login URL Change is a very lightweight, highly secure plugin that lets you easily and safely change the URL of the login form page to anything you want. It does not change any core files. It simply intercepts page requests and works on any WordPress website. This is great for your convenience, but it also closes the door to would-be brute-force attackers.

<strong>Why Use Admin Login URL Change?</strong>

WordPress websites are common targets for automated bots and hackers attempting to gain unauthorized access via brute-force login attempts. The default login URLs (`wp-login.php` and `wp-admin`) are widely known, making them easy targets. Admin Login URL Change solves this problem by letting you rename your login URL to something unique, effectively closing this security loophole.

### 🚀 Get Even More Security with Pro Features!
Upgrade to the **Pro Version** to unlock elite-level protection mechanisms:
* **IP Address Blocker**: Block individual IPs or entire CIDR ranges from even accessing your custom login page, featuring auto-ban thresholds, ban durations, and email alerts.
* **Searchable Country Blocker**: Restrict login access to specific countries using a live, search-filtered database of 200+ global regions.
* **Login Attempt Limiter**: Set max retries, retry windows, lockout durations, and display warning alerts to prevent dictionary and brute-force attacks.
* **Two-Factor Authentication (2FA)**: Add an ironclad second layer of verification via Authenticator Apps (Google Authenticator, Authy, Microsoft Authenticator) or Email OTP.

<strong>How to use the plugin</strong>

1. Go to your dashboard and navigate to the **Admin Login Slug** menu.
2. Enter your custom login slug (e.g. `madmin`).
3. Click "Save Settings". 
4. Bookmark your new URL and test it!

== Installation ==

1. Install Admin login URL Change by uploading the `admin-login-url-change` directory to the `/wp-content/plugins/` directory.
2. Activate Admin login URL Change through the `Plugins` menu in WordPress.
3. Change your URL Slug by going to the **Admin Login Slug**
4. Now you can logout and login by new URL.

== Frequently Asked Questions ==

= Do I need to have coding skills to use Admin login URL Change? =

Absolutely not.

= Will this break standard login page actions or WooCommerce integrations? =

No, the plugin is fully compatible with native WordPress login flows and integrates seamlessly with WooCommerce checkouts and account logins.

== Screenshots ==

1. Settings
2. Other Settings

== Changelog ==

= 1.2.0 =

* Improved: Submenu "Admin Login URL Change" Position as a top-level menu "Admin Login Slug".
* Added: Seamless integration hooks for Pro features (IP Blocker, searchable Country Blocker, Login Limiter, and Two-Factor Authentication).
* Improved: Restructured dashboard settings page into a stunning, responsive Material-style grid with notice suppression.
* Improved: Codebase refactored into clean Object-Oriented Programming (OOP) structure.
* Added: Compatibility with WordPress 7.0

= 1.1.6 =

* Fixed: Broken Access Control

= 1.1.5 =

* Added: Compatibility with WordPress 6.9

= 1.1.4 =

* Improvement: Updated file Structure
* Fixed: Redirect issue fixed

= 1.1.3 =

* Fixed: Deprecated issue Fixed

= 1.1.2 =

* Added: Compatibility with WordPress 6.8

= 1.1.1 =

* Added: Compatibility with WooCommerce 9.8.1

= 1.1.0 =

* Added: Compatibility with WordPress 6.7

= 1.0.9 =

* Added: Compatibility with WordPress 6.6

= 1.0.8 =

* Removed: Auto Redirect to the Setting Page

= 1.0.7 =

* Added: Compatibility with WordPress 6.5

= 1.0.6 =

* Updated: Security
* Fixed: Nonce Validation and escaping issues

= 1.0.5 =

* Fixed: Redirect Issue

= 1.0.4 =

* Added: Compatibility with WordPress 6.4

= 1.0.3 =

* Added: Compatibility with WordPress 6.3

= 1.0.2 =

* Added: Compatibility with WordPress 6.2
* Removed: Unused Code

= 1.0.1 =

* Settings Page Integrate

= 1.0 =

* Initial version
