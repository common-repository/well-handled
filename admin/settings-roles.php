<?php
/**
 * Admin: Settings
 *
 * @package Well-Handled
 * @author  Blobfolio, LLC <hello@blobfolio.com>
 */

/**
 * Do not execute this file directly.
 */
if (! defined('ABSPATH')) {
	exit;
}

use blobfolio\wp\wh\admin;
use blobfolio\wp\wh\options;
use blobfolio\wp\wh\vendor\common;

global $wpdb;

$nonce = wp_create_nonce('wh-nonce');
$data = array(
	'forms'=>array(
		'settings'=>array(
			'action'=>'wh_ajax_settings_roles',
			'n'=>$nonce,
			'roles'=>options::get('roles'),
			'errors'=>array(),
			'saved'=>false,
			'loading'=>false,
		),
	),
);
// Javascript doesn't like bools.
common\ref\cast::to_int($data['forms']['settings']['roles']);

// JSON doesn't appreciate broken UTF.
common\ref\sanitize::utf8($data);
?>
<script>var whData=<?php echo json_encode($data, JSON_HEX_AMP); ?>;</script>
<div class="wrap" id="vue-settings" v-cloak>
	<h1><?php echo __('Well-Handled: Settings & Tools', 'well-handled'); ?></h1>



	<!-- ==============================================
	STATUS UPDATES
	=============================================== -->
	<div class="updated" v-if="forms.settings.saved"><p><?php echo __('Your settings have been saved!', 'well-handled'); ?></p></div>
	<div class="error" v-for="error in forms.settings.errors"><p>{{error}}</p></div>



	<?php admin::settings_navigation(); ?>



	<div id="poststuff">
		<div id="post-body" class="metabox-holder wh-columns">

			<!-- Column One -->
			<div class="postbox-container">

				<form name="settingsForm" method="post" action="<?php echo admin_url('admin-ajax.php'); ?>" v-on:submit.prevent="settingsSubmit">

					<!-- ==============================================
					MAIL SETTINGS
					=============================================== -->
					<div class="postbox">
						<h3 class="hndle"><?php echo __('User Access', 'well-handled'); ?></h3>
						<div class="inside">

							<p><?php echo __('By default, only site administrators can manage Well-Handled templates and view stats. But if you believe in delegation, you can grant access to lower-level users.', 'well-handled'); ?></p>

							<p><?php
							echo sprintf(
								__('If enabled, the users will be granted the %s for their respective roles.', 'well-handled'),
								'<a href="https://codex.wordpress.org/Roles_and_Capabilities#Roles" target="_blank">' . __('usual capabilities', 'well-handled') . '</a>'
							); ?></p>

							<table class="wh-results">
								<thead>
									<tr>
										<th><?php echo __('Role', 'well-handled'); ?></th>
										<th><?php echo __('Content', 'well-handled'); ?></th>
										<th><?php echo __('Stats', 'well-handled'); ?></th>
									</tr>
								</thead>
								<tbody>
									<tr v-for="(item, role) in forms.settings.roles">
										<td>{{role}}</td>
										<td><input type="checkbox" v-model.number="forms.settings.roles[role].content" v-bind:true-value="1" v-bind:false-value="0" /></td>
										<td><input type="checkbox" v-model.number="forms.settings.roles[role].stats" v-bind:true-value="1" v-bind:false-value="0" /></td>
									</tr>
								</tbody>
							</table>
						</div>
					</div>

					<p><button type="submit" class="button button-large button-primary" v-bind:disabled="forms.settings.loading"><?php echo __('Save', 'well-handled'); ?></button></p>

				</form>

			</div><!--.postbox-container-->

			<!-- Column One -->
			<div class="postbox-container">
				&nbsp;
			</div><!--.postbox-container-->

		</div><!--#post-body-->
	</div><!--#poststuff-->
</div><!--.wrap-->
