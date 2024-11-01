<?php
/**
 * Well-Handled database.
 *
 * This class manages the database extensions.
 *
 * @package Well-Handled
 * @author  Blobfolio, LLC <hello@blobfolio.com>
 */

namespace blobfolio\wp\wh;

class db {
	const VERSION = '0.4.5';

	// History of sent messages.
	const SCHEMA_MESSAGES = "CREATE TABLE %PREFIX%wh_messages (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  mask char(20) NOT NULL DEFAULT '',
  date_created timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  name varchar(100) NOT NULL DEFAULT '',
  email varchar(100) NOT NULL DEFAULT '',
  subject varchar(100) NOT NULL DEFAULT '',
  template varchar(100) NOT NULL DEFAULT '',
  opened tinyint(1) unsigned NOT NULL DEFAULT '0',
  clicks smallint(5) unsigned NOT NULL DEFAULT '0',
  message text NOT NULL,
  method enum('wp_mail','smtp','ses','mandrill') NOT NULL DEFAULT 'wp_mail',
  compilation_time float unsigned NOT NULL DEFAULT '0',
  execution_time float unsigned NOT NULL DEFAULT '0',
  template_data text NOT NULL,
  template_options text NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY mask (mask),
  KEY date_created (date_created),
  KEY email (email),
  KEY subject (subject),
  KEY template (template),
  KEY opened (opened),
  KEY clicks (clicks),
  KEY method (method)
) %CHARSET%";

	// Redirect links for click tracking.
	const SCHEMA_MESSAGE_LINKS = "CREATE TABLE %PREFIX%wh_message_links (
  mask char(20) NOT NULL,
  message_id bigint(20) unsigned NOT NULL DEFAULT '0',
  url text NOT NULL,
  clicks smallint(5) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY  (mask),
  KEY message_id (message_id),
  KEY clicks (clicks)
) %CHARSET%";

	// Send errors.
	const SCHEMA_MESSAGE_ERRORS = 'CREATE TABLE %PREFIX%wh_message_errors (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  date_created timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  mail text NOT NULL,
  template text NOT NULL,
  error text NOT NULL,
  PRIMARY KEY  (id),
  KEY date_created (date_created)
) %CHARSET%';

	// When sending via CRON, messages go here.
	const SCHEMA_MESSAGE_QUEUE = 'CREATE TABLE %PREFIX%wh_message_queue (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  date_scheduled timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  data text NOT NULL,
  PRIMARY KEY  (id),
  KEY date_scheduled (date_scheduled)
) %CHARSET%';



	/**
	 * Check if DB upgrade is needed.
	 *
	 * @since 2.0.0
	 *
	 * @return bool True/false.
	 */
	public static function check() {
		// Don't let willynilly traffic trigger this.
		if (
			! \is_admin() &&
			(! \defined('WP_CLI') || ! \WP_CLI)
		) {
			return false;
		}

		$installed = (string) \get_option('wh_db_version', '0');
		if (! \preg_match('/^\d+(\.\d+)*$/', $installed)) {
			$installed = 0;
		}

		if (\version_compare($installed, static::VERSION) < 0) {
			return static::upgrade($installed);
		}

		return true;
	}

	/**
	 * Do Upgrade
	 *
	 * @since 2.0.0
	 *
	 * @param string $version Installed version.
	 * @return bool True/false.
	 */
	public static function upgrade($version=null) {
		global $wpdb;
		require_once \ABSPATH . 'wp-admin/includes/upgrade.php';

		// WordPress might get called multiple times while this is
		// running. Let's go ahead and update the version string
		// early to mitigate parallel runs.
		\update_option('wh_db_version', static::VERSION);

		// Pre-dbDelta operations.
		if (\is_string($version) && $version && \version_compare($version, '0.4.2') < 0) {
			// Update the main messages table. dbDelta could handle some of this,
			// but is painfully slow. If this query fails, dbDelta should pick up
			// the changes itself anyway.
			if (null !== $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}wh_messages'")) {
				$wpdb->query("
					ALTER TABLE `{$wpdb->prefix}wh_messages`
					DROP COLUMN `date_created_gmt`,
					MODIFY COLUMN `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
					MODIFY COLUMN `opened` tinyint(1) unsigned NOT NULL DEFAULT '0',
					MODIFY COLUMN `clicks` smallint(5) unsigned NOT NULL DEFAULT '0',
					MODIFY COLUMN `compilation_time` float unsigned NOT NULL DEFAULT '0',
					MODIFY COLUMN `execution_time` float unsigned NOT NULL DEFAULT '0',
					MODIFY COLUMN `date_created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
				");
			}

			// Update the links table.
			if (null !== $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}wh_message_links'")) {
				$wpdb->query("
					ALTER TABLE `{$wpdb->prefix}wh_message_links`
					MODIFY COLUMN `message_id` bigint(20) unsigned NOT NULL DEFAULT '0',
					MODIFY COLUMN `clicks` smallint(5) unsigned NOT NULL DEFAULT '0'
				");
			}

			// Reset the errors table to avoid potential parsing conflicts.
			$wpdb->query("DROP TABLE IF EXISTS `{$wpdb->prefix}wh_message_errors`");
		}

		$tables = array(
			static::SCHEMA_MESSAGES,
			static::SCHEMA_MESSAGE_LINKS,
			static::SCHEMA_MESSAGE_ERRORS,
			static::SCHEMA_MESSAGE_QUEUE,
		);

		$replace = array(
			'%PREFIX%'=>$wpdb->prefix,
			'%CHARSET%'=>$wpdb->get_charset_collate(),
		);

		foreach ($tables as $k=>$v) {
			$tables[$k] = \str_replace(\array_keys($replace), \array_values($replace), $v);
		}
		\dbDelta($tables);

		// Remove old CRON triggers.
		if (false !== ($timestamp = \wp_next_scheduled('wh_cron_check_license'))) {
			\wp_unschedule_event($timestamp, 'wh_cron_check_license');
		}

		return true;
	}
}
