<?php
/**
 * Well-Handled options.
 *
 * An options wrapper.
 *
 * @package Well-Handled
 * @author  Blobfolio, LLC <hello@blobfolio.com>
 */

namespace blobfolio\wp\wh;

use blobfolio\wp\wh\vendor\common;

class options {
	// Main options.
	const OPTION_NAME = 'wh_options';
	const OPTIONS = array(
		'nuclear'=>false,
		'reload_capabilities'=>false,
		'roles'=>array(),
		'send'=>array(
			'method'=>'wp_mail',
			'mandrill'=>array(
				'key'=>'',
				'email'=>'',
				'name'=>'',
			),
			'smtp'=>array(
				'email'=>'',
				'name'=>'',
				'user'=>'',
				'pass'=>'',
				'server'=>'',
				'port'=>25,
				'encryption'=>'none',
			),
			'ses'=>array(
				'endpoint'=>'email.us-east-1.amazonaws.com',
				'access_key'=>'',
				'secret_key'=>'',
				'email'=>'',
				'name'=>'',
			),
		),
		'send_data'=>array(
			'method'=>'none',
			'clicks'=>false,
			'errors'=>false,
			'retention'=>0,
		),
		'send_queue'=>array(
			'enabled'=>false,
			'frequency'=>10,
			'qty'=>5,
		),
	);

	// Editor themes.
	const THEMES = array(
		'3024-day',
		'3024-night',
		'abcdef',
		'ambiance',
		'ambiance-mobile',
		'base16-dark',
		'base16-light',
		'bespin',
		'blackboard',
		'cobalt',
		'colorforth',
		'dracula',
		'eclipse',
		'elegant',
		'erlang-dark',
		'hopscotch',
		'icecoder',
		'isotope',
		'lesser-dark',
		'liquibyte',
		'material',
		'mbo',
		'mdn-like',
		'midnight',
		'monokai',
		'neat',
		'neo',
		'night',
		'paraiso-dark',
		'paraiso-light',
		'pastel-on-dark',
		'railscasts',
		'rubyblue',
		'seti',
		'solarized',
		'the-matrix',
		'tomorrow-night-bright',
		'tomorrow-night-eighties',
		'ttcn',
		'twilight',
		'vibrant-ink',
		'xq-dark',
		'xq-light',
		'yeti',
		'zenburn',
	);

	const SEND_METHODS = array('mandrill', 'ses', 'smtp', 'wp_mail');

	const SMTP_PORTS = array(25, 465, 587, 2525, 2526);
	const SMTP_ENCRYPTION = array('none', 'SSL', 'TLS');

	const SES_ENDPOINTS = array(
		'email.us-east-1.amazonaws.com'=>'US East (N. Virginia)',
		'email.us-west-2.amazonaws.com'=>'US West (Oregon)',
		'email.eu-west-1.amazonaws.com'=>'EU (Ireland)',
	);

	const DATA_METHODS = array('none', 'meta', 'full');

	const QUEUE_FREQUENCIES = array(
		1=>'Every minute',
		5=>'Every 5 minutes',
		10=>'Every 10 minutes',
		30=>'Every half-hour',
		60=>'Every hour',
	);

	protected static $options;
	protected static $editor_theme;
	protected static $roles;
	protected static $has;



	/**
	 * Load Options
	 *
	 * @since 2.0.0
	 *
	 * @param bool $refresh Refresh.
	 * @return bool True/false.
	 */
	protected static function load($refresh=false) {
		if ($refresh || null === static::$options) {
			// Nothing saved yet? Or maybe an older version?
			if (false === (static::$options = \get_option(static::OPTION_NAME, false))) {
				static::$options = static::OPTIONS;
				foreach (static::$options as $k=>$v) {
					if ('notfound' !== ($option = \get_option("wh_$k", 'notfound'))) {
						static::$options[$k] = $option;
						\delete_option("wh_$k");
					}
				}
				static::$options = common\data::parse_args(static::$options, static::OPTIONS);
				\update_option(static::OPTION_NAME, static::$options);
			}

			// Before.
			$before = \json_encode(static::$options);

			// Sanitize them.
			static::sanitize(static::$options, true);

			// After.
			$after = \json_encode(static::$options);
			if ($before !== $after) {
				\update_option(static::OPTION_NAME, static::$options);
			}
		}

		return true;
	}

