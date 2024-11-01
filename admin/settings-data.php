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
			'action'=>'wh_ajax_settings_data',
			'n'=>$nonce,
			'nuclear'=>options::get('nuclear'),
			'send_data'=>options::get('send_data'),
			'errors'=>array(),
			'saved'=>false,
			'loading'=>false,
		),
		'prune'=>array(
			'action'=>'wh_ajax_prune',
			'n'=>$nonce,
			'age'=>0,
			'mode'=>'full',
			'errors'=>array(),
			'saved'=>false,
			'loading'=>false,
		),
	),
	'showExpiration'=>false,
	'hasContent'=>array(
		'content'=>options::has('content'),
		'errors'=>options::has('errors'),
		'links'=>options::has('links'),
		'messages'=>options::has('messages'),
	),
	'prune'=>array(
		'full'=>100,
		'meta'=>60,
		'errors'=>15,
	),
);

// Javascript doesn't handle boolean values well, so let's recast.
$data['forms']['settings']['send_data']['clicks'] = $data['forms']['settings']['send_data']['clicks'] ? 1 : 0;
$data['forms']['settings']['send_data']['errors'] = $data['forms']['settings']['send_data']['errors'] ? 1 : 0;
$data['forms']['settings']['nuclear'] = $data['forms']['settings']['nuclear'] ? 1 : 0;


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

	<div class="updated" v-if="forms.prune.saved"><p><?php echo __('The database has been pruned!', 'well-handled'); ?></p></div>
	<div class="error" v-for="error in forms.prune.errors"><p>{{error}}</p></div>



	<?php admin::settings_navigation(); ?>



	<div id="poststuff">
		<div id="post-body" class="metabox-holder wh-columns">

			<!-- Column One -->
			<div class="postbox-container">

				<form name="settingsForm" method="post" action="<?php echo admin_url('admin-ajax.php'); ?>" v-on:submit.prevent="settingsSubmit">


					<!-- ==============================================
					DATA AND TRACKING
					=============================================== -->
					<div class="postbox">
						<h3 class="hndle"><?php echo __('Data & Tracking', 'well-handled'); ?></h3>
						<div class="inside">

							<table class="wh-settings">
								<tbody>
									<tr>
										<th scope="row">
											<label for="settings-send_data-method"><?php echo __('Data to Keep', 'well-handled'); ?></label>
										</th>
										<td>
											<select v-model="forms.settings.send_data.method" id="settings-send_data-method">
												<option value="none"><?php echo __('Nothing', 'well-handled'); ?></option>
												<option value="meta"><?php echo __('Metadata', 'well-handled'); ?></option>
												<option value="full"><?php echo __('Everything', 'well-handled'); ?></option>
											</select>

											<p class="description" v-if="forms.settings.send_data.method === 'none'"><?php echo __('No information about sent messages will be stored.', 'well-handled'); ?></p>

											<p class="description" v-else-if="forms.settings.send_data.method === 'meta'"><?php echo __('Metadata like to/from, send times, etc., will be stored, but not the messages themselves.', 'well-handled'); ?></p>

											<p class="description" v-else><?php echo __('Full copies of each sent message (except for attachments) will be stored.', 'well-handled'); ?></p>
										</td>
									</tr>
									<tr v-if="'full' === forms.settings.send_data.method">
										<th scope="row"><label for="settings-send_data-retention"><?php echo __('Retention', 'well-handled'); ?></label></th>
										<td>
											<?php echo __('For X Days', 'well-handled'); ?>:
											<input type="number" v-model.number="forms.settings.send_data.retention" min="0" step="1" max="999" id="settings-send_data-retention" />

											<p class="description"><?php echo __('If you would rather not store *full* message content indefinitely, enter the desired retention period above (or "0" to keep it forever).', 'well-handled'); ?></p>
										</td>
									</tr>
									<tr v-if="'none' !== forms.settings.send_data.method">
										<th scope="row">
											<label><?php echo __('Tracking', 'well-handled'); ?></label>
										</th>
										<td>
											<label>
												<input type="checkbox" checked disabled />
												<strong><?php echo __('Track Opens', 'well-handled'); ?></strong>
											</label>

											<p class="description"><?php
											// @codingStandardsIgnoreStart
											echo __(
												'Open rates are tracked automatically when you opt to keep metadata or full message content. This information, however, is often unavailable because of privacy controls employed by email software. Enabling click tracking (below) will help improve this metric.',
												'well-handled'
											);
											// @codingStandardsIgnoreEnd
											?></p>
										</td>
									</tr>
									<tr v-if="'none' !== forms.settings.send_data.method">
										<th scope="row">
											&nbsp;
										</th>
										<td>
											<label>
												<input type="checkbox" v-model.number="forms.settings.send_data.clicks" v-bind:true-value="1" v-bind:false-value="0" />
												<strong><?php echo __('Track Clicks', 'well-handled'); ?></strong>
											</label>

											<p class="description"><?php
											// @codingStandardsIgnoreStart
											echo __(
												'Click-tracking is achieved by rewriting all links within an email to point back to your site (with a unique identifier). When a recipient clicks a link, Well-Handled records the hit and then seamlessly redirects them to the intended link target.',
												'well-handled'
											);
											// @codingStandardsIgnoreEnd
											?></p>
										</td>
									</tr>
									<tr v-if="'none' !== forms.settings.send_data.method">
										<th scope="row">
											&nbsp;
										</th>
										<td>
											<label>
												<input type="checkbox" v-model.number="forms.settings.send_data.errors" v-bind:true-value="1" v-bind:false-value="0" />
												<strong><?php echo __('Log Errors', 'well-handled'); ?></strong>
											</label>

											<p class="description"><?php
											echo __('"Error", in this case, being anything that prevents a message from being compiled and handed off to your outgoing mailserver. This includes things like template errors, malformed recipients, and authentication errors.', 'well-handled');
											?></p>

											<p class="description"><?php
											echo __('The ultimate deliverability of a message is not something this plugin can detect, however if your "from" address resolves to a real mailbox, bounce notifications should wind up there.', 'well-handled');
											?></p>

										</td>
									</tr>
								</tbody>
							</table>

						</div>
					</div>

					<!-- ==============================================
					HOUSEKEEPING
					=============================================== -->
					<div class="postbox">
						<h3 class="hndle"><?php echo __('Housekeeping', 'well-handled'); ?></h3>
						<div class="inside">
							<p><label>
								<input type="checkbox" v-model.number="forms.settings.nuclear" v-bind:true-value="1" v-bind:false-value="0" />
								<strong><?php echo __('Remove Data When Uninstalling', 'well-handled'); ?></strong>
							</label></p>

							<p class="description"><?php echo __(
								'If the above is checked, *all* plugin data (settings, templates, messages, etc.) will be removed in the event you decide to uninstall Well-Handled. Otherwise, that data will be retained so you can pick up where you left off should you ever re-install the plugin.',
								'well-handled'
							); ?></p>
						</div>
					</div>

					<p><button type="submit" class="button button-large button-primary" v-bind:disabled="forms.settings.loading"><?php echo __('Save', 'well-handled'); ?></button></p>

				</form>

			</div><!--.postbox-container-->

			<!-- Column One -->
			<div class="postbox-container">

				<!-- ==============================================
				PRUNE OLD DATA
				=============================================== -->
				<div class="postbox" v-if="hasContent.links || hasContent.messages || hasContent.content || hasContent.errors">
					<h3 class="hndle"><?php echo __('Prune Old Data', 'well-handled'); ?></h3>
					<div class="inside">

						<p><?php
						echo __('A lot of data can be accrued over time. If needed, you can manually remove records that have outlived their usefulness to free up some space.', 'well-handled');
						?></p>

						<table class="wh-settings">
							<tbody>
								<tr v-if="hasContent.messages">
									<th scope="row">
										<label for="settings-prune-full"><?php echo __('Everything', 'well-handled'); ?></label>
									</th>
									<td>
										<form name="pruneFullForm" method="post" action="<?php echo admin_url('admin-ajax.php'); ?>" v-on:submit.prevent="pruneSubmit('full')">

											<label for="settings-prune-full"><?php echo __('Older Than X Days', 'well-handled'); ?>:</label>
											<input type="number" v-model.number="prune.full" id="settings-prune-full" min="30" max="999" step="1" />

											<p class="description"><?php
											echo sprintf(
												__('This will remove *all* records, metadata, and stats for messages sent more than %s days ago.', 'well-handled'),
												'{{prune.full}}'
											);
											?></p>

											<p><strong><?php echo __('Warning', 'well-handled'); ?>:</strong> <?php echo __('This cannot be undone!', 'well-handled'); ?></p>

											<p v-if="hasContent.links"><strong><?php echo __('Warning', 'well-handled'); ?></strong> <?php echo __('If recipients have saved any of these old messages for reference, the links within them will stop working.', 'well-handled'); ?></p>

											<button type="submit" class="button button-large" v-bind:disabled="forms.prune.loading"><?php echo __('Delete', 'well-handled'); ?></button>
										</form>
									</td>
								</tr>
								<tr v-if="hasContent.content">
									<th scope="row">
										<label for="settings-prune-meta"><?php echo __('Content', 'well-handled'); ?></label>
									</th>
									<td>
										<form name="pruneMetaForm" method="post" action="<?php echo admin_url('admin-ajax.php'); ?>" v-on:submit.prevent="pruneSubmit('meta')">
											<label for="settings-prune-meta"><?php echo __('Older Than X Days', 'well-handled'); ?>:</label>
											<input type="number" v-model.number="prune.meta" id="settings-prune-meta" min="30" max="999" step="1" />

											<p class="description"><?php
											echo sprintf(
												__('This will remove the message content — but leave metadata, click stats, etc., alone — for any message sent more than %s days ago.', 'well-handled'),
												'{{prune.meta}}'
											);
											?></p>

											<p><strong><?php echo __('Warning', 'well-handled'); ?></strong> <?php echo __('This cannot be undone!', 'well-handled'); ?></p>

											<button type="submit" class="button button-large" v-bind:disabled="forms.prune.loading"><?php echo __('Delete', 'well-handled'); ?></button>
										</form>
									</td>
								</tr>
								<tr v-if="hasContent.errors">
									<th scope="row">
										<label for="settings-prune-errors"><?php echo __('Errors', 'well-handled'); ?></label>
									</th>
									<td>
										<form name="pruneErrorsForm" method="post" action="<?php echo admin_url('admin-ajax.php'); ?>" v-on:submit.prevent="pruneSubmit('errors')">
											<label for="settings-prune-errors"><?php echo __('Older Than X Days', 'well-handled'); ?>:</label>
											<input type="number" v-model.number="prune.errors" id="settings-prune-errors" min="1" max="999" step="1" />

											<p class="description"><?php
											echo sprintf(
												__('This will remove all logged errors created more than %s days ago.', 'well-handled'),
												'{{prune.errors}}'
											);
											?></p>

											<p><strong><?php echo __('Warning', 'well-handled'); ?></strong> <?php echo __('This cannot be undone!', 'well-handled'); ?></p>

											<button type="submit" class="button button-large" v-bind:disabled="forms.prune.loading"><?php echo __('Delete', 'well-handled'); ?></button>
										</form>
									</td>
								</tr>
							</tbody>
						</table>
					</div>
				</div>

			</div><!--.postbox-container-->

		</div><!--#post-body-->
	</div><!--#poststuff-->
</div><!--.wrap-->
