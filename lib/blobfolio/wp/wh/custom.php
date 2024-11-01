<?php
/**
 * Well-Handled - Custom post types, taxonomies, roles.
 *
 * Register all the custom content.
 *
 * @package Well-Handled
 * @author  Blobfolio, LLC <hello@blobfolio.com>
 */

namespace blobfolio\wp\wh;

class custom {

	// General capabilities.
	const CAPABILITIES = array(
		'edit_post'=>'edit_wh_template',
		'read_post'=>'read_wh_template',
		'delete_post'=>'delete_wh_template',
		'edit_posts'=>'edit_wh_templates',
		'edit_others_posts'=>'edit_others_wh_templates',
		'publish_posts'=>'publish_wh_templates',
		'read_private_posts'=>'read_private_wh_templates',
		'delete_posts'=>'delete_wh_templates',
		'delete_private_posts'=>'delete_private_wh_templates',
		'delete_published_posts'=>'delete_published_wh_templates',
		'delete_others_posts'=>'delete_others_wh_templates',
		'edit_private_posts'=>'edit_private_wh_templates',
		'edit_published_posts'=>'edit_published_wh_templates',
	);

	const STATS_CAPABILITY = 'wh_read_stats';

	protected static $roles;

	/**
	 * Register Taxonomies
	 *
	 * @since 2.0.0
	 *
	 * @return void Nothing.
	 */
	public static function register_taxonomies() {
		// Template tags.
		\register_taxonomy(
			'wp_template_tag',
			array('wh-template'),
			array(
				'labels'=>array(
					'name'=>\__('Tags', 'well-handled'),
					'singular_name'=>\__('Tag', 'well-handled'),
					'search_items'=>\__('Search Tags', 'well-handled'),
					'all_items'=>\__('All Tags', 'well-handled'),
					'parent_item'=>\__('Parent Tag', 'well-handled'),
					'parent_item_colon'=>'Parent Tag:',
					'edit_item'=>\__('Edit Tag', 'well-handled'),
					'update_item'=>\__('Update Tag', 'well-handled'),
					'add_new_item'=>\__('Add New Tag', 'well-handled'),
					'new_item_name'=>\__('New Tag Name', 'well-handled'),
					'menu_name'=>\__('Tags', 'well-handled'),
				),
				'rewrite'=>false,
				'show_ui'=>true,
				'show_admin_column'=>true,
				'show_in_nav_menus'=>true,
				'query_var'=>false,
				'hierarchical'=>false,
			)
		);
	}

	/**
	 * Register Post Types
	 *
	 * @since 2.0.0
	 *
	 * @return void Nothing.
	 */
	public static function register_post_types() {
		// Templates.
		\register_post_type(
			'wh-template',
			array(
				'labels'=>array(
					'name'=>\__('Templates', 'well-handled'),
					'singular_name'=>\__('Template', 'well-handled'),
					'menu_name'=>'Well-Handled',
					'name_admin_bar'=>\__('Template', 'well-handled'),
					'add_new'=>\__('Add New', 'well-handled'),
					'add_new_item'=>\__('Add New Template', 'well-handled'),
					'new_item'=>\__('New Template', 'well-handled'),
					'edit_item'=>\__('Edit Template', 'well-handled'),
					'view_item'=>\__('View Template', 'well-handled'),
					'all_items'=>\__('All Templates', 'well-handled'),
					'search_items'=>\__('Search Templates', 'well-handled'),
					'parent_item_colon'=>\__('Parent Templates', 'well-handled'),
					'not_found'=>\__('No Templates Found.', 'well-handled'),
					'not_found_in_trash'=>\__('No Templates Found In Trash.', 'well-handled'),
				),
				'public'=>false,
				'exclude_from_search'=>true,
				'publicly_queryable'=>false,
				'show_ui'=>true,
				'show_in_menu'=>true,
				'show_in_nav_menus'=>true,
				'show_in_admin_bar'=>false,
				'menu_icon'=>'dashicons-layout',
				'capability_type'=>'wh_template',
				'capabilities'=>static::CAPABILITIES,
				'hierarchical'=>false,
				'supports'=>array('title', 'permalink'),
				'taxonomies'=>array('wp_template_tag'),
				'has_archive'=>false,
				'rewrite'=>false,
				'query_var'=>false,
			)
		);
	}