	/**
	 * Sanitize Options
	 *
	 * @since 2.0.0
	 *
	 * @param array $options Options.
	 * @return bool True/false.
	 */
	protected static function sanitize(&$options) {
		// Can fix most issues en masse.
		$options = common\data::parse_args($options, static::OPTIONS);

		// Send method.
		if (! \in_array($options['send']['method'], static::SEND_METHODS, true)) {
			$options['send']['method'] = static::OPTIONS['send']['method'];
		}

		// Mandrill.
		common\ref\sanitize::whitespace($options['send']['mandrill']['key']);
		common\ref\sanitize::email($options['send']['mandrill']['email']);

		// SMTP.
		common\ref\sanitize::email($options['send']['smtp']['email']);
		if (! \in_array($options['send']['smtp']['port'], static::SMTP_PORTS, true)) {
			$options['send']['smtp']['port'] = static::OPTIONS['send']['smtp']['port'];
		}
		if (! \in_array($options['send']['smtp']['encryption'], static::SMTP_ENCRYPTION, true)) {
			$options['send']['smtp']['encryption'] = static::OPTIONS['send']['smtp']['encryption'];
		}

		// SES.
		common\ref\sanitize::email($options['send']['ses']['email']);
		if (! \array_key_exists($options['send']['ses']['endpoint'], static::SES_ENDPOINTS)) {
			$options['send']['ses']['endpoint'] = static::OPTIONS['send']['ses']['endpoint'];
		}

		// CRON sending.
		if (! $options['send_queue']['enabled']) {
			$options['send_queue'] = static::OPTIONS['send_queue'];
		}
		else {
			common\ref\sanitize::to_range($options['send_queue']['qty'], 1, 100);
			if (! \array_key_exists($options['send_queue']['frequency'], static::QUEUE_FREQUENCIES)) {
				$options['send_queue']['frequency'] = static::OPTIONS['send_queue']['frequency'];
			}
		}

		// Data.
		if (! \in_array($options['send_data']['method'], static::DATA_METHODS, true)) {
			$options['send_data'] = static::OPTIONS['send_data'];
		}
		else {
			common\ref\sanitize::to_range($options['send_data']['retention'], 0);
		}

		// Roles.
		$default_roles = static::get_default_roles();
		$options['roles'] = common\data::parse_args($options['roles'], $default_roles);

		return true;
	}

	/**
	 * Get Option
	 *
	 * @since 2.0.0
	 *
	 * @param string $key Key.
	 * @return mixed Value or false.
	 */
	public static function get($key=null) {
		static::load();

		// Return everything?
		if (null === $key) {
			return static::$options;
		}

		common\ref\cast::to_string($key, true);

		// Editor theme is weird.
		if ('wh_editor_theme' === $key || 'editor_theme' === $key) {
			return static::get_editor_theme();
		}
		// Everything else...
		elseif (! \array_key_exists($key, static::$options)) {
			return false;
		}

		return static::$options[$key];
	}

	/**
	 * Save Option
	 *
	 * @since 2.0.0
	 *
	 * @param string $key Key.
	 * @param mixed $value Value.
	 * @param bool $force Force resaving.
	 * @return bool True/false.
	 */
	public static function save($key, $value, $force=false) {
		static::load();
		common\ref\cast::to_string($key, true);

		// Editor theme is weird.
		if ('wh_editor_theme' === $key || 'editor_theme' === $key) {
			return static::save_editor_theme($value);
		}
		// Everything else...
		elseif (! \array_key_exists($key, static::$options)) {
			return false;
		}

		// No change?
		if (! $force && static::$options[$key] === $value) {
			return true;
		}

		$original = static::$options[$key];

		static::$options[$key] = $value;
		\update_option(static::OPTION_NAME, static::$options);
		static::load(true);

		// Might need to schedule a privilege reload.
		if (('roles' === $key) && (static::$options[$key] !== $original)) {
			static::save('reload_capabilities', true);
		}

		return true;
	}

