<?php
/**
 * Well-Handled uninstall.
 *
 * @package Well-Handled
 * @author  Blobfolio, LLC <hello@blobfolio.com>
 */

/**
 * Do not execute this file directly.
 */
if (! defined('WP_UNINSTALL_PLUGIN')) {
	exit;
}

// Are we killing everything? There can be a lot of data, so we don't
// want to delete it all unless the user said to.
$nuclear = true;
if (false !== $options = get_option('wh_options', false)) {
	if (is_array($options) && array_key_exists('nuclear', $options)) {
		$nuclear = !! $options['nuclear'];
	}
}

if ($nuclear) {
	// Options to get rid of.
	$options = array(
		'wh_cron_send_queue_lock',
		'wh_db_version',
		'wh_options',
		'wh_reload_capabilities',
		'wh_remote_sync',
	);
	foreach ($options as $o) {
		delete_option($o);
	}

	// And the data tables.
	global $wpdb;
	$tables = array(
		"{$wpdb->prefix}wh_messages",
		"{$wpdb->prefix}wh_message_links",
		"{$wpdb->prefix}wh_message_errors",
		"{$wpdb->prefix}wh_message_queue",
	);
	foreach ($tables as $t) {
		$wpdb->query("DROP TABLE IF EXISTS `$t`");
	}

	// Move templates to the trash.
	$posts = get_posts(
		array(
			'post_type'=>'wh-template',
			'post_status'=>'any',
			'numberposts'=>-1,
		)
	);
	if (! empty($posts) && is_array($posts)) {
		foreach ($posts as $p) {
			if ('trash' !== $p->post_status) {
				wp_trash_post($p->ID);
			}
		}
	}
}

return true;

