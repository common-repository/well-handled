<?php
/**
 * Well-Handled message.
 *
 * The goods are here.
 *
 * @package Well-Handled
 * @author  Blobfolio, LLC <hello@blobfolio.com>
 */

namespace blobfolio\wp\wh;

use blobfolio\wp\wh\vendor\common;
use blobfolio\wp\wh\vendor\mimes;

class message {

	const MESSAGE = array(
		'id'=>0,
		'mask'=>'',
		'date_created'=>'0000-00-00 00:00:00',
		'name'=>'',
		'email'=>'',
		'subject'=>'',
		'template'=>'',
		'opened'=>0,
		'clicks'=>0,
		'message'=>'',
		'method'=>'wp_mail',
		'compilation_time'=>0.0,
		'execution_time'=>0.0,
		'template_data'=>'',
		'template_options'=>'',
	);

	protected $message;
	protected $links;

	protected static $_instances;
	protected static $ses;
	protected static $_error;

	// ---------------------------------------------------------------------
	// Init
	// ---------------------------------------------------------------------

	/**
	 * Pre-Construct
	 *
	 * Cache static objects locally for better performance.
	 *
	 * @param string|int $message_id Message ID or mask.
	 * @param bool $refresh Refresh.
	 * @return object Instance.
	 */
	public static function get($message_id=null, $refresh=false) {
		// Figure out whether we're making a new instance or not.
		if (! isset(self::$_instances[static::class])) {
			self::$_instances[static::class] = array();
		}

		if (! \is_numeric($message_id)) {
			$message_id = static::get_message_id_from_mask($message_id);
		}

		common\ref\cast::to_int($message_id, true);
		common\ref\sanitize::to_range($message_id, 0);

		if (! $message_id) {
			return new static();
		}

		// Get the right object.
		if ($refresh || ! isset(self::$_instances[static::class][$message_id])) {
			self::$_instances[static::class][$message_id] = new static($message_id);
			if (! self::$_instances[static::class][$message_id]->is_message()) {
				unset(self::$_instances[static::class][$message_id]);
				return new static();
			}
		}

		return self::$_instances[static::class][$message_id];
	}

