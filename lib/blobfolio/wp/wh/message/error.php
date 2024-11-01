<?php
/**
 * Well-Handled message error.
 *
 * Send-related errors go here.
 *
 * @package Well-Handled
 * @author  Blobfolio, LLC <hello@blobfolio.com>
 */

namespace blobfolio\wp\wh\message;

use blobfolio\wp\wh\vendor\common;

class error {

	const ERROR = array(
		'id'=>0,
		'date_created'=>'0000-00-00 00:00:00',
		'mail'=>'',
		'template'=>'',
		'error'=>'',
	);

	const JSON_FIELDS = array('mail', 'template', 'error');

	protected $error;

	// ---------------------------------------------------------------------
	// Init
	// ---------------------------------------------------------------------

	/**
	 * Constructor
	 *
	 * @param int $error_id Error ID.
	 * @return bool true/false.
	 */
	public function __construct($error_id=null) {
		global $wpdb;

		$this->error = static::ERROR;

		common\ref\cast::to_int($error_id, true);
		if ($error_id <= 0) {
			return false;
		}

		// And get the content.
		$dbResult = $wpdb->get_results("
			SELECT *
			FROM `{$wpdb->prefix}wh_message_errors`
			WHERE `id`=$error_id
		");
		if (! isset($dbResult[0])) {
			return false;
		}
		$Row = common\data::array_pop_top($dbResult);
		$this->error = common\data::parse_args($Row, static::ERROR);

		return true;
	}

	/**
	 * Is Error?
	 *
	 * @return bool True/false
	 */
	public function is_error() {
		return \is_array($this->error) && $this->error['id'] > 0;
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
			\array_key_exists($matches[1][0], static::ERROR)
		) {
			$variable = $matches[1][0];
			$value = $this->is_error() ? $this->error[$variable] : static::ERROR[$variable];

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
			// JSON.
			elseif (\in_array($variable, static::JSON_FIELDS, true)) {
				return common\data::json_decode_array($value);
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

		// Make sure our JSON fields are encoded appropriately.
		foreach (static::JSON_FIELDS as $field) {
			if (! isset($args[$field])) {
				continue;
			}

			if (! \is_string($args[$field])) {
				common\ref\cast::to_array($args[$field]);
				$args[$field] = \json_encode($args[$field]);
			}
			elseif (! common\data::is_json($args[$field])) {
				unset($args[$field]);
			}
		}

		// Start with what we know.
		if ($this->is_error()) {
			$defaults = $this->error;
		}
		else {
			$defaults = static::ERROR;
		}
		unset($defaults['id']);
		unset($defaults['date_created']);

		$data = common\data::parse_args($args, $defaults);

		// New.
		if (! $this->is_error()) {
			if (false === $wpdb->insert(
				"{$wpdb->prefix}wh_message_errors",
				$data,
				'%s'
			)) {
				return false;
			}

			$error_id = (int) $wpdb->insert_id;
		}
		// Existing.
		else {
			if (false === $wpdb->update(
				"{$wpdb->prefix}wh_message_errors",
				$data,
				array('id'=>$this->error['id']),
				'%s',
				'%d'
			)) {
				return false;
			}

			$error_id = $this->error['id'];
		}

		// Repopulate the object.
		return $this->__construct($error_id);
	}

	// --------------------------------------------------------------------- end save
}