	/**
	 * Get Editor Theme
	 *
	 * This is user-specific.
	 *
	 * @since 2.0.0
	 *
	 * @return string Theme.
	 */
	public static function get_editor_theme() {
		if (null === static::$editor_theme) {
			static::$editor_theme = 'ambiance';
			if (false !== ($current_user = \wp_get_current_user())) {
				$theme = \get_user_meta($current_user->ID, 'wh_editor_theme', true);
				if ($theme && \in_array($theme, static::THEMES, true)) {
					static::$editor_theme = $theme;
				}
			}
		}

		return static::$editor_theme;
	}

	/**
	 * Save Editor Theme
	 *
	 * This is user-specific.
	 *
	 * @since 2.0.0
	 *
	 * @param string $theme Theme.
	 * @return bool True/false.
	 */
	public static function save_editor_theme($theme) {
		common\ref\cast::to_string($theme, true);
		if (false !== ($current_user = \wp_get_current_user())) {
			if ($theme && \in_array($theme, static::THEMES, true)) {
				static::$editor_theme = $theme;
				\update_user_meta($current_user->ID, 'wh_editor_theme', $theme);
				return true;
			}
		}

		return false;
	}

	/**
	 * Get Default Roles
	 *
	 * @since 2.0.0
	 *
	 * @param bool $refresh Refresh.
	 * @return array Roles.
	 */
	public static function get_default_roles($refresh=false) {
		if ($refresh || null === static::$roles) {
			static::$roles = array();
			$all = custom::get_roles();
			foreach ($all as $v) {
				static::$roles[$v] = array(
					'content'=>('administrator' === $v),
					'stats'=>('administrator' === $v),
				);
			}
		}

		return static::$roles;
	}

	/**
	 * Has XXX
	 *
	 * This is options-adjacent: what kind of data is
	 * currently being stored? There are a few places
	 * that ask this, so we can do it once here and
	 * be done with it.
	 *
	 * @since 2.0.0
	 *
	 * @param string $key Key.
	 * @param bool $refresh Refresh.
	 * @return mixed Value or false.
	 */
	public static function has($key, $refresh=false) {
		if ($refresh || null === static::$has) {
			global $wpdb;

			static::$has = array(
				'content'=>false,
				'errors'=>false,
				'links'=>false,
				'messages'=>false,
				'queue'=>false,
			);

			if ('nothing' !== \get_option('wh_db_version', 'nothing')) {

				static::$has['errors'] = null !== $wpdb->get_var("
					SELECT `id`
					FROM `{$wpdb->prefix}wh_message_errors`
					LIMIT 1
				");
				static::$has['messages'] = null !== $wpdb->get_var("
					SELECT `id`
					FROM `{$wpdb->prefix}wh_messages`
					LIMIT 1
				");
				static::$has['queue'] = null !== $wpdb->get_var("
					SELECT `id`
					FROM `{$wpdb->prefix}wh_message_queue`
					LIMIT 1
				");

				// Some of these only exist if there are messages...
				if (static::$has['messages']) {
					static::$has['content'] = null !== $wpdb->get_var("
						SELECT `id`
						FROM `{$wpdb->prefix}wh_messages`
						WHERE LENGTH(`message`)
						LIMIT 1
					");
					static::$has['links'] = null !== $wpdb->get_var("
						SELECT `mask`
						FROM `{$wpdb->prefix}wh_message_links`
						LIMIT 1
					");
				}
			}
		}

		return \array_key_exists($key, static::$has) ? static::$has[$key] : false;
	}
}
