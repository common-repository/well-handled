<?php
/**
 * Well-Handled - Bootstrap
 *
 * Set up the environment.
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



// Bootstrap.
require WH_BASE . 'lib/autoload.php';

// So many actions!
add_action('add_meta_boxes', array(WH_BASE_CLASS . 'admin', 'add_meta_boxes'));
add_action('admin_enqueue_scripts', array(WH_BASE_CLASS . 'admin', 'enqueue_scripts'));
add_action('admin_head', array(WH_BASE_CLASS . 'admin', 'post_vue_data'));
add_action('admin_init', array(WH_BASE_CLASS . 'admin', 'privacy_policy'));
add_action('admin_notices', array(WH_BASE_CLASS . 'admin', 'warnings'));
add_action('admin_notices', array(WH_BASE_CLASS . 'admin', 'post_notices'));
add_action('admin_notices', array(WH_BASE_CLASS . 'admin', 'error_notice'));
add_action('all_admin_notices', array(WH_BASE_CLASS . 'admin', 'preview_modal'));
add_action('init', array(WH_BASE_CLASS . 'admin', 'server_name'));
add_action('init', array(WH_BASE_CLASS . 'cron', 'register_actions'));
add_action('init', array(WH_BASE_CLASS . 'custom', 'check_capabilities'), 15, 0);
add_action('init', array(WH_BASE_CLASS . 'custom', 'register_post_types'));
add_action('init', array(WH_BASE_CLASS . 'custom', 'register_taxonomies'), 1, 0);
add_action('parse_request', array(WH_BASE_CLASS . 'admin', 'rewrite_parse_request'));
add_action('plugins_loaded', array(WH_BASE_CLASS . 'db', 'check'));
add_action('query_vars', array(WH_BASE_CLASS . 'admin', 'rewrite_query_vars'));
add_action('save_post_wh-template', array(WH_BASE_CLASS . 'admin', 'save_post'));

// And filters!
add_filter('cron_schedules', array(WH_BASE_CLASS . 'cron', 'schedules'));
add_filter('map_meta_cap', array(WH_BASE_CLASS . 'custom', 'map_capabilities'), 10, 4);
add_filter('wh_mail_recipient_email', array(WH_BASE_CLASS . 'message', 'format_recipient_email'), 5, 1);
add_filter('wh_mail_recipient_name', array(WH_BASE_CLASS . 'message', 'format_recipient_name'), 5, 1);

// Not many shortcodes.
add_shortcode('wh-fragment', array(WH_BASE_CLASS . 'template', 'make_fragment'));

// Life and death.
register_activation_hook(WH_INDEX, array(WH_BASE_CLASS . 'db', 'check'));
register_deactivation_hook(WH_INDEX, array(WH_BASE_CLASS . 'cron', 'unregister_actions'), 1, 0);

// And lastly, a few grouped items.
\blobfolio\wp\wh\admin::register_menus();
\blobfolio\wp\wh\ajax::register_actions();

// --------------------------------------------------------------------- end setup



// ---------------------------------------------------------------------
// User Functions
// ---------------------------------------------------------------------

// These functions existed in an earlier version of the plugin and so
// are retained here for compatibility reasons.

/**
 * Mail Template
 *
 * @param string|array $template_slug Template slug.
 * @param array $data Data.
 * @param array $options Options.
 * @return bool True/false.
 */
function wh_mail_template($template_slug, $data=null, $options=null) {
	return \blobfolio\wp\wh\message::send($template_slug, $data, $options);
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
	return \blobfolio\wp\wh\message::send_now($to, $subject, $message, $headers, $attachments, $testmode);
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
	$template = new \blobfolio\wp\wh\template($template_slug);
	if (false !== ($build = $template->make($data, $options))) {
		if (isset($build['content'])) {
			return $build['content'];
		}
	}

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
	return \blobfolio\wp\wh\message::format_recipient($email, $name);
}

// --------------------------------------------------------------------- end functions
