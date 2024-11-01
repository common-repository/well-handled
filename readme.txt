=== Plugin Name ===
Contributors: blobfolio
Donate link: https://blobfolio.com/plugin/well-handled/
Tags: email templates, handlebar, mustache, css, email, transactional, analytics
Requires at least: 4.7
Tested up to: 6.6
Requires PHP: 7.3
Stable tag: trunk
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Build, manage, preview, send, and track complex transactional email templates from WordPress.

== Description ==

Well-Handled lets developers build, manage, preview, send, and track complex transactional email templates with WordPress, freeing them from the time and expense of using a third-party service like Mandrill.  It comes with a ton of template processing options, easy drop-in functions for generating and sending transactional emails, and hookable filters for developers with additional needs.

  * Manage and preview email templates through WP-Admin;
  * Color-coded editor with dozens of themes;
  * Support for Handlebar/Mustache markup;
  * Preview templates in WP-Admin or send as an email;
  * Numerous post-processing options such as CSS inlining, comment removal, whitespace compression, etc., let you keep your working code readable and the rendered product optimal;
  * Shortcode and fragment support (like reusable headers, etc.);
  * Send emails via SMTP, Amazon SES, or Mandrill;
  * Track open rates and clicks, search send history, view statistics, access full message details;
  * Assign template and statistic access on a per-role basis;
  * [Deprecated] Mail sending via queue instead of realtime;

== Installation ==

1. Unzip the archive and upload the entire `well-handled` directory to your `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Done!

== Requirements ==

Well-Handled is more complex than the average plugin and therefore requires a litlte more from your server:

 * WordPress 4.7 or later.
 * PHP 7.3 or later.
 * PHP extensions: date, dom, filter, hash, imap, json, libxml, openssl, pcre
 * UTF-8 encoding.
 * Well-Handled is *not* compatible with WordPress Multi-Site.

Please note: it is **not safe** to run WordPress atop a version of PHP that has reached its [End of Life](http://php.net/supported-versions.php). Future releases of this plugin might, out of necessity, drop support for old, unmaintained versions of PHP. To ensure you continue to receive plugin updates, bug fixes, and new features, just make sure PHP is kept up-to-date. :)

== Screenshots ==

1. Manage templates in a familiar setting (WP Admin).
2. Code editor supports themable syntax highlighting.
3. Preview your templates, test compile options, and even pass arbitrary data to verify correct rendering.
4. Comprehensive reference materials viewable from WP Admin.
5. Can send via SMTP or Amazon SES, track open and click rates, and more.
6. Can search send activity and view full copies of messages sent.
7. Pretty stats!

== Privacy Policy ==

Well-Handled includes the ability for site operators to track sent emails — either basic metadata or full message content — and see which links the recipients of those messages end up visiting.

This data resides fully on the hosting web server and is not shared with any third-parties (aside from the SMTP servers used to send the messages).

While the plugin does not utilize any WordPress GDPR "Personal Data" features, it does provide mechanisms for deleting data, both selectively and automatically.

== Changelog ==

= 2.4.4 =
* [Fix] Double HTML entity encoding/decoding in some contexts.

= 2.4.3 =
* [Fix] Improve PHP 8.2 compatibility.

= 2.4.2 =
* [Fix] Improve PHP 8.1 compatibility.

= 2.4.1 =
* [Fix] Improve PHP 8 compatibility.

= 2.4.0 =
* [Change] Explicitly require minimum PHP 7.2.
* [Change] Remove plugin licensing options.
* [Fix] Javascript error on, ironically, the errors page.
* [Deprecated] The scheduled send functionality has been deprecated and will be removed at some future date.

= 2.3.6 =
* [Misc] Update SES to use v4 authentication.

= 2.3.5 =
* [Fix] `mailto:` and `tel:` could be mistakenly downgraded to `#`.

== Upgrade Notice ==

= 2.4.4 =
This release fixes an issue with double HTML entity encoding/decoding in some contexts.

= 2.4.3 =
This release improves compatibility with PHP 8.2.

= 2.4.2 =
This release improves compatibility with PHP 8.1.

= 2.4.1 =
This release improves compatibility with PHP 8.

= 2.4.0 =
This release now explicitly requires a minimum PHP version of 7.2.0, deprecates the scheduled send functionality, and removes all plugin licensing options. A few small bugs and typos have also been fixed.

= 2.3.6 =
This release updates the Amazon SES API to use v4 authentication.

= 2.3.5 =
This releases fixes a link-parsing bug that could cause `mailto:` and `tel:` to be mistakenly downgraded to `#`.