	/**
	 * Register Capabilities
	 *
	 * Templates have their own access restrictions just in
	 * case site operators don't trust their editors.
	 *
	 * @since 2.0.0
	 *
	 * @return void Nothing.
	 */
	public static function register_capabilities() {
		global $wp_roles;
		$all = \array_keys($wp_roles->roles);
		$possible = options::get('roles');
		$impossible = \array_diff($all, \array_keys($possible));

		foreach ($all as $role_slug) {
			$role = \get_role($role_slug);
			$content = isset($possible[$role_slug]) && $possible[$role_slug]['content'];
			$stats = isset($possible[$role_slug]) && $possible[$role_slug]['stats'];

			// Start with content capabilities.
			foreach (static::CAPABILITIES as $k=>$v) {
				// Remove invalid privileges.
				if (! $content || ! \array_key_exists($k, $role->capabilities)) {
					if (\array_key_exists($v, $role->capabilities)) {
						$role->remove_cap($v);
					}
				}
				// Add missing ones.
				elseif (
					\array_key_exists($k, $role->capabilities) &&
					! \array_key_exists($v, $role->capabilities)
				) {
					$role->add_cap($v);
				}
			}

			// And now onto stats.
			if ($stats && ! \array_key_exists(static::STATS_CAPABILITY, $role->capabilities)) {
				$role->add_cap(static::STATS_CAPABILITY);
			}
			elseif (! $stats && \array_key_exists(static::STATS_CAPABILITY, $role->capabilities)) {
				$role->remove_cap(static::STATS_CAPABILITY);
			}
		}

		// Reset role data.
		static::$roles = null;
		options::get_default_roles(true);

		// Don't run this again for a while.
		options::save('reload_capabilities', false, true);
	}

	/**
	 * Maybe Reload Capabilities
	 *
	 * WordPress caches capabilities to save on heavy operations,
	 * and we want to respect that as much as possible to prevent
	 * major performance drains.
	 *
	 * @since 2.0.0
	 *
	 * @return void Nothing.
	 */
	public static function check_capabilities() {
		if (
			(\current_user_can('manage_options') && ! \current_user_can('edit_wh_templates')) ||
			options::get('reload_capabilities')
		) {
			\add_action('admin_init', array(static::class, 'register_capabilities'), 0, 0);
		}
	}

	/**
	 * Map Additional Capabilities
	 *
	 * WP's role/capability mapping is a little convoluted
	 * so we have to do a bit of a tap dance to make sure
	 * things get set correctly.
	 *
	 * @since 2.0.0
	 *
	 * @param array $caps Capabilities.
	 * @param string $cap Capability.
	 * @param int $user_id User ID.
	 * @param array $args Arguments.
	 * @return array Capabilities.
	 */
	public static function map_capabilities($caps, $cap, $user_id, $args) {
		// We're specifically looking to re-map edit/delete/read
		// privileges for wh_templates.
		if (
			! \is_array($args) ||
			empty($args) ||
			! \in_array($cap, array('edit_wh_template', 'delete_wh_template', 'read_wh_template'), true)
		) {
			return $caps;
		}

		$post = \get_post($args[0]);
		if (null === $post) {
			return $caps;
		}
		$post_type = \get_post_type_object($post->post_type);
		// Empty the caps.
		$caps = array();

		switch ($cap) {
			case 'edit_wh_template':
				if ($user_id === $post->post_author) {
					$caps[] = $post_type->cap->edit_posts;
				}
				else {
					$caps[] = $post_type->cap->edit_others_posts;
				}
				break;
			case 'delete_wh_template':
				if ($user_id === $post->post_author) {
					$caps[] = $post_type->cap->delete_posts;
				}
				else {
					$caps[] = $post_type->cap->delete_others_posts;
				}
				break;
			case 'read_wh_template':
				if (('private' !== $post->post_status) || ($user_id === $post->post_author)) {
					$caps[] = 'read';
				}
				else {
					$caps[] = $post_type->cap->read_private_posts;
				}
				break;
		}

		return $caps;
	}

	/**
	 * Get Roles
	 *
	 * This returns an array of WordPress roles with the basic
	 * capacity for managing (any) post content.
	 *
	 * @since 2.0.0
	 *
	 * @param string $role Role.
	 * @param bool $refresh Refresh.
	 * @return array|bool Roles.
	 */
	public static function get_roles($role=null, $refresh=false) {
		global $wp_roles;

		if ($refresh || null === static::$roles) {
			static::$roles = array();
			$all = $wp_roles->roles;

			foreach ($all as $k=>$v) {
				$tmp = \array_intersect_key($v['capabilities'], static::CAPABILITIES);
				if (! empty($tmp)) {
					static::$roles[] = $k;
				}
			}

			\sort(static::$roles);
		}

		if (null !== $role) {
			return \in_array($role, static::$roles, true);
		}

		return static::$roles;
	}
}
