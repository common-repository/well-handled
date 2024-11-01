<?php
/**
 * Easy and powerful handlebar/mustache email template management for developers.
 *
 * @package Well-Handled Email Templates
 * @version 2.4.4
 *
 * @wordpress-plugin
 * Plugin Name: Well-Handled Email Templates
 * Version: 2.4.4
 * Plugin URI: https://wordpress.org/plugins/well-handled/
 * Description: Easy and powerful handlebar/mustache email template management for developers.
 * Text Domain: well-handled
 * Domain Path: /languages/
 * Author: Blobfolio, LLC
 * Author URI: https://blobfolio.com/
 * License: GPLv2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

/**
 * Do not execute this file directly.
 */
if (! defined('ABSPATH')) {
	exit;
}

// ---------------------------------------------------------------------
// Setup
// ---------------------------------------------------------------------

// Constants.
define('WH_BASE', dirname(__FILE__) . '/');
define('WH_INDEX', __FILE__);
define('WH_PHP_MIN', '7.2.0');
define('WH_VERSION', '2.4.4');
define('WH_BASE_CLASS', 'blobfolio\\wp\\wh\\');
define('WH_URL', 'https://blobfolio.com/plugin/well-handled/');

// If the server doesn't meet the requirements, load the fallback
// instead.
if (
	version_compare(PHP_VERSION, WH_PHP_MIN) < 0 ||
	(function_exists('is_multisite') && is_multisite()) ||
	! class_exists('DOMDocument') ||
	! function_exists('libxml_disable_entity_loader') ||
	(! function_exists('hash_algos') || ! in_array('sha512', hash_algos(), true))
) {
	require WH_BASE . 'bootstrap-fallback.php';
	return;
}

// Otherwise we can continue as normal.
require WH_BASE . 'bootstrap.php';
