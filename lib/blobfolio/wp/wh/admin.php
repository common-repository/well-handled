<?php
/**
 * Well-Handled - Admin Tasks
 *
 * Most of the functionality lives in the backend.
 *
 * @package Well-Handled
 * @author  Blobfolio, LLC <hello@blobfolio.com>
 */

namespace blobfolio\wp\wh;

use blobfolio\wp\wh\vendor\common;

class admin {

	protected static $errors = array();

	// ---------------------------------------------------------------------
	// General
	// ---------------------------------------------------------------------

	/**
	 * Site Is UTF-8
	 *
	 * @return bool True/false.
	 */
	public static function is_utf8() {
		$charset = \strtolower(\get_bloginfo('charset'));
		$charset = \preg_replace('/[^a-z0-9]/', '', $charset);

		return 0 === \strpos($charset, 'utf8');
	}

	/**
	 * Warnings
	 *
	 * @return bool True/false.
	 */
	public static function warnings() {
		global $pagenow;

		// Only show warnings to administrators, and only on relevant pages.
		if (
			! \current_user_can('edit_wh_templates') ||
			('plugins.php' !== $pagenow && false === static::current_screen())
		) {
			return true;
		}

		if (! static::is_utf8()) {
			static::$errors[] = \__('Well-Handled uses UTF-8 encoding for all string output. Your blog is using a different encoding, which can cause text to get garbled.', 'well-handled');
		}

		if (! \extension_loaded('imap')) {
			static::$errors[] = \__('The recommended PHP extension IMAP is missing; this will limit the effectiveness of certain email operations.', 'well-handled');
		}

		if (! \function_exists('idn_to_ascii')) {
			static::$errors[] = \__('The recommended PHP extension Intl is missing; you will not be able to send messages to internationalized or unicode domains.', 'well-handled');
		}

		// All good!
		if (empty(static::$errors)) {
			return true;
		}

		?>
		<div class="notice notice-error">
			<p><?php
			\printf(
				\__('Your server does not meet all the requirements for running %s. Things might work out anyhow, but you or your system administrator should take a look at the following:', 'well-handled'),
				'<strong>Well-Handled Email Templates</strong>'
			);
			?><br>
			&nbsp;&nbsp;&bullet;&nbsp;&nbsp;<?php echo \implode('<br>&nbsp;&nbsp;&bullet;&nbsp;&nbsp;', static::$errors); ?></p>
		</div>
		<?php

		return false;
	}

	/**
	 * Privacy Policy
	 *
	 * @return void Nothing.
	 */
	public static function privacy_policy() {
		if (\function_exists('wp_add_privacy_policy_content')) {
			$privacy = array();

			$send_data = options::get('send_data');

			// We want to group the click warning with the general
			// retention notice where applicable.
			$clicks = '';
			if ($send_data['clicks']) {
				$clicks = ' ' . \__('The site also maintains a record of any links visited by the recipients of such messages for tracking purposes.', 'well-handled');
			}

			// Data.
			if ('meta' === $send_data['method']) {
				$privacy[] = \__('As a failsafe, this site records the metadata — the message recipient and subject — of certain outgoing email messages.', 'well-handled') . $clicks;
			}
			elseif ('full' === $send_data['method']) {
				$privacy[] = \__('As a failsafe, this site retains complete copies of certain outgoing email messages, including the recipient(s), subjects, and message content.', 'well-handled') . $clicks;
			}

			// Errors.
			if ($send_data['errors']) {
				$privacy[] = \__('In the event of a system or networking failure, the full details of a failed email message are logged for debugging purposes. If a subsequent send attempt succeeds, the entry is deleted.', 'well-handled');
			}

			// Add the notice!
			if (! empty($privacy)) {
				\wp_add_privacy_policy_content(
					'Well-Handled',
					\wp_kses_post(\wpautop(\implode("\n\n", $privacy)))
				);
			}
		}
	}

