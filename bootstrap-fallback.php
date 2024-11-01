<?php
/**
 * Well-Handled - Fallback Bootstrap
 *
 * This is run on environments that do not meet the main plugin
 * requirements. It will either deactivate the plugin (if it has never
 * been active) or provide a semi-functional fallback environment to
 * keep the site from breaking, and suggest downgrading to the legacy
 * version.
 *
 * @package Well-Handled
 * @author  Blobfolio, LLC <hello@blobfolio.com>
 */

/**
 * Do not execute this file directly.
 */
if (! defined('ABSPATH')) {
	exit;
}



// ---------------------------------------------------------------------
// Compatibility Checking
// ---------------------------------------------------------------------

// There will be errors. What are they?
$wh_errors = array();

if (version_compare(PHP_VERSION, WH_PHP_MIN) < 0) {
	$wh_errors['version'] = \sprintf(
		__('PHP %s or newer is required.', 'well-handled'),
		WH_PHP_MIN
	);
}

if (function_exists('is_multisite') && is_multisite()) {
	$wh_errors['multisite'] = __('This plugin cannot be used on Multi-Site.', 'well-handled');
}

if (! class_exists('DOMDocument')) {
	$wh_errors['domdocument'] = __('The DOMDocument PHP extension is required.', 'well-handled');
}

if (! function_exists('libxml_disable_entity_loader')) {
	$wh_errors['libxml'] = __('The libxml PHP extension is required.', 'well-handled');
}

if (! function_exists('hash_algos') || ! in_array('sha512', hash_algos(), true)) {
	$wh_errors['hash_algos'] = __('PHP must support basic hashing algorithms like SHA512.', 'well-handled');
}

// --------------------------------------------------------------------- end compatibility



// ---------------------------------------------------------------------
// Notices
// ---------------------------------------------------------------------

/**
 * Admin Notice
 *
 * @return bool True/false.
 */
function wh_admin_notice() {
	global $wh_errors;

	if (empty($wh_errors) || ! is_array($wh_errors)) {
		return false;
	}
	?>
	<div class="notice notice-error">
		<p><?php
		echo sprintf(
			__('Your server does not meet the requirements for running %s. You or your system administrator should take a look at the following:', 'well-handled'),
			'<strong>Well-Handled Email Templates</strong>'
		);
		?></p>

		<?php
		foreach ($wh_errors as $error) {
			echo '<p>&nbsp;&nbsp;&mdash; ' . esc_html($error) . '</p>';
		}

		// Can we recommend the old version?
		if (isset($wh_errors['disabled'])) {
			unset($wh_errors['disabled']);
		}
		?>
	</div>
	<?php
	return true;
}
add_action('admin_notices', 'wh_admin_notice');

/**
 * Self-Deactivate
 *
 * If the environment can't support the plugin and the environment never
 * supported the plugin, simply remove it.
 *
 * @return bool True/false.
 */
function wh_deactivate() {
	// If the DB version option is set, an older version must have
	// once been installed. We won't auto-deactivate.
	if ('never' !== get_option('wh_db_version', 'never')) {
		return false;
	}

	require_once ABSPATH . 'wp-admin/includes/plugin.php';
	deactivate_plugins(WH_INDEX);

	global $wh_errors;
	$wh_errors['disabled'] = __('The plugin has been automatically disabled.', 'well-handled');

	if (isset($_GET['activate'])) {
		unset($_GET['activate']);
	}

	return true;
}
add_action('admin_init', 'wh_deactivate');

// --------------------------------------------------------------------- end notices



// ---------------------------------------------------------------------
// User Functions
// ---------------------------------------------------------------------

// These functions existed in an earlier version of the plugin and so
// are retained here for compatibility reasons. These functions are not
// functional in the fallback version; we just want to avoid breaking
// errors.

/**
 * Mail Template
 *
 * @param string|array $template_slug Template slug.
 * @param array $data Data.
 * @param array $options Options.
 * @return bool True/false.
 */
function wh_mail_template($template_slug, $data=null, $options=null) {
	// Send the email to the blog owner.
	$email = get_bloginfo('admin_email');
	$subject = sprintf('[%s] Server Incompatibility Error', get_bloginfo('name'));
	$body = 'Your server does not meet the requirements for running *Well-Handled Email Templates*. Please visit ' . admin_url('plugins.php') . ' to review the issues.';
	$body .= "\n\n" . str_repeat('-', 25) . "\n\nThe following message could not be compiled. The arguments are presented below for your reference.\n\n";

	// JSON is the easiest way to convey this information somewhat intelligibly.
	$out = array(
		'template'=>$template_slug,
		'template_options'=>$options,
		'data'=>$data,
	);
	$body .= json_encode($out, JSON_PRETTY_PRINT);

	return wp_mail($email, $subject, $body);
}

/**
 * Mail General
 *
 * @param string|array $to To.
 * @param string $subject Subject.
 * @param string $message Message.
 * @param string|array $headers Headers.
 * @param string|array $attachments Attachments.
 * @param bool $testmode Testmode.
 * @return bool True/false.
 */
function wh_mail($to, $subject, $message, $headers=null, $attachments=null, $testmode=false) {
	return wp_mail($to, $subject, $message, $headers, $attachments);
}

/**
 * Build Template
 *
 * @param string|array $template_slug Template slug.
 * @param array $data Data.
 * @param array $options Options.
 * @return string|bool HTML or false.
 */
function wh_get_template($template_slug, $data=null, $options=null) {
	return false;
}

/**
 * Format (Single) Recipient
 *
 * @param string $email Email.
 * @param string $name Name.
 * @return string|bool Recipient or false.
 */
function wh_recipient($email, $name='') {
	$email = sanitize_email($email);
	return $email ? $email : false;
}

// --------------------------------------------------------------------- end functions
