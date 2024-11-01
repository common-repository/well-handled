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
			'action'=>'wh_ajax_settings_send',
			'n'=>$nonce,
			'send'=>options::get('send'),
			'errors'=>array(),
			'saved'=>false,
			'loading'=>false,
		),
		'send'=>array(
			'action'=>'wh_ajax_test_send',
			'n'=>$nonce,
			'errors'=>array(),
			'saved'=>false,
			'loading'=>false,
		),
	),
);

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

	<div class="updated" v-if="forms.send.saved"><p><?php echo __('A test message has been sent! Keep your eyes peeled.', 'well-handled'); ?></p></div>
	<div class="error" v-if="count(forms.send.errors)">
		<p><?php echo __("Darn, the test send didn't work.", 'well-handled'); ?></p>
		<p v-for="error in forms.send.errors">{{error}}</p>
	</div>



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
						<h3 class="hndle"><?php echo __('Mail Settings', 'well-handled'); ?></h3>
						<div class="inside">

							<table class="wh-settings">
								<tbody>
									<tr>
										<th scope="row">
											<label for="settings-send-method"><?php echo __('Method', 'well-handled'); ?></label>
										</th>
										<td>
											<select v-model="forms.settings.send.method" id="settings-send-method">
												<option value="wp_mail"><?php echo __('Default', 'well-handled'); ?></option>
												<option value="smtp">SMTP</option>
												<option value="ses">Amazon SES</option>
												<option value="mandrill">Mandrill</option>
											</select>

											<p class="description" v-if="forms.settings.send.method === 'wp_mail'">
												<?php echo __("By default, WordPress uses the web server to send email. If that works, you're done!", 'well-handled'); ?>
											</p>
											<p class="description" v-else-if="forms.settings.send.method === 'smtp'">
												<?php echo __(
													'SMTP can be used to send *authenticated* email from an actual email address. It works the same way for WordPress as it does for a standalone email program on your desktop or phone; just plug in the necessary information.',
													'well-handled'
												); ?>
											</p>
											<p class="description" v-else-if="forms.settings.send.method === 'mandrill'">
												<?php
												echo sprintf(
													__('This uses the %s API to send messages through your Mandrill account. Template processing is done entirely through the plugin; Mandrill is just given the completed message for sending.', 'well-handled'),
													'<a href="https://mailchimp.com" target="_blank">Mandrill</a>'
												);
												?>
											</p>
											<p class="description" v-else>
												<?php
												echo sprintf(
													__('This uses the %s API to send *authenticated* email through your AWS account. This is usually much cheaper and much faster than SMTP.', 'well-handled'),
													'<a href="https://aws.amazon.com/ses/" target="_blank">Amazon SES</a>'
												);
												?>
											</p>
										</td>
									</tr>

									<tr v-if="forms.settings.send.method === 'smtp'">
										<th scope="row">
											<label for="settings-send-smtp-email"><?php echo __('From Email', 'well-handled'); ?></label>
										</th>
										<td>
											<input type="email" id="settings-send-smtp-email" v-model.trim="forms.settings.send.smtp.email" required placeholder="jane@doe.com" />

											<p class="description"><?php echo __('Most email providers require this to be the same as the login user.', 'well-handled'); ?></p>
										</td>
									</tr>

									<tr v-if="forms.settings.send.method === 'smtp'">
										<th scope="row">
											<label for="settings-send-smtp-name"><?php echo __('From Name', 'well-handled'); ?></label>
										</th>
										<td>
											<input type="text" id="settings-send-smtp-name" v-model.trim="forms.settings.send.smtp.name" required placeholder="Jane Doe" />
										</td>
									</tr>

									<tr v-if="forms.settings.send.method === 'smtp'">
										<th scope="row">
											<label for="settings-send-smtp-user"><?php echo __('SMTP User', 'well-handled'); ?></label>
										</th>
										<td>
											<input type="text" id="settings-send-smtp-user" v-model.trim="forms.settings.send.smtp.user" required placeholder="jane@doe.com" />

											<p class="description"><?php echo __('This is often, but not always, a complete email address.', 'well-handled'); ?></p>
										</td>
									</tr>

									<tr v-if="forms.settings.send.method === 'smtp'">
										<th scope="row">
											<label for="settings-send-smtp-pass"><?php echo __('SMTP Pass', 'well-handled'); ?></label>
										</th>
										<td>
											<input type="password" id="settings-send-smtp-pass" v-model.trim="forms.settings.send.smtp.pass" required />

											<p class="description"><?php
											echo sprintf(
												__('mail Users: not all Google accounts accept user/password logins for SMTP by default. You might need to set up an %s.', 'well-handled'),
												'<a href="https://support.google.com/accounts/answer/185833?hl=en" target="_blank">App Password</a>'
											);
											?></p>
										</td>
									</tr>

									<tr v-if="forms.settings.send.method === 'smtp'">
										<th scope="row">
											<label for="settings-send-smtp-server"><?php echo __('SMTP Server', 'well-handled'); ?></label>
										</th>
										<td>
											<input type="text" id="settings-send-smtp-server" v-model.trim="forms.settings.send.smtp.server" required placeholder="smtp.gmail.com" />
										</td>
									</tr>

									<tr v-if="forms.settings.send.method === 'smtp'">
										<th scope="row">
											<label for="settings-send-smtp-port"><?php echo __('SMTP Port', 'well-handled'); ?></label>
										</th>
										<td>
											<select v-model.number="forms.settings.send.smtp.port" id="settings-send-smtp-port">
												<?php foreach (options::SMTP_PORTS as $v) { ?>
													<option value="<?php echo esc_attr($v); ?>"><?php echo esc_attr($v); ?></option>
												<?php } ?>
											</select>
										</td>
									</tr>

									<tr v-if="forms.settings.send.method === 'smtp'">
										<th scope="row">
											<label for="settings-send-smtp-encryption"><?php echo __('Encryption', 'well-handled'); ?></label>
										</th>
										<td>
											<select v-model="forms.settings.send.smtp.encryption" id="settings-send-smtp-encryption">
												<?php foreach (options::SMTP_ENCRYPTION as $v) { ?>
													<option value="<?php echo esc_attr($v); ?>"><?php echo esc_attr($v); ?></option>
												<?php } ?>
											</select>
										</td>
									</tr>

									<tr v-if="forms.settings.send.method === 'ses'">
										<th scope="row">
											<label for="settings-send-ses-endpoint"><?php echo __('Endpoint', 'well-handled'); ?></label>
										</th>
										<td>
											<select v-model="forms.settings.send.ses.endpoint" id="settings-send-ses-endpoint">
												<?php foreach (options::SES_ENDPOINTS as $k=>$v) { ?>
													<option value="<?php echo esc_attr($k); ?>"><?php echo esc_attr($v); ?></option>
												<?php } ?>
											</select>

											<p class="description"><?php echo __('If your SES endpoint does not support API connections (not all do, unfortunately), use SMTP instead.', 'well-handled'); ?></p>
										</td>
									</tr>

									<tr v-if="forms.settings.send.method === 'ses'">
										<th scope="row">
											<label for="settings-send-ses-email"><?php echo __('From Email', 'well-handled'); ?></label>
										</th>
										<td>
											<input type="email" id="settings-send-ses-email" v-model.trim="forms.settings.send.ses.email" required placeholder="jane@doe.com" />

											<p class="description"><?php echo __('This address must be verified with Amazon for the specific endpoint being used.', 'well-handled'); ?></p>
										</td>
									</tr>

									<tr v-if="forms.settings.send.method === 'ses'">
										<th scope="row">
											<label for="settings-send-ses-name"><?php echo __('From Name', 'well-handled'); ?></label>
										</th>
										<td>
											<input type="text" id="settings-send-ses-name" v-model.trim="forms.settings.send.ses.name" required placeholder="Jane Doe" />
										</td>
									</tr>

									<tr v-if="forms.settings.send.method === 'ses'">
										<th scope="row">
											<label for="settings-send-ses-access_key"><?php echo __('Access Key', 'well-handled'); ?></label>
										</th>
										<td>
											<input type="password" id="settings-send-ses-access_key" v-model.trim="forms.settings.send.ses.access_key" required />
										</td>
									</tr>

									<tr v-if="forms.settings.send.method === 'ses'">
										<th scope="row">
											<label for="settings-send-ses-secret_key"><?php echo __('Secret Key', 'well-handled'); ?></label>
										</th>
										<td>
											<input type="password" id="settings-send-ses-secret_key" v-model.trim="forms.settings.send.ses.secret_key" required />
										</td>
									</tr>

									<tr v-if="forms.settings.send.method === 'mandrill'">
										<th scope="row">
											<label for="settings-send-mandrill-key"><?php echo __('API Key', 'well-handled'); ?></label>
										</th>
										<td>
											<input type="text" id="settings-send-mandrill-key" v-model.trim="forms.settings.send.mandrill.key" required />
										</td>
									</tr>

									<tr v-if="forms.settings.send.method === 'mandrill'">
										<th scope="row">
											<label for="settings-send-mandrill-email"><?php echo __('From Email', 'well-handled'); ?></label>
										</th>
										<td>
											<input type="email" id="settings-send-mandrill-email" v-model.trim="forms.settings.send.mandrill.email" required placeholder="jane@doe.com" />

											<p class="description"><?php echo __('This address must be verified with Mailchimp for sending.', 'well-handled'); ?></p>
										</td>
									</tr>

									<tr v-if="forms.settings.send.method === 'mandrill'">
										<th scope="row">
											<label for="settings-send-mandrill-name"><?php echo __('From Name', 'well-handled'); ?></label>
										</th>
										<td>
											<input type="text" id="settings-send-mandrill-name" v-model.trim="forms.settings.send.mandrill.name" required placeholder="Jane Doe" />
										</td>
									</tr>
								</tbody>
							</table>

						</div>
					</div>

					<p><button type="submit" class="button button-large button-primary" v-bind:disabled="forms.settings.loading"><?php echo __('Save'); ?></button></p>

				</form>

			</div><!--.postbox-container-->

			<!-- Column One -->
			<div class="postbox-container">

				<!-- ==============================================
				TEST SEND
				=============================================== -->
				<div class="postbox">
					<h3 class="hndle"><?php echo __('Test Send'); ?></h3>
					<div class="inside">
						<form name="sendForm" method="post" action="<?php echo admin_url('admin-ajax.php'); ?>" v-on:submit.prevent="sendSubmit">
							<button type="submit" class="button button-large" v-bind:disabled="forms.send.loading"><?php echo __('Test Mail Settings', 'well-handled'); ?></button>

							<p class="description"><?php
							$current_user = wp_get_current_user();
							echo sprintf(
								__('Click the above button to send a test message to %s using the saved settings (hint: if you just now made a change, save that first).', 'well-handled'),
								esc_html($current_user->user_email)
							);
							?></p>
						</form>
					</div>
				</div>

			</div><!--.postbox-container-->

		</div><!--#post-body-->
	</div><!--#poststuff-->
</div><!--.wrap-->