	/**
	 * Error Notice
	 *
	 * Mention recent send errors on the admin dashboard.
	 *
	 * @return void Nothing.
	 */
	public static function error_notice() {
		if (! \current_user_can('wh_read_stats')) {
			return;
		}

		$screen = \get_current_screen();
		if (('dashboard' !== $screen->id) || ! options::has('errors')) {
			return;
		}

		global $wpdb;
		$cutoff = \date('Y-m-d H:i:s', \strtotime('-2 days', \current_time('timestamp')));
		$errors = (int) $wpdb->get_var("
			SELECT COUNT(*)
			FROM `{$wpdb->prefix}wh_message_errors`
			WHERE `date_created` >= '$cutoff'
		");

		if ($errors) {
			?>
			<div class="notice notice-warning">
				<p><?php
					\printf(
						\__('Some recent %s messages could not be sent. Click %s to review and/or retry.', 'well-handled'),
						'<strong>Well-Handled</strong>',
						'<a href="' . \admin_url('edit.php?post_type=wh-template&page=wh-errors') . '">' . \__('here', 'well-handled') . '</a>'
					);
				?></p>
			</div>
			<?php
		}
	}

	/**
	 * Localize
	 *
	 * @return void Nothing.
	 */
	public static function localize() {
		\load_plugin_textdomain('well-handled', false, \basename(\WH_BASE) . '/languages');
	}

	/**
	 * Current Screen
	 *
	 * The WP Current Screen function isn't ready soon enough
	 * for our needs, so we need to get creative.
	 *
	 * @return bool|string WH screen type or false.
	 */
	public static function current_screen() {
		global $pagenow;
		global $typenow;

		// Obviously this needs to be an admin page.
		if (! \is_admin()) {
			return false;
		}

		// It is pretty straightforward when the post_type exists.
		if ('wh-template' === $typenow) {
			if (\array_key_exists('page', $_GET)) {
				return $_GET['page'];
			}
			if ('post-new.php' === $pagenow) {
				return 'post';
			}
			if ('edit.php' === $pagenow) {
				return 'edit';
			}
		}

		// Could be a miscellaneous page.
		if (\array_key_exists('page', $_GET)) {
			if (\preg_match('/^wh\-/', $_GET['page'])) {
				return $_GET['page'];
			}
		}

		// For some reason post.php doesn't expose the post_type.
		if ('post.php' === $pagenow) {
			$post = null;
			if (\array_key_exists('post', $_GET)) {
				$post = \get_post($_GET['post']);
			}
			elseif (\array_key_exists('post_ID', $_GET)) {
				$post = \get_post($_GET['post_ID']);
			}
			if (null !== $post && 'wh-template' === $post->post_type) {
				return 'post';
			}
		}

		return false;
	}

	/**
	 * Fix Server Name
	 *
	 * WordPress generates its wp_mail() "from" address from
	 * $_SERVER['SERVER_NAME'], which doesn't always exist. This
	 * will generate something to use as a fallback for CLI
	 * instances, etc.
	 *
	 * @return void Nothing.
	 */
	public static function server_name() {
		if (! \array_key_exists('SERVER_NAME', $_SERVER)) {
			if (false === $_SERVER['SERVER_NAME'] = common\sanitize::hostname(\site_url(), false)) {
				$_SERVER['SERVER_NAME'] = 'localhost';
			}
		}
	}

	// --------------------------------------------------------------------- end general



	// ---------------------------------------------------------------------
	// Menus & Pages
	// ---------------------------------------------------------------------

	/**
	 * Register Scripts & Styles
	 *
	 * Register our assets and enqueue some of them maybe.
	 *
	 * @return bool True/false.
	 */
	public static function enqueue_scripts() {
		if (false === ($screen = static::current_screen())) {
			return false;
		}

		// Chartist CSS.
		\wp_register_style(
			'wh_css_chartist',
			\plugins_url('css/chartist.css', \WH_BASE . 'index.php'),
			array(),
			\WH_VERSION
		);
		if ('wh-stats' === $screen) {
			\wp_enqueue_style('wh_css_chartist');
		}

		// Codemirror CSS.
		\wp_register_style(
			'wh_css_codemirror',
			\plugins_url('css/codemirror.css', \WH_BASE . 'index.php'),
			array(),
			\WH_VERSION
		);

		// Code editor themes.
		\wp_register_style(
			'wh_css_codemirror_themes',
			\plugins_url('css/codemirror-themes.css', \WH_BASE . 'index.php'),
			array('wh_css_codemirror'),
			\WH_VERSION
		);
		if ('post' === $screen || 'edit' === $screen) {
			\wp_enqueue_style('wh_css_codemirror_themes');
		}

		// Prism CSS.
		\wp_register_style(
			'wh_css_prism',
			\plugins_url('css/prismjs.css', \WH_BASE . 'index.php'),
			array(),
			\WH_VERSION
		);
		if ('wh-settings' === $screen || 'wh-help' === $screen) {
			\wp_enqueue_style('wh_css_prism');
		}

		// Main CSS.
		\wp_register_style(
			'wh_css',
			\plugins_url('css/core.css', \WH_BASE . 'index.php'),
			array(),
			\WH_VERSION
		);
		\wp_enqueue_style('wh_css');

		// Chartist JS.
		\wp_register_script(
			'wh_js_chartist',
			\plugins_url('js/chartist.min.js', \WH_BASE . 'index.php'),
			array('wh_js_vue'),
			\WH_VERSION,
			true
		);

		// Codemirror JS.
		\wp_register_script(
			'wh_js_codemirror',
			\plugins_url('js/codemirror.min.js', \WH_BASE . 'index.php'),
			array('jquery'),
			\WH_VERSION,
			true
		);

		// Vue JS.
		\wp_register_script(
			'wh_js_vue',
			\plugins_url('js/vue.min.js', \WH_BASE . 'index.php'),
			array('jquery'),
			\WH_VERSION,
			true
		);

		// Prism JS.
		\wp_register_script(
			'wh_js_prism',
			\plugins_url('js/prism.min.js', \WH_BASE . 'index.php'),
			array('jquery'),
			\WH_VERSION,
			true
		);

		// Help JS.
		\wp_register_script(
			'wh_js_help',
			\plugins_url('js/core-help.min.js', \WH_BASE . 'index.php'),
			array(
				'wh_js_vue',
				'wh_js_prism',
			),
			\WH_VERSION,
			true
		);
		if ('wh-help' === $screen) {
			\wp_enqueue_script('wh_js_help');
		}

		// Post JS.
		\wp_register_script(
			'wh_js_post',
			\plugins_url('js/core-post.min.js', \WH_BASE . 'index.php'),
			array(
				'wh_js_codemirror',
				'wh_js_vue',
			),
			\WH_VERSION,
			true
		);
		if ('post' === $screen || 'edit' === $screen) {
			\wp_enqueue_script('wh_js_post');
		}

		// Search JS (errors, activity, etc.).
		\wp_register_script(
			'wh_js_search',
			\plugins_url('js/core-search.min.js', \WH_BASE . 'index.php'),
			array(
				'wh_js_vue',
			),
			\WH_VERSION,
			true
		);
		if ('wh-errors' === $screen || 'wh-activity' === $screen) {
			\wp_enqueue_script('wh_js_search');
		}

		// Settings JS.
		\wp_register_script(
			'wh_js_settings',
			\plugins_url('js/core-settings.min.js', \WH_BASE . 'index.php'),
			array(
				'wh_js_vue',
				'wh_js_prism',
			),
			\WH_VERSION,
			true
		);
		if ('wh-settings' === $screen) {
			\wp_enqueue_script('wh_js_settings');
		}

		// Stats JS.
		\wp_register_script(
			'wh_js_stats',
			\plugins_url('js/core-stats.min.js', \WH_BASE . 'index.php'),
			array(
				'wh_js_vue',
				'wh_js_chartist',
			),
			\WH_VERSION,
			true
		);
		if ('wh-stats' === $screen) {
			\wp_enqueue_script('wh_js_stats');
		}

		return true;
	}

	/**
	 * Register Menus
	 *
	 * @return void Nothing.
	 */
	public static function register_menus() {
		$pages = array(
			'help',
			'activity',
			'stats',
			'errors',
			'settings',
		);
		foreach ($pages as $page) {
			\add_action('admin_menu', array(static::class, "{$page}_menu"));
		}
	}

	/**
	 * Reference Menu
	 *
	 * @return bool True/false.
	 */
	public static function help_menu() {
		\add_submenu_page(
			'edit.php?post_type=wh-template',
			\__('Reference', 'well-handled'),
			\__('Reference', 'well-handled'),
			'edit_wh_templates',
			'wh-help',
			array(static::class, 'help_page')
		);

		return true;
	}

	/**
	 * Reference Page
	 *
	 * @return bool True/false.
	 */
	public static function help_page() {
		require \WH_BASE . 'admin/help.php';
		return true;
	}

	/**
	 * Activity Menu
	 *
	 * @return bool True/false.
	 */
	public static function activity_menu() {
		// This page is only if there are messages.
		if (! options::has('messages')) {
			return false;
		}

		\add_submenu_page(
			'edit.php?post_type=wh-template',
			\__('Activity', 'well-handled'),
			\__('Activity', 'well-handled'),
			'wh_read_stats',
			'wh-activity',
			array(static::class, 'activity_page')
		);

		return true;
	}

	/**
	 * Activity Page
	 *
	 * @return bool True/false.
	 */
	public static function activity_page() {
		require \WH_BASE . 'admin/activity.php';
		return true;
	}

	/**
	 * Stats Menu
	 *
	 * @return bool True/false.
	 */
	public static function stats_menu() {
		// This page is only if there are messages.
		if (! options::has('messages')) {
			return false;
		}

		\add_submenu_page(
			'edit.php?post_type=wh-template',
			\__('Stats', 'well-handled'),
			\__('Stats', 'well-handled'),
			'wh_read_stats',
			'wh-stats',
			array(static::class, 'stats_page')
		);

		return true;
	}

	/**
	 * Stats Page
	 *
	 * @return bool True/false.
	 */
	public static function stats_page() {
		require \WH_BASE . 'admin/stats.php';
		return true;
	}

	/**
	 * Errors Menu
	 *
	 * @return bool True/false.
	 */
	public static function errors_menu() {
		// This page is only if there are errors.
		if (! options::has('errors')) {
			return false;
		}

		// Are there errors?
		global $wpdb;
		$errors = (int) $wpdb->get_var("
			SELECT COUNT(*)
			FROM `{$wpdb->prefix}wh_message_errors`
		");
		$menu_text = \__('Errors', 'well-handled');
		if ($errors) {
			$menu_text .= ' <span class="update-plugins count-' . $errors . '"><span class="update-count">' . "$errors</span></span>";
		}

		\add_submenu_page(
			'edit.php?post_type=wh-template',
			\__('Errors', 'well-handled'),
			$menu_text,
			'wh_read_stats',
			'wh-errors',
			array(static::class, 'errors_page')
		);

		return true;
	}

	/**
	 * Errors Page
	 *
	 * @return bool True/false.
	 */
	public static function errors_page() {
		require \WH_BASE . 'admin/errors.php';
		return true;
	}

	/**
	 * Settings Menu
	 *
	 * @return bool True/false.
	 */
	public static function settings_menu() {
		// Send settings.
		\add_submenu_page(
			'edit.php?post_type=wh-template',
			\__('Settings', 'well-handled'),
			\__('Settings', 'well-handled'),
			'manage_options',
			'wh-settings',
			array(static::class, 'settings_page')
		);

		return true;
	}

	/**
	 * Settings Pages
	 *
	 * @return bool True/false.
	 */
	public static function settings_page() {
		$section = \array_key_exists('section', $_GET) ? $_GET['section'] : '';

		switch ($section) {
			case 'data':
				require \WH_BASE . 'admin/settings-data.php';
				break;
			case 'queue':
				require \WH_BASE . 'admin/settings-queue.php';
				break;
			case 'roles':
				require \WH_BASE . 'admin/settings-roles.php';
				break;
			default:
				require \WH_BASE . 'admin/settings.php';
		}

		return true;
	}

	/**
	 * Settings Navigation
	 *
	 * @return void Nothing.
	 */
	public static function settings_navigation() {
		$section = \array_key_exists('section', $_GET) ? $_GET['section'] : '';
		$base = \admin_url('edit.php?post_type=wh-template&page=wh-settings');

		// The scheduling feature is deprecated and will be removed at some
		// future point.
		$send_queue = options::get('send_queue');
		if (! $send_queue['enabled']) {
			$scheduling = '';
		}
		else {
			$scheduling = \sprintf(
				'<a href="%s&amp;section=queue" class="nav-tab %s">%s</a>',
				\esc_attr($base),
				('queue' === $section ? 'nav-tab-active' : ''),
				\__('Scheduling', 'well-handled')
			);
		}

		?>
		<p>&nbsp;</p>
		<h3 class="nav-tab-wrapper">
			<a href="<?php echo \esc_attr($base); ?>" class="nav-tab <?php echo ! $section ? 'nav-tab-active' : ''; ?>"><?php echo \__('Sending', 'well-handled'); ?></a>
			<a href="<?php echo \esc_attr($base); ?>&amp;section=data" class="nav-tab <?php echo 'data' === $section ? 'nav-tab-active' : ''; ?>"><?php echo \__('Data/Tracking', 'well-handled'); ?></a><?php echo $scheduling; ?>
			<a href="<?php echo \esc_attr($base); ?>&amp;section=roles" class="nav-tab <?php echo 'roles' === $section ? 'nav-tab-active' : ''; ?>"><?php echo \__('User Access', 'well-handled'); ?></a>
		</h3>

		<?php
	}

	// --------------------------------------------------------------------- end menus



	// ---------------------------------------------------------------------
	// Add/Edit Post
	// ---------------------------------------------------------------------

	/**
	 * Post Notices
	 *
	 * WP notices from post updates. For whatever reason these
	 * can't simply be printed or passed from the form handler.
	 *
	 * @return void Nothing.
	 */
	public static function post_notices() {
		if (
			\in_array(static::current_screen(), array('edit', 'post'), true) &&
			false !== ($current_user = \wp_get_current_user())
		) {
			$transient_key = 'wh-post-notice-error-' . $current_user->ID;
			if (false !== ($cache = \get_transient($transient_key))) {
				if (! empty($cache) && \is_array($cache)) {
					foreach ($cache as $c) {
						echo '<div class="error"><p>' . \esc_html($c) . '</p></div>';
					}
				}
				\delete_transient($transient_key);
			}
		}
	}

	/**
	 * Set up Vue Data
	 *
	 * We use vue.js for runtime hotness, but it needs access to
	 * some data. There aren't any appropriate hooks for the add/edit
	 * post screen, so this is triggered along with notices.
	 *
	 * @return bool True/false.
	 */
	public static function post_vue_data() {
		global $wpdb;

		if (! \in_array(static::current_screen(), array('edit', 'post'), true)) {
			return false;
		}

		$current_user = \wp_get_current_user();

		$post = null;
		if (\array_key_exists('post', $_GET)) {
			$post = \get_post($_GET['post']);
		}
		elseif (\array_key_exists('post_ID', $_GET)) {
			$post = \get_post($_GET['post_ID']);
		}

		$data = array(
			'modal'=>false,
			'preview'=>'about:blank',
			'forms'=>array(
				'preview'=>array(
					'action'=>'wh_ajax_preview',
					'n'=>\wp_create_nonce('wh-nonce'),
					'template'=>$post->post_content ?? @\file_get_contents(\WH_BASE . 'skel/starter-template.html'),
					'data'=>isset($post->ID) ? \get_post_meta($post->ID, 'wh_render_data', true) : '',
					'options'=>array(),
					'email'=>0,
					'emailTo'=>$current_user->user_email,
					'post_id'=>$post->ID ?? 0,
				),
				'theme'=>array(
					'action'=>'wh_ajax_theme',
					'n'=>\wp_create_nonce('wh-nonce'),
					'theme'=>options::get('editor_theme'),
				),
			),
		);

		// Template preview data might exist from something already sent.
		if (isset($post->ID) && ! $data['forms']['preview']['data']) {
			$template_data = $wpdb->get_var(\sprintf("
				SELECT `template_data`
				FROM `{$wpdb->prefix}wh_messages`
				WHERE
					`template`='%s' AND
					LENGTH(`template_data`) AND
					NOT(`template_data` IN ('{}', '[]'))
				ORDER BY `date_created` DESC
				LIMIT 1
			", \esc_sql($post->post_name)));
			if (null !== $template_data) {
				common\ref\format::json($template_data);
				if (\is_string($template_data) && $template_data) {
					$data['forms']['preview']['data'] = $template_data;

					// WordPress applies a lazy strip_slashes that kills JS Unicode,
					// so we need to decode before saving.
					\update_post_meta($post->ID, 'wh_render_data', common\format::decode_js_entities($template_data));
				}
			}
		}

		foreach (template::OPTIONS as $k=>$v) {
			// Skip irrelevant options.
			if (! \is_bool($v) || 'utm' === \substr($k, 0, 3)) {
				continue;
			}
			$data['forms']['preview']['options'][$k] = ($v ? 1 : 0);
		}

		echo '<script>var wh_app_data=' . \json_encode($data) . ';</script>';
		return true;
	}

	/**
	 * Add Meta Boxes
	 *
	 * Register wh-template post meta boxes.
	 *
	 * @return void Nothing.
	 */
	public static function add_meta_boxes() {
		// Slug (i.e. post_name).
		\add_meta_box(
			'wh_meta_box_slug',
			\__('Slug', 'well-handled'),
			array(static::class, 'meta_box_slug'),
			'wh-template',
			'side'
		);

		// Code (i.e. post_content).
		\add_meta_box(
			'wh_meta_box_code',
			'<a href="' . \admin_url('edit.php?post_type=wh-template&page=wh-help') . '" class="alignright" target="_blank">' . \__('Reference', 'well-handled') . '</a>' . \__('Your Code!', 'well-handled'),
			array(static::class, 'meta_box_code'),
			'wh-template',
			'normal'
		);

		// Editor theme.
		\add_meta_box(
			'wh_meta_box_theme',
			\__('Editor Theme', 'well-handled'),
			array(static::class, 'meta_box_theme'),
			'wh-template',
			'side'
		);

		// Template viewer.
		if (\current_user_can('edit_wh_templates')) {
			\add_meta_box(
				'wh_meta_box_preview',
				\__('Preview!', 'well-handled'),
				array(static::class, 'meta_box_preview'),
				'wh-template',
				'side'
			);
		}
	}

	/**
	 * Post: Slug
	 *
	 * Add a field to edit the post_name.
	 *
	 * @param post $post Post object.
	 * @return void Nothing.
	 */
	public static function meta_box_slug($post) {
		?>
		<!-- a nonce -->
		<input type="hidden" name="wh-nonce" value="<?php echo \wp_create_nonce("wh-template-{$post->ID}"); ?>" />

		<label class="screen-reader-text" for="wh-slug"><?php echo \__('Slug', 'well-handled'); ?></label>
		<input type="text" name="wh-slug" id="wh-slug" required value="<?php echo \esc_attr($post->post_name); ?>" />

		<p class="description"><?php echo \__('This unique slug is used to retrieve the template programmatically. If you change this, be sure and update your code.', 'well-handled'); ?></p>
		<?php
	}

	/**
	 * Post: Preview Modal
	 *
	 * @return bool|void False or nothing.
	 */
	public static function preview_modal() {
		if (! \in_array(static::current_screen(), array('edit', 'post'), true)) {
			return false;
		}
		?>
		<transition name="fade" v-cloak>
			<div v-if="modal" id="wh-modal" class="wh-modal">
				<span v-on:click.prevent="modal=false" id="wh-modal--close" class="dashicons dashicons-no"></span>

				<div id="wh-modal--inner">
					<div id="wh-modal--inner--data">
						<fieldset class="wh-fieldset">
							<label class="wh-label" for="wh-modal--data"><?php echo \__('Test Data', 'well-handled'); ?></label>
							<textarea id="wh-modal--data" placeholder="{ &quot;firstname&quot; : &quot;Jane&quot;, &quot;lastname&quot; : &quot;Doe&quot; ... }" class="wh-code" v-model.trim="forms.preview.data"></textarea>

							<p class="description"><?php \__('Enter your desired test data above in JSON format to make the preview more meaningful.', 'well-handled'); ?></p>
						</fieldset>

						<fieldset class="wh-fieldset">
							<label class="wh-label" for="wh-modal--data"><?php echo \__('Build Options', 'well-handled'); ?></label>

							<div class="wh-preview-options">
							<?php
							foreach (template::OPTIONS as $k=>$v) {
								// Skip irrelevant options.
								if (! \is_bool($v) || 'utm' === \substr($k, 0, 3)) {
									continue;
								}

								$nice = \ucwords(\str_replace('_', ' ', $k));
								?>

									<label class="checkbox">
										<input type="checkbox" name="options[<?php echo $k; ?>]" v-model.number="forms.preview.options.<?php echo $k; ?>" v-bind:true-value="1" v-bind:false-value="0" />
										<?php echo $nice; ?>
									</label>
							<?php } ?>
							</div>
						</fieldset>

						<fieldset class="wh-fieldset">
							<label class="checkbox">
								<input type="checkbox" name="email" v-model.number="forms.preview.email" v-bind:true-value="1" v-bind:false-value="0" />
								<?php echo \__('Email It?', 'well-handled'); ?>
							</label>
						</fieldset>

						<fieldset class="wh-fieldset" v-if="forms.preview.email > 0">
							<label class="wh-label" for="wh-modal--emailto"><?php echo \__('Send To', 'well-handled'); ?></label>
							<input type="email" id="wh-modal--emailto" placeholder="jane@doe.com" v-model.trim="forms.preview.emailTo" required />
						</fieldset>

						<a v-on:click.prevent="getPreview" class="button button-primary"><?php echo \__('Generate Preview', 'well-handled'); ?></a>
					</div>

					<div id="wh-modal--inner--template">
						<iframe id="wh-modal--template" src="about:blank" frameborder="0" allowfullscreen></iframe>
					</div>
				</div>
			</div>
		</transition>
		<?php
	}

	/**
	 * Post: Code
	 *
	 * Add a field to edit the post_content.
	 *
	 * @param post $post Post object.
	 * @return void Nothing.
	 */
	public static function meta_box_code($post) {
		?>
		<textarea name="wh-code" id="wh-code" v-model.trim="forms.preview.template"></textarea>
		<?php
	}

	/**
	 * Post: Code Theme
	 *
	 * Switch codemirror themes.
	 *
	 * @param post $post Post object.
	 * @return void Nothing.
	 */
	public static function meta_box_theme($post) {
		?>
		<label class="screen-reader-text" for="wh-editor-theme"><?php echo \__('Editor Theme', 'well-handled'); ?></label>
		<select v-on:change="changeTheme" name="wh-editor-theme" id="wh-editor-theme" v-model="forms.theme.theme">
			<?php
			foreach (options::THEMES as $t) {
				echo '<option value="' . \esc_attr($t) . '">' . \esc_attr(\ucwords(\str_replace('-', ' ', $t))) . '</option>';
			}
			?>
		</select>
		<p class="description"><?php echo \__('Found the perfect editor? Click the button below to remember your choice.', 'well-handled'); ?></p>

		<a v-on:click.prevent="saveTheme" class="button" id="wh-save-editor-theme"><?php echo \__('Make Default', 'well-handled'); ?></a>
		<?php
	}

	/**
	 * Post: Template Viewer
	 *
	 * Preview a template with data.
	 *
	 * @param post $post Post object.
	 * @return void Nothing.
	 */
	public static function meta_box_preview($post) {
		?>
		<a v-on:click.prevent="showModal" class="button button-primary"><?php echo \__('Preview', 'well-handled'); ?></a>
		<?php
	}

	/**
	 * Post: Save
	 *
	 * Process additional fields during wh-template saves.
	 *
	 * @param int $post_id Post ID.
	 * @return bool True/false.
	 */
	public static function save_post($post_id) {
		global $wpdb;
		$errors = array();
		$data = \stripslashes_deep($_POST);

		// Wrong post types, etc.
		if (
			\wp_is_post_revision($post_id) ||
			\wp_is_post_autosave($post_id) ||
			! \current_user_can('edit_wh_templates') ||
			! isset($data['wh-nonce'])
		) {
			return false;
		}

		// Check nonce.
		if (! \wp_verify_nonce($data['wh-nonce'], "wh-template-{$post_id}")) {
			$errors[] = \__('The form had expired or was invalid. Please try again.', 'well-handled');
		}

		// Our data!
		$post = \get_post($post_id);
		$out = array(
			'post_content'=>$data['wh-code'] ?? '',
			'post_name'=>isset($data['wh-slug']) ? \sanitize_title($data['wh-slug']) : \sanitize_title($post->post_title),
		);

		common\ref\cast::to_string($out['post_content'], true);
		common\ref\cast::to_string($out['post_name'], true);

		if (! $out['post_name']) {
			$errors[] = \__('A slug for the template is required.', 'well-handled');
		}
		else {
			// Make sure it is unique.
			$out['post_name'] = \wp_unique_post_slug(
				$out['post_name'],
				$post_id,
				$post->post_status,
				'wh-template',
				$post->post_parent
			);
		}

		// Save it!
		if (empty($errors)) {
			$wpdb->update(
				"{$wpdb->prefix}posts",
				$out,
				array('ID'=>$post_id),
				'%s',
				'%d'
			);
		}
		else {
			// We have to store the errors so they can be printed later.
			$current_user = \wp_get_current_user();
			\set_transient(
				'wh-post-notice-error-' . $current_user->ID,
				$errors,
				30
			);
		}

		return true;
	}

	// --------------------------------------------------------------------- end post



	// ---------------------------------------------------------------------
	// Tracking
	// ---------------------------------------------------------------------

	// -------------------------------------------------
	// Whitelist rewrite query vars
	//
	// @param query_vars
	// @return query_vars
	/**
	 * Whitelist Query Vars
	 *
	 * @param array $query_vars Query variables.
	 * @return array Query variables.
	 */
	public static function rewrite_query_vars($query_vars) {
		$query_vars[] = 'wh_link';
		$query_vars[] = 'wh_image';
		return $query_vars;
	}

	/**
	 * Parse Rewrite Requests
	 *
	 * @param \WP_Query $query Query.
	 * @return mixed Content or true.
	 */
	public static function rewrite_parse_request($query) {
		// An image?
		if (\array_key_exists('wh_image', $query->query_vars)) {
			return static::tracking_image($query->query_vars['wh_image']);
		}
		// A link?
		elseif (\array_key_exists('wh_link', $query->query_vars)) {
			return static::tracking_link($query->query_vars['wh_link']);
		}

		return true;
	}

	/**
	 * Tracking Image
	 *
	 * Mark the message as opened and return image.
	 *
	 * @param string $mask Mask.
	 * @return void Nothing.
	 */
	public static function tracking_image($mask) {
		global $wpdb;

		// Remove fake extension.
		$mask = \preg_replace('/\.gif$/i', '', $mask);

		// Update.
		if (\preg_match('/^[A-Z0-9]{20}$/', $mask)) {
			$message = message::get($mask);
			if ($message->is_message()) {
				$message->save(array('opened'=>1));
				// Draw the image!
				\header('Content-Type: image/gif');
				die("\x47\x49\x46\x38\x39\x61\x01\x00\x01\x00\x90\x00\x00\xff\x00\x00\x00\x00\x00\x21\xf9\x04\x05\x10\x00\x00\x00\x2c\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02\x04\x01\x00\x3b");
			}
		}

		// Trigger a 404 once parsing is done.
		\add_action('wp', array(\WH_BASE_CLASS . 'admin', 'do_404'), 0, 0);
	}

	/**
	 * Tracking Link
	 *
	 * Mark the message as opened, link clicked, and redirect.
	 *
	 * @param string $mask Mask.
	 * @return void Nothing.
	 */
	public static function tracking_link($mask) {
		global $wpdb;

		if (\preg_match('/^[A-Z0-9]{20}$/', $mask)) {
			$link = message\link::get($mask);
			if ($link->is_link()) {
				$link->click();
			}
		}

		// Trigger a 404 once parsing is done.
		\add_action('wp', array(static::class, 'do_404'), 0, 0);
	}

	/**
	 * 404
	 *
	 * We can't trigger a proper 404 during parse_request,
	 * so this is a delayed response to an earlier error.
	 *
	 * @return void Nothing.
	 */
	function do_404() {
		global $wp_query;
		$wp_query->set_404();
		$wp_query->max_num_pages = 0;
		\status_header(404);
		\nocache_headers();
	}

	// --------------------------------------------------------------------- end tracking

}