	/**
	 * Constructor
	 *
	 * @param string|int $message_id Message ID or mask.
	 * @return bool true/false.
	 */
	public function __construct($message_id=null) {
		global $wpdb;

		$this->message = static::MESSAGE;
		$this->links = null;

		if (! \is_numeric($message_id)) {
			$message_id = static::get_message_id_from_mask($message_id);
		}
		common\ref\cast::to_int($message_id, true);
		common\ref\sanitize::to_range($message_id, 0);

		if (! $message_id) {
			return false;
		}

		// And get the content.
		$dbResult = $wpdb->get_results("
			SELECT *
			FROM `{$wpdb->prefix}wh_messages`
			WHERE `id`=$message_id
		", \ARRAY_A);
		if (! isset($dbResult[0])) {
			return false;
		}
		$Row = common\data::array_pop_top($dbResult);
		$this->message = common\data::parse_args($Row, static::MESSAGE);

		return true;
	}

	/**
	 * Is Message?
	 *
	 * @return bool True/false
	 */
	public function is_message() {
		return \is_array($this->message) && $this->message['id'] > 0;
	}

	/**
	 * Get Message ID From Mask
	 *
	 * @param string $mask Mask.
	 * @return int|bool Message ID or false.
	 */
	public static function get_message_id_from_mask($mask) {
		global $wpdb;
		common\ref\cast::to_string($mask);
		$mask = \preg_replace('/[^A-Z0-9]/', '', $mask);

		if (\strlen($mask) === 20) {
			$message_id = (int) $wpdb->get_var("
				SELECT `id`
				FROM `{$wpdb->prefix}wh_messages`
				WHERE `mask`='$mask'
			");
			if ($message_id) {
				return $message_id;
			}
		}

		return false;
	}

	/**
	 * Magic Getter
	 *
	 * @param string $method Method name.
	 * @param mixed $args Arguments.
	 * @return mixed Variable.
	 * @throws \Exception Invalid method.
	 */
	public function __call($method, $args) {
		\preg_match_all('/^get_(.+)$/', $method, $matches);
		if (
			! empty($matches[0]) &&
			\array_key_exists($matches[1][0], static::MESSAGE)
		) {
			$variable = $matches[1][0];
			$value = $this->is_message() ? $this->message[$variable] : static::MESSAGE[$variable];

			// Dates.
			if (0 === \strpos($variable, 'date')) {
				if (! empty($args) && \is_array($args)) {
					$args = common\data::array_pop_top($args);
					common\ref\cast::to_string($args, true);
				}
				else {
					$args = 'Y-m-d H:i:s';
				}
				return \date($args, \strtotime($value));
			}

			// Everything else.
			return $value;
		}

		throw new \Exception(\sprintf(\__('The required method "%s" does not exist for %s', 'well-handled'), $method, static::class));
	}

	/**
	 * Get Links
	 *
	 * @param bool $refresh Refresh.
	 * @return array Links.
	 */
	public function get_links($refresh=false) {
		if (! $this->is_message()) {
			return array();
		}

		if ($refresh || null === $this->links) {
			$this->links = array();
			global $wpdb;
			$dbResult = $wpdb->get_results("
				SELECT `mask`
				FROM `{$wpdb->prefix}wh_message_links`
				WHERE `message_id`={$this->message['id']}
				ORDER BY `url` ASC
			", \ARRAY_A);
			if (isset($dbResult[0])) {
				foreach ($dbResult as $Row) {
					$this->links[] = new message\link($Row['mask']);
				}
			}
		}

		return $this->links;
	}

	// --------------------------------------------------------------------- end init



	// ---------------------------------------------------------------------
	// Recipients
	// ---------------------------------------------------------------------

	/**
	 * Format Recipient Email
	 *
	 * @param string $email Email.
	 * @return string Email.
	 */
	public static function format_recipient_email($email) {
		common\ref\cast::to_string($email, true);
		common\ref\sanitize::email($email);
		return $email;
	}

	/**
	 * Format Recipient Name
	 *
	 * @param string $name Name.
	 * @return string Name.
	 */
	public static function format_recipient_name($name) {
		common\ref\cast::to_string($name, true);
		common\ref\sanitize::quotes($name);
		common\ref\sanitize::whitespace($name);
		common\ref\sanitize::printable($name);
		$name = \str_replace(array('"', '<', '>'), '', $name);

		return $name;
	}

	/**
	 * Format Recipient
	 *
	 * @param string $email Email.
	 * @param string $name Name.
	 * @return string|bool Recipient or false.
	 */
	public static function format_recipient($email, $name='') {
		$email = \apply_filters('wh_mail_recipient_email', $email);
		$name = \apply_filters('wh_mail_recipient_name', $name);

		if (! $email) {
			return false;
		}

		if ($name) {
			return '"' . $name . '" <' . $email . '>';
		}

		return $email;
	}

	/**
	 * Parse Recipient
	 *
	 * @param string $recipient Recipient.
	 * @return array|bool Recipient or false.
	 */
	public static function parse_recipient($recipient) {
		common\ref\cast::to_string($recipient, true);
		common\ref\sanitize::quotes($recipient);
		common\ref\sanitize::whitespace($recipient);
		common\ref\sanitize::printable($recipient);

		$parsed = array(
			'name'=>'',
			'email'=>'',
		);

		// Try regular expression first to deal with Unicode hosts.
		if (\preg_match('/(.*)<(.+)>/u', $recipient, $matches)) {
			if (\count($matches) === 3) {
				$parsed['name'] = \apply_filters('wh_mail_recipient_name', $matches[1]);
				$parsed['email'] = \apply_filters('wh_mail_recipient_email', $matches[2]);
			}
		}

		// Maybe it is just an email?
		if (! $parsed['email']) {
			$parsed['email'] = \apply_filters('wh_mail_recipient_email', $recipient);
		}

		// Try IMAP?
		if (! $parsed['email'] && \function_exists('imap_rfc822_parse_adrlist')) {
			$tmp = \imap_rfc822_parse_adrlist($recipient, '');
			foreach ($tmp as $t) {
				if ('.SYNTAX-ERROR.' === $t->host) {
					continue;
				}

				$parsed['email'] = \apply_filters('wh_mail_recipient_email', "{$t->mailbox}@{$t->host}");
				if (\property_exists($t, 'personal')) {
					$parsed['name'] = \apply_filters('wh_mail_recipient_name', $t->personal);
				}
			}
		}

		return $parsed['email'] ? $parsed : false;
	}

	// --------------------------------------------------------------------- end recipients



	// ---------------------------------------------------------------------
	// Save
	// ---------------------------------------------------------------------

	/**
	 * Generate Message Mask
	 *
	 * @return string Mask.
	 */
	public static function message_mask() {
		global $wpdb;
		$mask = common\data::random_string(20);
		while (\intval($wpdb->get_var("
			SELECT COUNT(*)
			FROM `{$wpdb->prefix}wh_messages`
			WHERE `mask`='$mask'
		"))) {
			$mask = common\data::random_string(20);
		}

		return $mask;
	}

	/**
	 * Save
	 *
	 * @param array $args Arguments.
	 *
	 * @arg string $name Name.
	 * @arg string $email Email.
	 * @arg string $subject Subject.
	 * @arg string $template Template slug.
	 * @arg int $opened Opened 1/0.
	 * @arg int $clicks Number of clicks.
	 * @arg string $message Message.
	 * @arg string $method Send method.
	 * @arg float $compilation_time Compilation time.
	 * @arg float $execution_time Send time.
	 * @arg string $template_data Build data (JSON).
	 * @arg string $template_options Build options (JSON).
	 *
	 * @return bool True/false.
	 */
	public function save($args=null) {
		global $wpdb;

		common\ref\cast::to_array($args);
		if (! \is_array($args)) {
			return false;
		}

		// Our options and data should be JSON.
		foreach (array('template_data', 'template_options') as $field) {
			if (isset($args[$field])) {
				if (\is_array($args[$field])) {
					$args[$field] = \json_encode($args[$field]);
				}
				elseif (! common\data::is_json($args[$field])) {
					unset($args[$field]);
				}
			}
		}

		// This gets generated automatically.
		if (isset($args['mask'])) {
			unset($args['mask']);
		}

		// Start with what we know.
		if ($this->is_message()) {
			$defaults = $this->message;
		}
		else {
			$defaults = static::MESSAGE;
			$defaults['mask'] = static::message_mask();
		}
		// Minus a few fully automatic pieces.
		unset($defaults['id']);
		unset($defaults['date_created']);

		$data = common\data::parse_args($args, $defaults);

		// Fix the numbers.
		common\ref\sanitize::to_range($data['opened'], 0, 1);
		common\ref\sanitize::to_range($data['clicks'], 0);
		common\ref\sanitize::to_range($data['execution_time'], 0.0);
		common\ref\sanitize::to_range($data['compilation_time'], 0.0);

		// Fix recipient.
		$data['email'] = \apply_filters('wh_mail_recipient_email', $data['email']);
		$data['name'] = \apply_filters('wh_mail_recipient_name', $data['name']);

		// Generic one-liners.
		common\ref\sanitize::whitespace($data['subject']);
		common\ref\sanitize::printable($data['subject']);
		common\ref\sanitize::whitespace($data['template']);
		common\ref\sanitize::printable($data['template']);

		if (! \in_array($data['method'], options::SEND_METHODS, true)) {
			$data['method'] = options::OPTIONS['send']['method'];
		}

		// Chop? These are generous limits, but still, don't want to fail.
		$lengths = array(
			'name'=>100,
			'email'=>100,
			'subject'=>100,
			'template'=>200,
		);
		foreach ($lengths as $field=>$length) {
			if (common\mb::strlen($data[$field]) > $length) {
				$data[$field] = common\mb::substr($data[$field], 0, $length);
			}
		}

		// Okedoke, save it!
		$format = array(
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
			'%d',
			'%d',
			'%s',
			'%s',
			'%f',
			'%f',
			'%s',
			'%s',
		);

		// New.
		if (! $this->is_message()) {
			if (false === $wpdb->insert(
				"{$wpdb->prefix}wh_messages",
				$data,
				$format
			)) {
				return false;
			}
			$message_id = (int) $wpdb->insert_id;
		}
		// Existing.
		else {
			$message_id = $this->message['id'];
			if (false === $wpdb->update(
				"{$wpdb->prefix}wh_messages",
				$data,
				array('id'=>$message_id),
				$format,
				'%d'
			)) {
				return false;
			}
		}

		// Repopulate the object.
		return $this->__construct($message_id);
	}

	/**
	 * Recount Link Clicks
	 *
	 * @return bool True/false.
	 */
	public function recount() {
		if (! $this->is_message()) {
			return false;
		}

		$total = 0;
		$this->links = null;
		$this->get_links();
		foreach ($this->links as $link) {
			$total += $link->get_clicks();
		}

		if ($total !== $this->message['clicks']) {
			$this->save(array('clicks'=>$total, 'opened'=>1));
		}

		return true;
	}

	/**
	 * Delete
	 *
	 * @return bool True/false.
	 */
	public function delete() {
		if (! $this->is_message()) {
			return false;
		}

		global $wpdb;
		$message_id = $this->message['id'];
		$wpdb->delete(
			"{$wpdb->prefix}wh_messages",
			array('id'=>$message_id),
			'%d'
		);
		$wpdb->delete(
			"{$wpdb->prefix}wh_message_links",
			array('message_id'=>$message_id),
			'%d'
		);
		$this->message = static::MESSAGE;

		return true;
	}

	// --------------------------------------------------------------------- end save



	// ---------------------------------------------------------------------
	// Send
	// ---------------------------------------------------------------------

	/**
	 * Send
	 *
	 * Technically more of a pre-send; it validates the message data
	 * and either passes it off to the mail handler or queues it for
	 * later.
	 *
	 * @param array|string $template_slug Template slug.
	 * @param array $data Data.
	 * @param array $options Options.
	 * @param mixed $when When to send? Without Cron sending, default is now, otherwise default is next cronjob.
	 * @return bool True/false.
	 */
	public static function send($template_slug, $data=null, $options=null, $when=false) {
		$template = new template($template_slug);
		if (! $template->is_template()) {
			return false;
		}

		$title = $template->get_title();
		$template_slug = $template->get_slug();

		common\ref\cast::to_array($options);
		// "TO" might be a comma-delimited string, but a valid line might
		// also contain commas. If it validates, we'll assume it is one,
		// otherwise it might be multiple.
		if (isset($options['to']) && \is_string($options['to'])) {
			if (false === static::parse_recipient($options['to'])) {
				$options['to'] = \explode(',', $options['to']);
			}
		}

		// Sort out mail-related options first.
		$defaults = array(
			'to'=>array(),
			'subject'=>\sprintf(
				'[%s] %s',
				common\format::decode_entities(\get_bloginfo('name')),
				$title
			),
			'headers'=>null,
			'attachments'=>null,
			'testmode'=>false,
		);
		$mail_options = common\data::parse_args($options, $defaults);

		// Validate the recipient(s).
		foreach ($mail_options['to'] as $k=>$v) {
			if (false === static::parse_recipient($v)) {
				unset($mail_options['to']);
			}
		}
		if (empty($mail_options['to'])) {
			return false;
		}

		// Make sure the message is buildable.
		if (false === ($build = $template->make($data, $options))) {
			return false;
		}
		else {
			$body = $build['content'];
			unset($build['content']);
		}

		// Check out the headers.
		if (\is_string($mail_options['headers'])) {
			common\ref\sanitize::whitespace($mail_options['headers'], 1);
			$mail_options['headers'] = \explode("\n", $mail_options['headers']);
		}
		else {
			common\ref\cast::to_array($mail_options['headers']);
		}
		foreach ($mail_options['headers'] as $k=>$v) {
			common\ref\cast::to_string($mail_options['headers'][$k], true);
		}
		$mail_options['headers'] = \array_unique($mail_options['headers']);
		\sort($mail_options['headers']);

		// And attachments.
		if (\is_string($mail_options['attachments'])) {
			$mail_options['attachments'] = \explode(',', $mail_options['attachments']);
		}
		else {
			common\ref\cast::to_array($mail_options['attachments']);
		}
		foreach ($mail_options['attachments'] as $k=>$v) {
			common\ref\cast::to_string($mail_options['attachments'][$k], true);
			common\ref\file::path($mail_options['attachments'][$k]);
			if (false === $mail_options['attachments'][$k]) {
				unset($mail_options['attachments'][$k]);
			}
		}
		$mail_options['attachments'] = \array_unique($mail_options['attachments']);
		\sort($mail_options['attachments']);

		// Are we sending or scheduling?
		$now = true;
		$send = options::get('send');
		$send_queue = options::get('send_queue');
		if ($send_queue['enabled']) {
			// Send at the next cronjob.
			if (false === $when) {
				$now = false;
			}
			// Send some other time?
			elseif (true !== $when) {
				common\ref\sanitize::datetime($when);
				if ('0000-00-00 00:00:00' !== $when) {
					$now = false;
				}
			}
		}

		if ($now) {
			return static::send_now(
				$mail_options['to'],
				$mail_options['subject'],
				$body,
				$mail_options['headers'],
				$mail_options['attachments'],
				$mail_options['testmode'],
				$build
			);
		}

		return static::send_later(
			$mail_options['to'],
			$mail_options['subject'],
			$body,
			$mail_options['headers'],
			$mail_options['attachments'],
			$mail_options['testmode'],
			$build,
			$when
		);
	}

	/**
	 * Send Later
	 *
	 * Queue a message for later sending
	 *
	 * @param array $to Recipient(s).
	 * @param string $subject Subject.
	 * @param string $message Message.
	 * @param array $headers Headers.
	 * @param array $attachments Attachments.
	 * @param bool $testmode Testmode.
	 * @param array $build Build details.
	 * @param mixed $when When to send.
	 * @return bool True/false.
	 */
	protected static function send_later($to, $subject, $message, $headers=null, $attachments=null, $testmode=false, $build=null, $when=null) {
		global $wpdb;

		if (null === $when) {
			$when = \current_time('mysql');
		}
		common\ref\sanitize::datetime($when);
		if ('0000-00-00 00:00:00' === $when) {
			$when = \current_time('mysql');
		}

		// Store each separately to help maintain balanced processing.
		$recipients = $to;
		$saved = 0;
		foreach ($recipients as $to) {
			common\ref\cast::to_array($to);
			$data = \compact('to', 'subject', 'message', 'headers', 'attachments', 'testmode', 'build');
			$data = \json_encode($data);

			if (false !== $wpdb->insert(
				"{$wpdb->prefix}wh_message_queue",
				array(
					'data'=>$data,
					'date_scheduled'=>$when,
				),
				'%s'
			)) {
				++$saved;
			}
		}

		return $saved > 0;
	}

	/**
	 * Send Now
	 *
	 * Actually send the message out!
	 *
	 * @param array $to Recipient(s).
	 * @param string $subject Subject.
	 * @param string $message Message.
	 * @param array $headers Headers.
	 * @param array $attachments Attachments.
	 * @param bool $testmode Testmode.
	 * @param array $build Build details.
	 * @return bool True/false.
	 */
	public static function send_now($to, $subject, $message, $headers, $attachments, $testmode=false, $build=null) {

		$send = options::get('send');
		$send_data = options::get('send_data');

		// Override data tracking for testmode.
		if ($testmode) {
			$send_data = options::OPTIONS['send_data'];
		}

		// Some basic casting.
		common\ref\cast::to_array($to);
		common\ref\cast::to_string($subject, true);
		common\ref\cast::to_string($message, true);
		common\ref\cast::to_array($headers);
		common\ref\cast::to_array($attachments);

		// Give the people some filters.
		$to = \apply_filters('wh_mail_to', $to);
		$subject = \apply_filters('wh_mail_subject', $subject);
		$message = \apply_filters('wh_mail_message', $message);
		$headers = \apply_filters('wh_mail_headers', $headers);
		$attachments = \apply_filters('wh_mail_attachments', $attachments);

		// And once more after the filters, just in case.
		common\ref\cast::to_array($to);
		common\ref\cast::to_string($subject, true);
		common\ref\cast::to_string($message, true);
		common\ref\cast::to_array($headers);
		common\ref\cast::to_array($attachments);

		$defaults = array(
			'compilation_time'=>0.0,
			'template_slug'=>'',
			'template_data'=>'',
			'template_options'=>'',
		);
		$build = common\data::parse_args($build, $defaults);

		// Make sure the attachments are still valid.
		$tmp = $attachments;
		$attachments = array();
		foreach ($tmp as $k=>$v) {
			common\ref\cast::to_string($tmp[$k], true);
			common\ref\file::path($tmp[$k]);
			if (false === $tmp[$k]) {
				unset($tmp[$k]);
			}
		}
		$tmp = \array_unique($tmp);
		\sort($tmp);

		// Reformat attachments for SES/Mandrill sending, which cares more about file types.
		if (('ses' === $send['method']) || ('mandrill' === $send['method'])) {
			foreach ($tmp as $t) {
				$finfo = mimes\mimes::finfo($t);
				// Amazon does it one way...
				if ('ses' === $send['method']) {
					$attachment = array(
						'path'=>$finfo['path'],
						'name'=>$finfo['basename'],
						'type'=>$finfo['mime'],
					);
				}
				// Mandrill another.
				else {
					$attachment = array(
						'type'=>$finfo['mime'],
						'name'=>$finfo['basename'],
						'content'=>\base64_encode(@\file_get_contents($finfo['path'])),
					);
				}

				if (! empty($finfo['suggested_filename'])) {
					$attachment['name'] = common\data::array_pop_top($finfo['suggested_filename']);
				}
				$attachments[] = $attachment;
			}
		}
		// Otherwise our sorted array is fine.
		else {
			$attachments = $tmp;
		}

		// Add hooks.
		if (\in_array($send['method'], array('smtp', 'wp_mail'), true)) {
			\add_filter('wp_mail_content_type', array(static::class, 'mail_content_type'));
			if ('smtp' === $send['method']) {
				\add_filter('phpmailer_init', array(static::class, 'mail_smtp'));
			}
			\add_action('wp_mail_failed', array(static::class, 'wp_mail_failed'));
		}

		// Start sending!
		$sent = 0;
		foreach ($to as $recipient) {
			$start = \microtime(true);

			// Get the TO parts.
			if (false === ($tmp = static::parse_recipient($recipient))) {
				static::mail_failed(new \WP_Error('Send Failed', 'Invalid recipient.'));
				static::save_error(
					array(
						'to'=>$recipient,
						'subject'=>$subject,
						'message'=>$message,
						'headers'=>$headers,
						'attachments'=>$attachments,
					),
					$build
				);
				continue;
			}
			$recipient_name = $tmp['name'];
			$recipient_email = $tmp['email'];
			$recipient = static::format_recipient($tmp['email'], $tmp['name']);

			// Save the message.
			if ('none' !== $send_data['method']) {
				$message_object = new static();
				$data = array(
					'name'=>$recipient_name,
					'email'=>$recipient_email,
					'subject'=>$subject,
					'template'=>$build['template_slug'],
					'message'=>('full' === $send_data['method'] ? $message : ''),
					'method'=>$send['method'],
					'compilation_time'=>$build['compilation_time'],
					'template_data'=>('full' === $send_data['method'] ? $build['template_data'] : ''),
					'template_options'=>$build['template_options'],
				);
				$message_object->save($data);
				if (! $message_object->is_message()) {
					static::mail_failed(new \WP_Error('Send Failed', 'Could not save message.'));
					static::save_error(
						array(
							'to'=>$recipient,
							'subject'=>$subject,
							'message'=>$message,
							'headers'=>$headers,
							'attachments'=>$attachments,
						),
						$build
					);
					continue;
				}

				$message_id = $message_object->get_id();

				try {
					// Load up the DOM.
					\libxml_use_internal_errors(true);
					if (\PHP_VERSION_ID < 80000) {
						// phpcs:ignore
						\libxml_disable_entity_loader(true);
					}
					$dom = new \DOMDocument('1.0', 'UTF-8');
					$dom->loadHTML($message);

					// Click tracking.
					if ($send_data['clicks']) {
						$urls = array();

						$links = $dom->getElementsByTagName('a');
						if ($links->length) {
							for ($x = 0; $x < $links->length; ++$x) {
								$link = $links->item($x);
								$url = $link->getAttribute('href');
								if (\preg_match('/^https?:\/\//iu', $url)) {
									if (! \array_key_exists($url, $urls)) {
										$urls[$url] = message\link::link_mask();
									}
									$link->setAttribute('href', \site_url("?wh_link={$urls[$url]}"));
								}
							}
						}

						// Save the links.
						if (! empty($urls)) {
							if (false === message\link::bulk($message_id, $urls)) {
								static::mail_failed(new \WP_Error(
									\__('Send Failed', 'well-handled'),
									\__('Could not build links.', 'well-handled')
								));
								static::save_error(
									array(
										'to'=>$recipient,
										'subject'=>$subject,
										'message'=>$message,
										'headers'=>$headers,
										'attachments'=>$attachments,
									),
									$build
								);
								$message_object->delete();
								continue;
							}
						}
					}// End click tracking.

					// Open tracking.
					$img = $dom->createElement('img');
					$mask = $message_object->get_mask();
					$img->setAttribute('src', \site_url("?wh_image={$mask}.gif"));
					$img->setAttribute('width', 1);
					$img->setAttribute('height', 1);
					$img->setAttribute('class', 'wh-tracking-image');
					$dom->getElementsByTagName('body')->item(0)->appendChild($img);

					$message = $dom->saveHTML();
				} catch (\Throwable $e) {
					static::mail_failed(new \WP_Error(
						\__('Send Failed', 'well-handled'),
						\__('Could not build message', 'well-handled') . ': ' . $e->getMessage()
					));
					static::save_error(
						array(
							'to'=>$recipient,
							'subject'=>$subject,
							'message'=>$message,
							'headers'=>$headers,
							'attachments'=>$attachments,
						),
						$build
					);
					continue;
				}

				// And update, maybe.
				if ('full' === $send_data['method']) {
					$message_object->save(array('message'=>$message));
				}
			}// End save.

			// Send!
			if ('ses' === $send['method']) {
				$status = static::send_now_ses($recipient, $subject, $message, $headers, $attachments);
			}
			elseif ('mandrill' === $send['method']) {
				$status = static::send_now_mandrill($recipient, $subject, $message, $headers, $attachments);
			}
			else {
				$status = static::send_now_wp_mail($recipient, $subject, $message, $headers, $attachments);
			}

			// Sending failed.
			if (false === $status) {
				static::save_error(
					array(
						'to'=>$recipient,
						'subject'=>$subject,
						'message'=>$message,
						'headers'=>$headers,
						'attachments'=>$attachments,
					),
					$build
				);
				if ('none' !== $send_data['method']) {
					$message_object->delete();
				}
				continue;
			}

			$end = \microtime(true);
			if ('none' !== $send_data['method']) {
				$message_object->save(array('execution_time'=>($end - $start)));
			}
			++$sent;
		}

		// Clear hooks.
		if (\in_array($send['method'], array('smtp', 'wp_mail'), true)) {
			\remove_filter('wp_mail_content_type', array(static::class, 'mail_content_type'));
			if ('smtp' === $send['method']) {
				\remove_filter('phpmailer_init', array(static::class, 'mail_smtp'));
			}
			\remove_action('wp_mail_failed', array(static::class, 'wp_mail_failed'));
		}

		return $sent > 0;
	}

	/**
	 * Send Now wp_mail
	 *
	 * Send using wp_mail()
	 *
	 * @param array $to Recipient(s).
	 * @param string $subject Subject.
	 * @param string $message Message.
	 * @param array $headers Headers.
	 * @param array $attachments Attachments.
	 * @return bool True/false.
	 */
	protected static function send_now_wp_mail($to, $subject, $message, $headers, $attachments) {
		return !! \wp_mail($to, $subject, $message, $headers, $attachments);
	}

	/**
	 * Send Now Mandrill
	 *
	 * Send using Mandrill.
	 *
	 * @param array $to Recipient(s).
	 * @param string $subject Subject.
	 * @param string $message Message.
	 * @param array $headers Headers.
	 * @param array $attachments Attachments.
	 * @return bool True/false.
	 */
	protected static function send_now_mandrill($to, $subject, $message, $headers, $attachments) {
		$send = options::get('send');

		// Gotta have an API key.
		if (! $send['mandrill']['key']) {
			static::mail_failed(new \WP_Error(
				\__('Send Failed', 'well-handled'),
				'[Mandrill] ' . \__('Missing API key.', 'well-handled')
			));
			return false;
		}

		// The payload.
		$out = array(
			'key'=>$send['mandrill']['key'],
			'message'=>array(
				'html'=>$message,
				'subject'=>$subject,
				'from_email'=>$send['mandrill']['email'],
				'from_name'=>\apply_filters('wh_mail_from_name', $send['mandrill']['name'], 'mandrill'),
				'to'=>array(),
				'headers'=>array(),
				'track_opens'=>false,
				'track_clicks'=>false,
				'merge'=>false,
				'attachments'=>$attachments,
			),
		);

		// We need to break apart recipients.
		common\ref\cast::to_array($to);
		foreach ($to as $v) {
			if (false !== $recipient = static::parse_recipient($v)) {
				$recipient['type'] = 'to';
				$out['message']['to'][] = $recipient;
			}
		}

		// Walk through headers too.
		common\ref\cast::to_array($headers);
		foreach ($headers as $v) {
			common\ref\sanitize::whitespace($v, 1);
			$v2 = \explode("\n", $v);
			foreach ($v2 as $header) {
				$parts = \explode(':', $header);
				if (2 !== \count($parts)) {
					continue;
				}
				common\ref\sanitize::whitespace($parts);

				// A recipient?
				if (\preg_match('/^(bcc|cc)$/i', $parts[0])) {
					common\ref\mb::strtolower($parts[0]);
					if (false !== ($recipient = static::parse_recipient($parts[1]))) {
						$recipient['type'] = $parts[0];
						$out['message']['to'][] = $recipient;
					}
					continue;
				}
				elseif (
					$parts[0] &&
					$parts[1] &&
					('from' !== common\mb::strtolower($parts[0]))
				) {
					$out['message']['headers'][$parts[0]] = $parts[1];
				}
			}
		}

		// Make sure everything is valid UTF-8.
		common\ref\sanitize::utf8($out);

		$response = \wp_remote_post(
			'https://mandrillapp.com/api/1.0/messages/send.json',
			array(
				'timeout'=>10,
				'user-agent'=>'WordPress - ' . \get_bloginfo('name'),
				'body'=>\json_encode($out),
				'headers'=>array('Content-type: application/json'),
			)
		);
		$response = \wp_remote_retrieve_body($response);
		$response = \json_decode($response, true);

		// This should be an array of arrays. Let's get the first.
		if (\is_array($response)) {
			$response = common\data::array_pop_top($response);
		}

		// Parse the response, if it worked.
		if (\is_array($response) && isset($response['status'])) {
			// Success!
			if (\in_array($response['status'], array('sent', 'queued', 'scheduled'), true)) {
				return true;
			}
			elseif ('rejected' === $response['status']) {
				static::mail_failed(new \WP_Error(
					\__('Send Failed', 'well-handled'),
					'[Mandrill] ' . \__('The message was rejected', 'well-handled') . ': ' . $response['reject_reason']
				));
			}
			elseif ('error' === $response['status']) {
				static::mail_failed(new \WP_Error(
					\__('Send Failed', 'well-handled'),
					\sprintf('[Mandrill] %s: %s', $response['name'], $response['message'])
				));
			}
			return false;
		}

		// Generic error.
		static::mail_failed(new \WP_Error(
			\__('Send Failed', 'well-handled'),
			'[Mandrill] ' . \__('An invalid response was returned.', 'well-handled')
		));

		return false;
	}

	/**
	 * Send Now Amazon SES
	 *
	 * Send using Amazon SES
	 *
	 * @param array $to Recipient(s).
	 * @param string $subject Subject.
	 * @param string $message Message.
	 * @param array $headers Headers.
	 * @param array $attachments Attachments.
	 * @return bool True/false.
	 */
	protected static function send_now_ses($to, $subject, $message, $headers, $attachments) {
		$send = options::get('send');

		// Initialize connection.
		if (! \is_a(static::$ses, 'blobfolio\\wp\\wh\\vendor\\aws\\SimpleEmailService')) {
			try {
				static::$ses = new vendor\aws\SimpleEmailService(
					$send['ses']['access_key'],
					$send['ses']['secret_key'],
					$send['ses']['endpoint'],
					false,
					vendor\aws\SimpleEmailService::REQUEST_SIGNATURE_V4
				);
			} catch (\Throwable $e) {
				static::mail_failed(new \WP_Error(
					\__('Send Failed', 'well-handled'),
					'[SES] ' . \__('Could not initialize SES', 'well-handled') . ': ' . $e->getMessage()));
				return false;
			}
		}

		try {
			$m = new vendor\aws\SimpleEmailServiceMessage();
			$m->addTo($to);
			$m->setFrom(static::format_recipient($send['ses']['email'], \apply_filters('wh_mail_from_name', $send['ses']['name'], 'ses')));
			$m->setSubject($subject);
			$m->setMessageFromString('', $message);

			if (! empty($attachments)) {
				foreach ($attachments as $a) {
					$m->addAttachmentFromFile($a['name'], $a['path'], $a['type']);
				}
			}

			if (! empty($headers)) {
				foreach ($headers as $h) {
					// Special headers.
					if (\preg_match('/^bcc:/i', $h)) {
						$m->addBCC(\trim(\preg_replace('/^bcc:\s*/i', '', $h)));
					}
					elseif (\preg_match('/^cc:/i', $h)) {
						$m->addCC(\trim(\preg_replace('/^cc:\s*/i', '', $h)));
					}
					else {
						$m->addCustomHeader($h);
					}
				}
			}

			if (true !== ($status = static::$ses->sendEmail($m))) {
				if (\is_array($status) && isset($status['MessageId'])) {
					return true;
				}
				elseif (isset($status->error['Error'])) {
					static::mail_failed(new \WP_Error(
						\__('Send Failed', 'well-handled'),
						\sprintf('[SES] %s.', $status->error['Error']['Message'])));
				}
				else {
					static::mail_failed(new \WP_Error(
						\__('Send Failed', 'well-handled'),
						'[SES] ' . \__('Non-specific failure.', 'well-handled')));
				}

				return false;
			}
		} catch (\Throwable $e) {
			static::mail_failed(new \WP_Error(
				\__('Send Failed', 'well-handled'),
				'[SES] ' . $e->getMessage()));
			return false;
		}

		return true;
	}

	/**
	 * Mail Content-Type Filter
	 *
	 * @return string Content type.
	 */
	public static function mail_content_type() {
		return 'text/html';
	}

	/**
	 * SMTP Filter
	 *
	 * We will still use wp_mail() for sending SMTP messages,
	 * we just need to override the PHPMailer config.
	 *
	 * @param \PHPMailer $phpmailer PHPMailer object.
	 * @return void Nothing.
	 */
	public static function mail_smtp($phpmailer) {
		$send = options::get('send');
		if ('smtp' === $send['method']) {
			$phpmailer->IsSMTP();
			$phpmailer->From = $send['smtp']['email'];
			$phpmailer->FromName = \apply_filters('wh_mail_from_name', $send['smtp']['name'], 'smtp');
			$phpmailer->SetFrom($phpmailer->From, $phpmailer->FromName);
			if ('none' !== $send['smtp']['encryption']) {
				$phpmailer->SMTPSecure = \strtolower($send['smtp']['encryption']);
			}
			$phpmailer->Host = $send['smtp']['server'];
			$phpmailer->Port = $send['smtp']['port'];
			$phpmailer->SMTPAuth = true;
			$phpmailer->Username = $send['smtp']['user'];
			$phpmailer->Password = $send['smtp']['pass'];
			$phpmailer->SMTPAutoTLS = false;
		}
	}

	/**
	 * Error from wp_mail()
	 *
	 * @param \WP_Error $error Error object.
	 * @return void Nothing.
	 */
	public static function wp_mail_failed($error) {
		if (\is_wp_error($error)) {
			$message = $error->get_error_message();
			common\ref\sanitize::whitespace($message);
			if ($message) {
				static::mail_failed(new \WP_Error('Send Failed', '[wp_mail()] ' . $message));
			}
		}
	}

	/**
	 * General Send Error
	 *
	 * @param \WP_Error $error Error object.
	 * @return void Nothing.
	 */
	public static function mail_failed($error) {
		static::$_error = null;

		if (\is_wp_error($error)) {
			static::$_error = $error;
		}
	}

	/**
	 * Save Error
	 *
	 * @param array $mail Mail details.
	 * @param array $build Build details.
	 * @return bool True/false.
	 */
	public static function save_error($mail, $build) {
		$send_data = options::get('send_data');
		if (! \is_wp_error(static::$_error)) {
			return false;
		}

		common\ref\cast::to_array($mail);
		common\ref\cast::to_array($build);

		// Trigger user-hookable action.
		\do_action('wh_mail_error', $mail, $build, static::$_error);

		if (! $send_data['errors']) {
			return false;
		}

		// And save ours.
		$error = new message\error();
		return $error->save(
			array(
				'mail'=>$mail,
				'template'=>$build,
				'error'=>array(
					'code'=>static::$_error->get_error_code(),
					'message'=>static::$_error->get_error_message(),
				),
			)
		);
	}

	/**
	 * Get Last Error
	 *
	 * @return bool True/false.
	 */
	public static function get_error() {
		return \is_wp_error(static::$_error) ? static::$_error : false;
	}

	// --------------------------------------------------------------------- end send
}
