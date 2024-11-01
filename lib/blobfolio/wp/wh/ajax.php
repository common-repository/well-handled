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

class ajax {

	const ACTIONS = array(
		// Posts.
		'wh_ajax_preview'=>'preview',
		'wh_ajax_theme'=>'theme',
		// Searches.
		'wh_ajax_activity'=>'activity',
		'wh_ajax_errors'=>'errors',
		// Resends and maintenance.
		'wh_ajax_error_delete'=>'error_delete',
		'wh_ajax_error_retry'=>'error_retry',
		// Settings & Tools.
		'wh_ajax_prune'=>'prune',
		'wh_ajax_queue'=>'queue',
		'wh_ajax_settings_send'=>'settings_send',
		'wh_ajax_settings_data'=>'settings_data',
		'wh_ajax_settings_queue'=>'settings_queue',
		'wh_ajax_settings_roles'=>'settings_roles',
		'wh_ajax_test_send'=>'test_send',
		// Stats.
		'wh_ajax_stats'=>'stats',
	);

	const RESPONSE = array(
		'data'=>array(),
		'errors'=>array(),
		'msg'=>'',
		'status'=>200,
	);

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
			\add_action("wp_ajax_$action", array(static::class, $handler));
		}
	}

	/**
	 * Send Response
	 *
	 * @param array $out Response.
	 * @return void Nothing.
	 */
	protected static function send($out=null) {
		$out = common\data::parse_args($out, static::RESPONSE);

		if (! empty($out['errors']) && 200 === $out['status']) {
			$out['status'] = 400;
		}

		\status_header($out['status']);

		echo \json_encode($out);
		exit;
	}

	/**
	 * Sanitize Request
	 *
	 * Strip slashes, fix UTF-8, and maybe test nonce.
	 *
	 * @param array $request Request.
	 * @param bool $nonce Test nonce.
	 * @return array Response.
	 */
	protected static function start(&$request=null, $nonce=true) {
		$out = static::RESPONSE;

		if (\is_array($request)) {
			$request = \stripslashes_deep($request);
			common\ref\cast::to_string($request);

			if ($nonce && (! isset($request['n']) || ! \wp_verify_nonce($request['n'], 'wh-nonce'))) {
				$out['errors'][] = \__('The form had expired. Please reload the page and try again.', 'well-handled');
			}
		}

		return $out;
	}



	// ---------------------------------------------------------------------
	// Posts
	// ---------------------------------------------------------------------

	/**
	 * Template Preview
	 *
	 * @return void Nothing.
	 */
	public static function preview() {
		$out = static::start($_POST);
		$out['data'] = array(
			'json'=>'',
			'preview'=>'',
			'email'=>false,
		);

		if (! \current_user_can('edit_wh_templates')) {
			$out['errors'][] = \__('You do not have access to preview templates.', 'well-handled');
			$out['status'] = 403;
		}

		$data = array(
			'data'=>'',
			'email'=>'',
			'emailTo'=>'',
			'options'=>array(),
			'post_id'=>0,
			'template'=>'',
		);

		// Falsey options won't be passed, so we have to explicitly set them
		// otherwise the truey defaults will take priority.
		foreach (template::OPTIONS as $k=>$v) {
			// Skip irrelevant options.
			if (! \is_bool($v) || 'utm' === \substr($k, 0, 3)) {
				continue;
			}
			$data['options'][$k] = false;
		}

		$data = common\data::parse_args($_POST, $data);
		common\ref\cast::to_bool($data['options']);
		common\ref\cast::to_bool($data['email'], true);
		common\ref\sanitize::to_range($data['post_id'], 0);
		$data['template'] = \trim($data['template']);

		common\ref\format::json($data['data']);
		if ($data['data'] && ! common\data::is_json($data['data'])) {
			$out['errors'][] = \__('The test data is not valid JSON.', 'well-handled');
		}
		else {
			$data['data'] = common\data::json_decode_array($data['data']);
		}

		if (! $data['template']) {
			$out['errors'][] = \__("The template is empty; there's nothing to show.", 'well-handled');
		}

		// Try to build it!
		if (empty($out['errors'])) {
			$template = new template();
			if (false === ($content = $template->make($data['data'], $data['options'], $data['template']))) {
				$out['errors'][] = \__('The template could not be built.', 'well-handled');

				$log = $template->get_log();
				if (\is_array($log)) {
					foreach ($log as $k=>$v) {
						$log[$k] = \str_replace('[' . \__('error', 'well-handled') . ']', '<span class="log-error">[' . \__('error', 'well-handled') . ']</span>', $log[$k]);
					}
				}
				else {
					$log = false;
				}

				$template = new template();
				$content = $template->make(
					array(
						'ERRORS'=>$out['errors'],
						'LOG'=>$log,
					),
					null,
					@\file_get_contents(\WH_BASE . 'skel/preview-error.html')
				);
			}

			$out['data']['json'] = \json_encode($data['data'], \JSON_PRETTY_PRINT);
			$out['data']['preview'] = $content['content'];
		}
		else {
			if (common\data::is_json($data['data'])) {
				$out['data']['json'] = \json_encode($data['data'], \JSON_PRETTY_PRINT);
			}
			$template = new template();
			$content = $template->make(
				array(
					'ERRORS'=>$out['errors'],
				),
				null,
				@\file_get_contents(\WH_BASE . 'skel/preview-error.html')
			);
			$out['data']['preview'] = $content['content'];
		}

		// Don't send empty JSON back.
		if (
			('{}' === $out['data']['json']) ||
			('[]' === $out['data']['json']) ||
			! $out['data']['json']
		) {
			$out['data']['json'] = '';
		}

		// Save the data?
		if (empty($out['errors']) && $data['post_id'] > 0) {
			if ($out['data']['json']) {
				// WordPress applies a lazy strip_slashes that kills JS Unicode,
				// so we need to decode before saving.
				\update_post_meta($data['post_id'], 'wh_render_data', common\format::decode_js_entities($out['data']['json']));
			}
			else {
				\delete_post_meta($data['post_id'], 'wh_render_data');
			}
		}

		// Are we emailing?
		common\ref\sanitize::email($data['emailTo']);
		if (empty($out['errors']) && $data['email'] && $data['emailTo']) {
			if ($data['post_id'] > 0) {
				$post = \get_post($data['post_id']);
				$title = $post->post_title;
			}
			else {
				$title = \__('Well-Handled Template', 'well-handled');
			}

			$out['data']['email'] = message::send_now(
				$data['emailTo'],
				\sprintf('[' . \__('TEST', 'well-handled') . '] %s', $title),
				$out['data']['preview'],
				array(),
				array(),
				true
			);
		}

		static::send($out);
	}

	/**
	 * Save Default Editor Theme
	 *
	 * @return void Nothing.
	 */
	public static function theme() {
		$out = static::start($_POST);
		$out['data']['success'] = false;

		if (! isset($_POST['theme'])) {
			$out['errors']['theme'] = \__('A theme is required.', 'well-handled');
		}

		if (empty($out['errors'])) {
			if (false !== options::save_editor_theme($_POST['theme'])) {
				$out['data']['success'] = true;
			}
			else {
				$out['errors']['theme'] = \__('A theme is required.', 'well-handled');
			}
		}

		static::send($out);
	}

	// --------------------------------------------------------------------- end posts



	// ---------------------------------------------------------------------
	// Settings & Tools
	// ---------------------------------------------------------------------

	/**
	 * Prune Data
	 *
	 * @return void Nothing.
	 */
	public static function prune() {
		global $wpdb;
		$out = static::start($_POST);

		if (! \current_user_can('manage_options')) {
			$out['errors']['other'] = \__('You do not have access to prune data.', 'well-handled');
			$out['status'] = 403;
		}

		if (
			! isset($_POST['mode']) ||
			! \in_array($_POST['mode'], array('full', 'meta', 'errors'), true)
		) {
			$out['errors']['mode'] = \__('Invalid data type for pruning.', 'well-handled');
		}

		if (! isset($_POST['age'])) {
			$_POST['age'] = 0;
		}
		common\ref\cast::to_int($_POST['age'], true);
		$min = 30;
		if ('errors' === $_POST['mode']) {
			$min = 1;
		}
		if ($_POST['age'] < $min) {
			$out['errors']['age'] = \sprintf(
				\__('For safety reasons data younger than %d days cannot be deleted.', 'well-handled'),
				$min
			);
		}

		if (empty($out['errors'])) {
			// For "meta", we just want to delete the message body.
			if ('meta' === $_POST['mode']) {
				$wpdb->query("
					UPDATE `{$wpdb->prefix}wh_messages`
					SET `message`=''
					WHERE DATEDIFF(NOW(), `date_created`) >= {$_POST['age']}
				");
			}
			// For everything, we need to delete links and messages.
			elseif ('full' === $_POST['mode']) {
				$wpdb->query("
					DELETE FROM `{$wpdb->prefix}wh_messages`
					WHERE DATEDIFF(NOW(), `date_created`) >= {$_POST['age']}
				");
				$wpdb->query("
					DELETE FROM `{$wpdb->prefix}wh_message_links`
					WHERE NOT(`message_id` IN (
						SELECT `id`
						FROM `{$wpdb->prefix}wh_messages`
						ORDER BY `id` ASC
					))");
			}
			// For errors, kill errors.
			else {
				$wpdb->query("
					DELETE FROM `{$wpdb->prefix}wh_message_errors`
					WHERE DATEDIFF(NOW(), `date_created`) >= {$_POST['age']}
				");
			}

			$out['data'] = array(
				'content'=>options::has('content', true),
				'errors'=>options::has('errors'),
				'links'=>options::has('links'),
				'messages'=>options::has('messages'),
			);
		}

		static::send($out);
	}

	/**
	 * Update Send Settings
	 *
	 * @return void Nothing.
	 */
	public static function settings_send() {
		$out = static::start($_POST);

		if (! \current_user_can('manage_options')) {
			$out['errors']['other'] = \__('You do not have access to manage settings.', 'well-handled');
			$out['status'] = 403;
		}

		$original = array(
			'send'=>options::get('send'),
		);
		$data = common\data::parse_args($_POST, $original);

		if (empty($out['errors'])) {
			foreach ($data as $k=>$v) {
				options::save($k, $v);
				$data[$k] = options::get($k);
			}

			$out['data'] = $data;
		}

		static::send($out);
	}

	/**
	 * Update Data Settings
	 *
	 * @return void Nothing.
	 */
	public static function settings_data() {
		$out = static::start($_POST);

		if (! \current_user_can('manage_options')) {
			$out['errors']['other'] = \__('You do not have access to manage settings.', 'well-handled');
			$out['status'] = 403;
		}

		$original = array(
			'nuclear'=>options::get('nuclear'),
			'send_data'=>options::get('send_data'),
		);
		$data = common\data::parse_args($_POST, $original);

		if (empty($out['errors'])) {
			foreach ($data as $k=>$v) {
				options::save($k, $v);
				$data[$k] = options::get($k);
			}

			// Convert our bools back to integers for JS.
			$data['send_data']['clicks'] = $data['send_data']['clicks'] ? 1 : 0;
			$data['send_data']['errors'] = $data['send_data']['errors'] ? 1 : 0;
			$data['nuclear'] = $data['nuclear'] ? 1 : 0;

			$out['data'] = $data;
		}

		static::send($out);
	}

	/**
	 * Update Send Queue Settings
	 *
	 * @return void Nothing.
	 */
	public static function settings_queue() {
		$out = static::start($_POST);

		if (! \current_user_can('manage_options')) {
			$out['errors']['other'] = \__('You do not have access to manage settings.', 'well-handled');
			$out['status'] = 403;
		}

		$original = array(
			'send_queue'=>options::get('send_queue'),
		);
		$data = common\data::parse_args($_POST, $original);

		if (empty($out['errors'])) {
			foreach ($data as $k=>$v) {
				options::save($k, $v);
				$data[$k] = options::get($k);
			}

			// Convert bools back to integers for JS.
			$data['send_queue']['enabled'] = $data['send_queue']['enabled'] ? 1 : 0;

			$out['data'] = $data;
		}

		static::send($out);
	}

	/**
	 * Update Roles Settings
	 *
	 * @return void Nothing.
	 */
	public static function settings_roles() {
		$out = static::start($_POST);

		if (! \current_user_can('manage_options')) {
			$out['errors']['other'] = \__('You do not have access to manage settings.', 'well-handled');
			$out['status'] = 403;
		}

		$original = array(
			'roles'=>options::get('roles'),
		);
		$data = common\data::parse_args($_POST, $original);

		if (empty($out['errors'])) {
			foreach ($data as $k=>$v) {
				options::save($k, $v);
				$data[$k] = options::get($k);
			}

			// Convert bools back to integers for JS.
			common\ref\cast::to_int($data['roles']);

			$out['data'] = $data;
		}

		static::send($out);
	}

	/**
	 * State of the Queue
	 *
	 * @return void Nothing.
	 */
	public static function queue() {
		global $wpdb;

		$out = static::start($_POST);
		$out['data']['queue'] = array(
			'total'=>0,
			'due'=>0,
			'next'=>'',
		);

		if (! \current_user_can('manage_options')) {
			$out['errors']['other'] = \__('You do not have access to manage settings.', 'well-handled');
			$out['status'] = 403;
		}

		if (empty($out['errors'])) {
			if (options::has('queue')) {
				$out['data']['queue']['total'] = (int) $wpdb->get_var("
					SELECT COUNT(*)
					FROM `{$wpdb->prefix}wh_message_queue`
				");
				$out['data']['queue']['due'] = (int) $wpdb->get_var("
					SELECT COUNT(*)
					FROM `{$wpdb->prefix}wh_message_queue`
					WHERE `date_scheduled` <= NOW()
				");
			}

			if (false !== ($timestamp = \wp_next_scheduled('wh_cron_send_queue'))) {
				$now = \time();

				if ($timestamp <= $now) {
					$out['data']['queue']['next'] = \__('Any minute now...', 'well-handled');
				}
				else {
					$s = $timestamp - $now;
					if ($s >= 60) {
						$s = \floor($s / 60);
						$out['data']['queue']['next'] = common\format::inflect($s, '%d ' . \__('minute', 'well-handled'), '%d ' . \__('minutes', 'well-handled'));
					}
					else {
						$out['data']['queue']['next'] = common\format::inflect($s, '%d ' . \__('second', 'well-handled'), '%d ' . \__('seconds', 'well-handled'));
					}
				}
			}
		}

		static::send($out);
	}

	/**
	 * Test Send Settings
	 *
	 * @return void Nothing.
	 */
	public static function test_send() {
		$out = static::start($_POST);

		if (! \current_user_can('manage_options')) {
			$out['errors']['other'] = \__('You do not have access to test the mail settings.', 'well-handled');
			$out['status'] = 403;
		}

		if (empty($out['errors'])) {
			$current_user = \wp_get_current_user();

			if (false === ($out['data']['status'] = $result = message::send_now(
				message::format_recipient(
					$current_user->user_email,
					$current_user->user_firstname
				),
				\__('[TEST] Well-Handled Mail Settings', 'well-handled'),
				\file_get_contents(\WH_BASE . 'skel/test-send.html'),
				array(),
				array(),
				$testmode = true
			))) {
				$error = message::get_error();
				if (\is_wp_error($error)) {
					$out['errors']['send'] = $error->get_error_message();
				}
				else {
					$out['errors']['send'] = \__('No specific error was returned; an authentication failure or server/port conflict is the most likely culprit.', 'well-handled');
				}
				$out['status'] = 500;
			}
		}

		static::send($out);
	}

	// --------------------------------------------------------------------- end settings



	// ---------------------------------------------------------------------
	// Searches
	// ---------------------------------------------------------------------

	/**
	 * Search Send Errors
	 *
	 * @return void Nothing.
	 */
	public static function errors() {
		global $wpdb;
		$out = static::start($_POST);
		$out['data'] = array(
			'page'=>0,
			'pages'=>0,
			'total'=>0,
			'items'=>array(),
		);

		$search = array(
			'date_min'=>'0000-00-00',
			'date_max'=>'0000-00-00',
			'page'=>0,
			'pageSize'=>10,
			'orderby'=>'date_created',
			'order'=>'desc',
		);

		$orders = array(
			'date_created'=>'Date',
		);

		if (! \current_user_can('wh_read_stats')) {
			$out['errors']['other'] = \__('You are not authorized to view send errors.', 'well-handled');
		}

		if (empty($out['errors'])) {

			// Sort out search parameters.
			$data = common\data::parse_args($_POST, $search);

			foreach (array('date_min', 'date_max') as $field) {
				common\ref\sanitize::date($data[$field]);
				if ('0000-00-00' === $data[$field]) {
					$data[$field] = \current_time('Y-m-d');
				}
			}
			if ($data['date_min'] > $data['date_max']) {
				common\data::switcheroo($data['date_min'], $data['date_max']);
			}

			if (! \array_key_exists($data['orderby'], $orders)) {
				$data['orderby'] = 'date_created';
			}
			if ('asc' !== $data['order'] && 'desc' !== $data['order']) {
				$data['order'] = 'desc';
			}

			common\ref\sanitize::to_range($data['pageSize'], 1, 50);

			// Build the query conditions.
			$conds = array();
			$conds[] = "DATE(`date_created`) >= '{$data['date_min']}'";
			$conds[] = "DATE(`date_created`) <= '{$data['date_max']}'";
			$conds = \implode(' AND ', $conds);

			$out['data']['total'] = (int) $wpdb->get_var("
				SELECT COUNT(*)
				FROM `{$wpdb->prefix}wh_message_errors`
				WHERE $conds
			");

			if ($out['data']['total'] > 0) {
				$out['data']['pages'] = (int) (\ceil($out['data']['total'] / $data['pageSize']) - 1);
				common\ref\sanitize::to_range($data['page'], 0, $out['data']['pages']);
				$out['data']['page'] = $data['page'];

				$from = $out['data']['page'] * $data['pageSize'];

				$dbResult = $wpdb->get_results("
					SELECT *
					FROM `{$wpdb->prefix}wh_message_errors`
					WHERE $conds
					ORDER BY `{$data['orderby']}` {$data['order']}
					LIMIT $from, {$data['pageSize']}
				", \ARRAY_A);
				if (isset($dbResult[0])) {
					foreach ($dbResult as $Row) {
						$item = array(
							'error_id'=>\intval($Row['id']),
							'date_created'=>common\sanitize::datetime($Row['date_created']),
							'message'=>array(
								'to'=>'',
								'subject'=>'',
								'message'=>'',
								'headers'=>array(),
								'attachments'=>array(),
								'template_slug'=>'',
							),
							'error'=>array(
								'code'=>'',
								'message'=>'',
							),
						);

						$item['message'] = common\data::json_decode_array($Row['mail'], $item['message']);
						$item['error'] = common\data::json_decode_array($Row['error'], $item['error']);

						$template = \json_decode($Row['template'], true);
						$item['message']['template_slug'] = $template['template_slug'];

						// Let's validate the message HTML just in case
						// that was skipped the first time around; don't want
						// anything dangerous showing up.
						if ($item['message']['message']) {
							template::validate_html($item['message']['message']);
						}

						$out['data']['items'][] = $item;
					}
				}
			}
		}

		static::send($out);
	}

	/**
	 * Delete Error
	 *
	 * @return void Nothing.
	 */
	public static function error_delete() {
		global $wpdb;
		$out = static::start($_POST);
		$out['data'] = array('success'=>false);

		if (! \current_user_can('wh_read_stats')) {
			$out['errors']['other'] = \__('You are not authorized to delete errors.', 'well-handled');
		}

		if (empty($errors)) {
			$error_id = isset($_POST['error_id']) ? \intval($_POST['error_id']) : 0;
			if ($error_id <= 0 || ! \intval($wpdb->get_var("
				SELECT `id`
				FROM `{$wpdb->prefix}wh_message_errors`
				WHERE `id`=$error_id
			"))) {
				$out['errors']['error_id'] = \__('Invalid error. Ironic. Haha.', 'well-handled');
				$out['status'] = 404;
			}
			else {
				// Go ahead and delete it!
				$wpdb->delete(
					"{$wpdb->prefix}wh_message_errors",
					array('id'=>$error_id),
					'%d'
				);
				$out['data']['success'] = true;
			}
		}

		static::send($out);
	}

	/**
	 * Retry Error
	 *
	 * This will attempt to resend a message that originally resulted in
	 * an error. If successful, the corresponding error message will be
	 * deleted.
	 *
	 * @return void Nothing.
	 */
	public static function error_retry() {
		global $wpdb;
		$out = static::start($_POST);
		$out['data'] = array('success'=>false);

		if (! \current_user_can('wh_read_stats')) {
			$out['errors']['other'] = \__('You are not authorized to retry errors.', 'well-handled');
		}

		if (empty($errors)) {
			$error_id = isset($_POST['error_id']) ? \intval($_POST['error_id']) : 0;
			if ($error_id <= 0) {
				$out['errors']['error_id'] = \__('Invalid error. Ironic. Haha.', 'well-handled');
				$out['status'] = 404;
			}
			else {
				$dbResult = $wpdb->get_results("
					SELECT
						`id`,
						`mail`,
						`template`
					FROM `{$wpdb->prefix}wh_message_errors`
					WHERE `id`=$error_id
				", \ARRAY_A);
				if (isset($dbResult[0])) {
					$raw = $dbResult[0];
					$raw['mail'] = \json_decode($raw['mail'], true);
					$raw['template'] = \json_decode($raw['template'], true);
					$raw['template']['template_data'] = common\data::json_decode_array($raw['template']['template_data']);
					$raw['template']['template_options'] = common\data::json_decode_array($raw['template']['template_options']);

					if ($raw['template']['template_slug']) {
						$options = $raw['template']['template_options'];
						$options['to'] = $raw['mail']['to'];
						$options['subject'] = $raw['mail']['subject'];
						$options['headers'] = $raw['mail']['headers'];
						$options['attachments'] = $raw['mail']['attachments'];

						$out['data']['success'] = !! \wh_mail_template(
							$raw['template']['template_slug'],
							$raw['template']['template_data'],
							$options
						);
					}
				}
				else {
					$out['errors']['error_id'] = \__('Invalid error. Ironic. Haha.', 'well-handled');
					$out['status'] = 404;
				}
			}
		}

		// All good this time!
		if ($out['data']['success']) {
			// Go ahead and remove the error!
			$wpdb->delete(
				"{$wpdb->prefix}wh_message_errors",
				array('id'=>$error_id),
				'%d'
			);
		}
		// Nope, still errors.
		elseif (empty($errors)) {
			$out['errors']['other'] = \__('The message could not be resent.', 'well-handled');
		}

		static::send($out);
	}

	/**
	 * Search Send Activity
	 *
	 * @return void Nothing.
	 */
	public static function activity() {
		global $wpdb;
		$out = static::start($_POST);
		$out['data'] = array(
			'page'=>0,
			'pages'=>0,
			'total'=>0,
			'items'=>array(),
		);

		$search = array(
			'date_min'=>'0000-00-00',
			'date_max'=>'0000-00-00',
			'email'=>'',
			'emailExact'=>1,
			'name'=>'',
			'nameExact'=>1,
			'subject'=>'',
			'subjectExact'=>1,
			'template'=>'',
			'method'=>'',
			'opened'=>-1,
			'page'=>0,
			'pageSize'=>10,
			'orderby'=>'date_created',
			'order'=>'desc',
		);

		$orders = array(
			'date_created'=>'Date',
			'email'=>'Email',
			'name'=>'Name',
			'subject'=>'Subject',
			'template'=>'Template',
		);

		if (! \current_user_can('wh_read_stats')) {
			$out['errors']['other'] = \__('You are not authorized to view activity.', 'well-handled');
		}

		if (empty($out['errors'])) {

			// Sort out search parameters.
			$data = common\data::parse_args($_POST, $search);

			foreach (array('date_min', 'date_max') as $field) {
				common\ref\sanitize::date($data[$field]);
				if ('0000-00-00' === $data[$field]) {
					$data[$field] = \current_time('Y-m-d');
				}
			}
			if ($data['date_min'] > $data['date_max']) {
				common\data::switcheroo($data['date_min'], $data['date_max']);
			}

			foreach (array('email', 'name', 'subject') as $field) {
				common\ref\sanitize::whitespace($data[$field]);
				if (common\mb::strlen($data[$field]) < 3) {
					$data[$field] = '';
					$data["{$field}Exact"] = 0;
				}
				else {
					common\ref\sanitize::to_range($data["{$field}Exact"], 0, 1);
				}
			}

			common\ref\sanitize::whitespace($data['template']);

			if (! \array_key_exists($data['orderby'], $orders)) {
				$data['orderby'] = 'date_created';
			}
			if ('asc' !== $data['order'] && 'desc' !== $data['order']) {
				$data['order'] = 'desc';
			}

			common\ref\sanitize::to_range($data['pageSize'], 1, 50);

			// Build the query conditions.
			$conds = array();
			$conds[] = "DATE(m.`date_created`) >= '{$data['date_min']}'";
			$conds[] = "DATE(m.`date_created`) <= '{$data['date_max']}'";

			foreach (array('email', 'name', 'subject') as $field) {
				if ($data[$field]) {
					// Exact match.
					if ($data["{$field}Exact"]) {
						$conds[] = "m.`$field`='" . \esc_sql($data[$field]) . "'";
					}
					// Fuzzy.
					else {
						$conds[] = "m.`$field` LIKE '%" . \esc_sql($wpdb->esc_like($data[$field])) . "%'";
					}
				}
			}

			if ($data['template']) {
				$conds[] = "m.`template`='" . \esc_sql($data['template']) . "'";
			}

			if ($data['method'] && \in_array($data['method'], options::SEND_METHODS, true)) {
				$conds[] = "m.`method`='{$data['method']}'";
			}

			if ($data['opened'] >= 0 && $data['opened'] <= 1) {
				$conds[] = "m.`opened`={$data['opened']}";
			}

			$conds = \implode(' AND ', $conds);

			$out['data']['total'] = (int) $wpdb->get_var("
				SELECT COUNT(*)
				FROM `{$wpdb->prefix}wh_messages` AS m
				WHERE $conds
			");

			if ($out['data']['total'] > 0) {
				$out['data']['pages'] = (int) (\ceil($out['data']['total'] / $data['pageSize']) - 1);
				common\ref\sanitize::to_range($data['page'], 0, $out['data']['pages']);
				$out['data']['page'] = $data['page'];

				$from = $out['data']['page'] * $data['pageSize'];

				$dbResult = $wpdb->get_results("
					SELECT
						m.*,
						p.post_title
					FROM
						`{$wpdb->prefix}wh_messages` AS m
						LEFT JOIN `{$wpdb->prefix}posts` AS p ON m.template=p.post_name AND p.post_type='wh-template'
					WHERE $conds
					ORDER BY m.`{$data['orderby']}` {$data['order']}
					LIMIT $from, {$data['pageSize']}
				", \ARRAY_A);
				if (isset($dbResult[0])) {
					foreach ($dbResult as $Row) {
						$item = array(
							'message_id'=>\intval($Row['id']),
							'date_created'=>common\sanitize::datetime($Row['date_created']),
							'template'=>$Row['post_title'] ? $Row['post_title'] : $Row['template'] . ' [missing]',
							'template_slug'=>$Row['template'],
							'message'=>array(
								'email'=>$Row['email'],
								'name'=>$Row['name'],
								'subject'=>$Row['subject'],
								'message'=>$Row['message'],
								'data'=>common\format::json($Row['template_data'], true),
								'options'=>common\format::json($Row['template_options'], true),
							),
							'stats'=>array(
								'compilation_time'=>\round($Row['compilation_time'], 3),
								'execution_time'=>\round($Row['execution_time'], 3),
								'method'=>$Row['method'],
								'opened'=>\intval($Row['opened']),
								'clicks'=>\intval($Row['clicks']),
							),
						);

						// Let's validate the message HTML just in case
						// that was skipped the first time around; don't want
						// anything dangerous showing up.
						if ($item['message']['message']) {
							template::validate_html($item['message']['message']);
						}

						$out['data']['items'][] = $item;
					}
				}
			}
		}

		static::send($out);
	}

	// --------------------------------------------------------------------- end errors



	// ---------------------------------------------------------------------
	// Stats!
	// ---------------------------------------------------------------------

	/**
	 * Collect Send Statistics
	 *
	 * @return void Nothing.
	 */
	public static function stats() {
		global $wpdb;

		$out = static::start($_POST);
		$out['data'] = array(
			'date_min'=>'0000-00-00',
			'date_max'=>'0000-00-00',
			'ranged'=>false,
			'stats'=>array(
				'period'=>0,
				'sent'=>array(
					'total'=>0,
					'avg'=>0.0,
					'recipients'=>0,
					'opened'=>array(),
					'open_rate'=>0.0,
					'method'=>array(),
					'template'=>array(),
				),
				'compilation_time'=>array(
					'total'=>0,
					'avg'=>0.0,
				),
				'execution_time'=>array(
					'total'=>0,
					'avg'=>0.0,
				),
				'clicks'=>array(
					'total'=>0,
					'avg'=>0.0,
					'subject'=>array(),
					'template'=>array(),
					'domain'=>array(),
					'url'=>array(),
					'subject_avg'=>array(),
					'template_avg'=>array(),
					'domain_avg'=>array(),
					'url_avg'=>array(),
				),
				'volume'=>array(
					'all'=>array(),
					'years'=>array(),
					'this_year'=>array(),
					'this_month'=>array(),
				),
			),
		);

		if (! \current_user_can('wh_read_stats')) {
			$out['errors']['other'] = \__('You are not authorized to view stats.', 'well-handled');
			$out['status'] = 403;
		}

		if (null === $out['data']['date_min'] = $wpdb->get_var("
			SELECT DATE(MIN(`date_created`))
			FROM `{$wpdb->prefix}wh_messages`
		")) {
			$out['errors']['data'] = \__('There are no stored messages.', 'well-handled');
		}

		// Let's kill the process here and now.
		if (! empty($out['errors'])) {
			static::send($out);
		}

		// We can get the max date now.
		$out['data']['date_max'] = $wpdb->get_var("
			SELECT DATE(MAX(`date_created`))
			FROM `{$wpdb->prefix}wh_messages`
		");

		// Our search range.
		$range = array(
			'date_min'=>$out['data']['date_min'],
			'date_max'=>$out['data']['date_max'],
		);
		$range = common\data::parse_args($_POST, $range);
		foreach ($range as $k=>$v) {
			common\ref\sanitize::date($range[$k]);
			if ('0000-00-00' === $range[$k]) {
				$range[$k] = $out['data'][$k];
			}
		}
		if ($range['date_min'] > $range['date_max']) {
			common\data::switcheroo($range['date_min'], $range['date_max']);
		}
		$out['data']['ranged'] = (
			($range['date_min'] !== $out['data']['date_min']) ||
			($range['date_max'] !== $out['data']['date_max'])
		);

		// Raw data.
		$raw = array(
			'volume'=>array(
				'years'=>array(),
				'months'=>array(),
				'month'=>array(
					'Jan'=>0,
					'Feb'=>0,
					'Mar'=>0,
					'Apr'=>0,
					'May'=>0,
					'Jun'=>0,
					'Jul'=>0,
					'Aug'=>0,
					'Sep'=>0,
					'Oct'=>0,
					'Nov'=>0,
					'Dec'=>0,
				),
				'day'=>array(),
				'all'=>array(),
			),
			'opened'=>0,
			'clicks'=>0,
			'clicks_template'=>array(),
			'clicks_subject'=>array(),
			'clicks_url'=>array(),
			'clicks_domain'=>array(),
			'subject'=>array(),
			'template'=>array(),
		);
		// Fill in years.
		for ($x = \date('Y', \strtotime($range['date_min'])); $x <= \date('Y', \strtotime($range['date_max'])); ++$x) {
			$raw['volume']['years'][$x] = 0;
		}
		// Fill in days.
		for ($x = 1; $x <= \current_time('t'); ++$x) {
			$raw['volume']['day'][$x] = 0;
		}
		// Fill in dates and months.
		for ($x = 0; \date('Y-m-d', \strtotime("+$x days", \strtotime($range['date_min']))) <= $range['date_max']; ++$x) {
			$raw['volume']['all'][\date('Y-m-d', \strtotime("+$x days", \strtotime($range['date_min'])))] = 0;
			$raw['volume']['months'][\date('Y-m', \strtotime("+$x days", \strtotime($range['date_min'])))] = 0;
		}

		// Build search conditions.
		$conds = array();
		$conds[] = "`date_created` >= '{$range['date_min']} 00:00:00'";
		$conds[] = "`date_created` <= '{$range['date_max']} 23:59:59'";
		$conds = \implode(' AND ', $conds);

		// Pull the data!
		$current_year = (int) \current_time('Y');
		$current_yearmonth = \current_time('Y-m');

		// Counter intuitively, this will be faster if split up into multiple queries.

		// First up, by date.
		$dbResult = $wpdb->get_results("
			SELECT
				DATE(`date_created`) AS `date_created`,
				COUNT(*) AS `count`,
				SUM(`clicks`) AS `clicks`
			FROM `{$wpdb->prefix}wh_messages`
			WHERE $conds
			GROUP BY DATE(`date_created`)
		", \ARRAY_A);
		if (! isset($dbResult[0])) {
			static::send($out);
		}
		foreach ($dbResult as $Row) {
			common\ref\cast::to_int($Row['clicks']);
			common\ref\cast::to_int($Row['count']);

			// General date-based counts.
			$time_created = \strtotime($Row['date_created']);
			$year_created = (int) \date('Y', $time_created);
			$yearmonth_created = \date('Y-m', $time_created);

			$raw['volume']['all'][\date('Y-m-d', $time_created)] += $Row['count'];
			$raw['volume']['years'][$year_created] += $Row['count'];
			$raw['volume']['months'][$yearmonth_created] += $Row['count'];
			if ($current_year === $year_created) {
				$raw['volume']['month'][\date('M', $time_created)] += $Row['count'];
			}
			if ($current_yearmonth === $yearmonth_created) {
				$raw['volume']['day'][\intval(\date('j', $time_created))] += $Row['count'];
			}

			$raw['clicks'] += $Row['clicks'];
		}

		// Now opens.
		$dbResult = $wpdb->get_results("
			SELECT
				`opened`,
				COUNT(*) AS `count`
			FROM `{$wpdb->prefix}wh_messages`
			WHERE $conds
			GROUP BY `opened`
			ORDER BY `count` DESC
		", \ARRAY_A);
		$tmp = array('Yes'=>0, 'No'=>0);
		foreach ($dbResult as $Row) {
			common\ref\cast::to_int($Row);
			$tmp[($Row['opened'] ? 'Yes' : 'No')] = $Row['count'];
			if ($Row['opened']) {
				$raw['opened'] = $Row['count'];
			}
		}
		$out['data']['stats']['sent']['opened'] = array(
			'labels'=>\array_keys($tmp),
			'series'=>\array_values($tmp),
		);

		// Method.
		$dbResult = $wpdb->get_results("
			SELECT
				`method`,
				COUNT(*) AS `count`
			FROM `{$wpdb->prefix}wh_messages`
			WHERE $conds
			GROUP BY `method`
			ORDER BY `count` DESC
		", \ARRAY_A);
		$tmp = array();
		foreach ($dbResult as $Row) {
			// Nice method names.
			switch ($Row['method']) {
				case 'ses':
					$Row['method'] = 'Amazon SES';
					break;
				case 'smtp':
					$Row['method'] = 'SMTP';
					break;
				case 'mandrill':
					$Row['method'] = 'Mandrill';
					break;
				default:
					$Row['method'] = 'Default';
			}

			$tmp[$Row['method']] = (int) $Row['count'];
		}
		$out['data']['stats']['sent']['method'] = array(
			'labels'=>\array_keys($tmp),
			'series'=>\array_values($tmp),
		);

		// Compilation and Execution.
		foreach (array('compilation_time', 'execution_time') as $field) {
			$dbResult = $wpdb->get_results("
				SELECT
					AVG($field) AS `avg`,
					SUM($field) AS `count`
				FROM `{$wpdb->prefix}wh_messages`
				WHERE
					$conds AND
					$field > 0
			", \ARRAY_A);
			if (isset($dbResult[0])) {
				$out['data']['stats'][$field]['total'] = \round($dbResult[0]['count'], 3);
				$out['data']['stats'][$field]['avg'] = \round($dbResult[0]['avg'], 3);
			}
		}

		// Template and subject.
		foreach (array('template', 'subject') as $field) {
			$dbResult = $wpdb->get_results("
				SELECT
					$field,
					SUM(`clicks`) AS `clicks`,
					COUNT(*) AS `count`
				FROM `{$wpdb->prefix}wh_messages`
				WHERE $conds
				GROUP BY $field
				ORDER BY $field ASC
			", \ARRAY_A);
			foreach ($dbResult as $Row) {
				common\ref\sanitize::whitespace($Row[$field]);
				$raw[$field][$Row[$field]] = (int) $Row['count'];
				$raw["clicks_{$field}"][$Row[$field]] = (int) $Row['clicks'];
			}
		}

		// Sort these.
		foreach (array('subject', 'template', 'clicks_subject', 'clicks_template') as $field) {
			\arsort($raw[$field]);
		}

		// Count emails.
		$out['data']['stats']['sent']['recipients'] = (int) $wpdb->get_var("
			SELECT COUNT(DISTINCT `email`)
			FROM `{$wpdb->prefix}wh_messages`
			WHERE $conds
		");

		// Click/URL stats.
		if ($raw['clicks'] > 0) {
			$dbResult = $wpdb->get_results("
				SELECT
					l.url,
					SUM(l.clicks) as `clicks`
				FROM
					`{$wpdb->prefix}wh_messages` AS m,
					`{$wpdb->prefix}wh_message_links` AS l
				WHERE
					m.date_created >= '{$range['date_min']} 00:00:00' AND
					m.date_created <= '{$range['date_max']} 23:59:59' AND
					l.message_id=m.id AND
					l.clicks > 0
				GROUP BY l.url
			", \ARRAY_A);
			if (isset($dbResult[0])) {
				foreach ($dbResult as $Row) {
					common\ref\cast::to_int($Row['clicks']);

					$parsed = common\mb::parse_url($Row['url']);
					if (! isset($parsed['host'])) {
						continue;
					}

					$domain = \preg_replace('/^www\./', '', $parsed['host']);
					$path = $parsed['path'] ?? '/';
					$url = $domain . $path;

					foreach (array('domain', 'url') as $field) {
						if (! $$field) {
							continue;
						}

						if (! isset($raw["clicks_{$field}"][$$field])) {
							$raw["clicks_{$field}"][$$field] = 0;
						}
						$raw["clicks_{$field}"][$$field] += $Row['clicks'];
					}
				}

				// And a little more sorting.
				foreach (array('clicks_url', 'clicks_domain') as $field) {
					\ksort($raw[$field]);
					\arsort($raw[$field]);
				}
			}
		}// End clicks.

		// Slice size for lists.
		$slice = 10;
		if (isset($_POST['slice'])) {
			$slice = common\cast::to_int($_POST['slice'], true);
			common\ref\sanitize::to_range($slice, 5);
		}

		// Now that we have our data, stuff it into our output!

		$out['data']['stats']['volume']['all'] = array(
			'labels'=>\array_keys($raw['volume']['all']),
			'series'=>array(\array_values($raw['volume']['all'])),
		);
		$out['data']['stats']['volume']['years'] = array(
			'labels'=>\array_keys($raw['volume']['years']),
			'series'=>array(\array_values($raw['volume']['years'])),
		);
		$out['data']['stats']['volume']['this_month'] = array(
			'labels'=>\array_keys($raw['volume']['day']),
			'series'=>array(\array_values($raw['volume']['day'])),
		);
		$out['data']['stats']['volume']['this_year'] = array(
			'labels'=>\array_keys($raw['volume']['month']),
			'series'=>array(\array_values($raw['volume']['month'])),
		);

		$out['data']['stats']['period'] = \count($raw['volume']['all']);
		$out['data']['stats']['sent']['total'] = \array_sum($raw['volume']['all']);

		// Don't need volume any more.
		unset($raw['volume']);

		$out['data']['stats']['sent']['avg'] = $out['data']['stats']['period'] > 0 ? \round($out['data']['stats']['sent']['total'] / $out['data']['stats']['period'], 3) : 0.0;
		$other = common\data::array_otherize($raw['template'], 5);
		$out['data']['stats']['sent']['template'] = array(
			'labels'=>\array_keys($other),
			'series'=>\array_values($other),
		);
		$out['data']['stats']['sent']['open_rate'] = $out['data']['stats']['sent']['total'] ? \round($raw['opened'] / $out['data']['stats']['sent']['total'] * 100, 3) : 0.0;

		if ($raw['clicks'] > 0) {
			$out['data']['stats']['clicks']['total'] = $raw['clicks'];
			$out['data']['stats']['clicks']['avg'] = \round($raw['clicks'] / $out['data']['stats']['sent']['total'], 3);

			foreach (array('domain', 'url', 'template', 'subject') as $field) {
				\array_splice($raw["clicks_{$field}"], $slice);
				$out['data']['stats']['clicks'][$field] = common\format::array_to_indexed($raw["clicks_{$field}"]);
			}
		}

		static::send($out);
	}

	// --------------------------------------------------------------------- end stats
}
