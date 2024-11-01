<?php
/**
 * Well-Handled message link.
 *
 * URLs can be rewritten to track link clicks.
 *
 * @package Well-Handled
 * @author  Blobfolio, LLC <hello@blobfolio.com>
 */

namespace blobfolio\wp\wh\message;

use blobfolio\wp\wh\vendor\common;

class link {

	const LINK = array(
		'mask'=>'',
		'message_id'=>0,
		'url'=>'',
		'clicks'=>0,
	);

	protected $link;
	protected static $_instances;
	protected static $_masks = array();

	// ---------------------------------------------------------------------
	// Init
	// ---------------------------------------------------------------------

	/**
	 * Pre-Construct
	 *
	 * Cache static objects locally for better performance.
	 *
	 * @param string $link_id Link mask.
	 * @param bool $refresh Refresh.
	 * @return object Instance.
	 */
	public static function get($link_id=null, $refresh=false) {
		// Figure out whether we're making a new instance or not.
		if (! isset(self::$_instances[static::class])) {
			self::$_instances[static::class] = array();
		}

		common\ref\cast::to_string($link_id, true);
		if (! \preg_match('/^[A-Z0-9]{20}$/', $link_id)) {
			return new static();
		}

		// Get the right object.
		if ($refresh || ! isset(self::$_instances[static::class][$link_id])) {
			self::$_instances[static::class][$link_id] = new static($link_id);
			if (! self::$_instances[static::class][$link_id]->is_link()) {
				unset(self::$_instances[static::class][$link_id]);
				return new static();
			}
		}

		return self::$_instances[static::class][$link_id];
	}

	/**
	 * Constructor
	 *
	 * @param string|int $link_id Link mask.
	 * @return bool true/false.
	 */
	public function __construct($link_id=null) {
		global $wpdb;

		$this->link = static::LINK;

		common\ref\cast::to_string($link_id, true);
		if (! \preg_match('/^[A-Z0-9]{20}$/', $link_id)) {
			return new static();
		}

		// And get the content.
		$dbResult = $wpdb->get_results("
			SELECT *
			FROM `{$wpdb->prefix}wh_message_links`
			WHERE `mask`='$link_id'
		");
		if (! isset($dbResult[0])) {
			return false;
		}
		$Row = common\data::array_pop_top($dbResult);
		$this->link = common\data::parse_args($Row, static::LINK);

		return true;
	}

	/**
	 * Is Link?
	 *
	 * @return bool True/false
	 */
	public function is_link() : bool {
		return isset($this->link['mask']) && $this->link['mask'];
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
			\array_key_exists($matches[1][0], static::LINK)
		) {
			$variable = $matches[1][0];
			$value = $this->is_link() ? $this->link[$variable] : static::LINK[$variable];

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

	// --------------------------------------------------------------------- end init



	// ---------------------------------------------------------------------
	// Save
	// ---------------------------------------------------------------------

	/**
	 * Generate Link Mask
	 *
	 * @return string Mask.
	 */
	public static function link_mask() {
		global $wpdb;
		$mask = common\data::random_string(20);
		while (
			\in_array($mask, static::$_masks, true) ||
			\intval($wpdb->get_var("
				SELECT COUNT(*)
				FROM `{$wpdb->prefix}wh_message_links`
				WHERE `mask`='$mask'
			"))
		) {
			$mask = common\data::random_string(20);
		}

		// Prevent collisions at runtime.
		static::$_masks[] = $mask;

		return $mask;
	}

	/**
	 * Save
	 *
	 * @param array $args Arguments.
	 *
	 * @arg int $message_id Message ID.
	 * @arg string $url URL.
	 * @arg int $clicks Clicks.
	 *
	 * @return bool True/false.
	 */
	public function save($args=null) {
		global $wpdb;

		common\ref\cast::to_array($args);
		if (! \is_array($args)) {
			return false;
		}

		// This gets generated automatically.
		if (isset($args['mask'])) {
			unset($args['mask']);
		}

		// Start with what we know.
		if ($this->is_link()) {
			$defaults = $this->link;
		}
		else {
			$defaults = static::LINK;
			$defaults['mask'] = static::link_mask();
		}

		$data = common\data::parse_args($args, $defaults);

		// Sanitize a few things.
		common\ref\sanitize::to_range($data['message_id'], 0);
		common\ref\sanitize::to_range($data['clicks'], 0);
		common\ref\sanitize::url($data['url']);

		// Okedoke, save it!
		$format = array(
			'%s',
			'%d',
			'%s',
			'%d',
		);

		// New.
		if (! $this->is_link()) {
			if (false === $wpdb->insert(
				"{$wpdb->prefix}wh_message_links",
				$data,
				$format
			)) {
				return false;
			}
		}
		// Existing.
		else {
			if (false === $wpdb->update(
				"{$wpdb->prefix}wh_message_links",
				$data,
				array('mask'=>$data['mask']),
				$format,
				'%s'
			)) {
				return false;
			}
		}

		// Repopulate the object.
		$this->__construct($data['mask']);

		// Recount the main message links.
		if ($this->link['clicks'] > 0) {
			$message = \blobfolio\wp\wh\message::get($data['message_id']);
			$message->recount();
		}

		return true;
	}

	/**
	 * Bulk Generate
	 *
	 * @param int $message_id Message ID.
	 * @param array $urls URL=>Mask.
	 * @return bool True/false.
	 */
	public static function bulk($message_id, $urls) {
		global $wpdb;

		common\ref\cast::to_int($message_id, true);
		if (
			$message_id <= 0 ||
			null === $wpdb->get_var("
				SELECT `id`
				FROM `{$wpdb->prefix}wh_messages`
				WHERE `id`=$message_id
			")
		) {
			return false;
		}

		common\ref\cast::to_array($urls);
		$inserts = array();

		foreach ($urls as $k=>$v) {
			common\ref\sanitize::url($k);
			if (! $k || ! \preg_match('/^[A-Z\d]{20}$/i', $v)) {
				continue;
			}

			$inserts[] = "('$v', $message_id, '" . \esc_sql($k) . "')";
		}

		if (empty($inserts)) {
			return false;
		}

		$chunks = \array_chunk($inserts, 250);
		foreach ($chunks as $v) {
			$wpdb->query("
				INSERT INTO `{$wpdb->prefix}wh_message_links`(`mask`,`message_id`,`url`)
				VALUES " . \implode(',', $v) . '
				ON DUPLICATE KEY UPDATE `mask`=`mask`
			');
		}

		return true;
	}

	/**
	 * Click
	 *
	 * @return void Nothing.
	 */
	public function click() {
		if ($this->is_link()) {
			// This will also update the main message record.
			$this->save(array('clicks'=>$this->link['clicks'] + 1));

			\wp_redirect($this->link['url']);
			exit;
		}

		// We can't trigger a 404 right now, so it'll have to wait.
		\add_action('wp', array(\WH_BASE_CLASS . 'admin', 'do_404'), 0, 0);
	}

	// --------------------------------------------------------------------- end save
}
