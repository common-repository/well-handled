<?php
/**
 * Well-Handled - AJAX
 *
 * AJAX handlers.
 *
 * @package Well-Handled
 * @author  Blobfolio, LLC <hello@blobfolio.com>
 */

namespace blobfolio\wp\wh;

use blobfolio\wp\wh\vendor\common;

class cron {

	const ACTIONS = array(
		'wh_cron_send_queue'=>'send_queue',
		'wh_cron_retention'=>'retention',
	);

	const SCHEDULES = array(
		'oneminute'=>array(
			'interval'=>60,
			'display'=>'Every minute',
		),
		'fiveminutes'=>array(
			'interval'=>300,
			'display'=>'Every 5 minutes',
		),
		'tenminutes'=>array(
			'interval'=>600,
			'display'=>'Every 10 minutes',
		),
		'halfhour'=>array(
			'interval'=>1800,
			'display'=>'Every half-hour',
		),
	);

	/**
	 * CRON Schedules
	 *
	 * Give WordPress a few more schedules to work with.
	 *
	 * @param array $schedules Schedules.
	 * @return array Schedules.
	 */
	public static function schedules($schedules) {
		foreach (static::SCHEDULES as $k=>$v) {
			$schedules[$k] = $v;
		}

		return $schedules;
	}

	/**
	 * Interval to slug.
	 *
	 * @param int $interval Interval.
	 * @return string|bool Slug or false.
	 */
	public static function get_cron_slug($interval) {
		$schedules = \wp_get_schedules();
		foreach ($schedules as $k=>$v) {
			if ($v['interval'] === $interval) {
				return $k;
			}
		}

		return false;
	}

	/**
	 * Register Actions
	 *
	 * This is a little tedious to do by hand in
	 * the main index.php file. Haha.
	 *
	 * @return void Nothing.
	 */
	public static function register_actions() {
		foreach (static::ACTIONS as $action=>$handler) {
			// Add the action.
			\add_action($action, array(static::class, $handler));

			$schedule = 'daily';

			// Users choose mail frequency.
			if ('send_queue' === $handler) {
				$send_queue = options::get('send_queue');

				// Not using queue.
				if (
					! $send_queue['enabled'] &&
					! options::has('queue')
				) {
					static::unregister_actions($action);
					continue;
				}

				// A bad frequency.
				if (false === ($schedule = static::get_cron_slug($send_queue['frequency'] * 60))) {
					continue;
				}
			}
			elseif ('retention' === $handler) {
				$send_data = options::get('send_data');

				if (0 === $send_data['retention']) {
					static::unregister_actions($action);
					continue;
				}
			}

			// Schedule if missing.
			if (false === ($timestamp = \wp_next_scheduled($action))) {
				\wp_schedule_event(\time(), $schedule, $action);
			}
		}
	}

	/**
	 * Unregister Actions
	 *
	 * WordPress doesn't kill useless hooks automatically.
	 *
	 * @param string $action Action.
	 * @return bool True/false.
	 */
	public static function unregister_actions($action=null) {
		// Do a single action.
		if ($action && \is_string($action)) {
			if (\array_key_exists($action, static::ACTIONS)) {
				if (false !== ($timestamp = \wp_next_scheduled($action))) {
					\wp_unschedule_event($timestamp, $action);
				}
			}
			return false;
		}

		// Kill them all!
		foreach (static::ACTIONS as $action=>$handler) {
			if (false !== ($timestamp = \wp_next_scheduled($action))) {
				\wp_unschedule_event($timestamp, $action);
			}
		}

		\error_log("hit $hit");

		return true;
	}

	// ---------------------------------------------------------------------
	// Messages
	// ---------------------------------------------------------------------

	/**
	 * Send Scheduled Mail
	 *
	 * @return bool True/false.
	 */
	public static function send_queue() {
		global $wpdb;

		// Set up a simple lock to prevent overlapping jobs.
		// The lock is only valid for 10 minutes, so if PHP
		// is allowed to run indefinitely there could still be
		// problems.
		$now = \microtime(true);
		if (false !== ($lock = \get_option('wh_cron_send_queue_lock', false))) {
			common\ref\cast::to_float($lock, true);
			if ($lock < $now && $now - $lock < 600) {
				return false;
			}
		}

		$send_queue = options::get('send_queue');

		// Anything needing sending?
		$dbResult = $wpdb->get_results("
			SELECT
				`id`,
				`data`
			FROM `{$wpdb->prefix}wh_message_queue`
			WHERE `date_scheduled` <= NOW()
			ORDER BY `id` ASC
			LIMIT {$send_queue['qty']}
		", \ARRAY_A);
		if (! isset($dbResult[0])) {
			return false;
		}

		// Lock it.
		\update_option('wh_cron_send_queue_lock', $now);

		$defaults = array(
			'to'=>null,
			'subject'=>null,
			'message'=>null,
			'headers'=>null,
			'attachments'=>null,
			'testmode'=>false,
			'build'=>null,
		);

		foreach ($dbResult as $Row) {
			$data = common\data::json_decode_array($Row['data'], $defaults);
			message::send_now(
				$data['to'],
				$data['subject'],
				$data['message'],
				$data['headers'],
				$data['attachments'],
				$data['testmode'],
				$data['build']
			);

			// Remove the message from the queue immediately to
			// prevent accidental duplicate send.
			$wpdb->delete(
				"{$wpdb->prefix}wh_message_queue",
				array('id'=>$Row['id']),
				'%d'
			);
		}

		// Unlock it.
		\delete_option('wh_cron_send_queue_lock');

		return true;
	}

	/**
	 * Expire Old Message Content
	 *
	 * @return bool True/false.
	 */
	public static function retention() {
		global $wpdb;

		$send_data = options::get('send_data');
		if (0 === $send_data['retention']) {
			return false;
		}

		// This is easy.
		$wpdb->query("
			UPDATE `{$wpdb->prefix}wh_messages`
			SET `message`=''
			WHERE DATEDIFF(NOW(), `date_created`) >= {$send_data['retention']}
		");

		return true;
	}

	// --------------------------------------------------------------------- end messages

}
