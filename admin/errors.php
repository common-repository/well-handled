<?php
/**
 * Admin: Errors
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



use blobfolio\wp\wh\vendor\common;



// Find the boundaries.
global $wpdb;
if (null !== $date_min = $wpdb->get_var("
	SELECT DATE(MIN(`date_created`))
	FROM `{$wpdb->prefix}wh_message_errors`
")) {
	$date_max = $wpdb->get_var("
		SELECT DATE(MAX(`date_created`))
		FROM `{$wpdb->prefix}wh_message_errors`
	");
}
else {
	$date_min = current_time('Y-m-d');
	$date_max = current_time('Y-m-d');
}



$orders = array(
	'date_created'=>'Date',
);



$data = array(
	'forms'=>array(
		'search'=>array(
			'action'=>'wh_ajax_errors',
			'n'=>wp_create_nonce('wh-nonce'),
			'date_min'=>$date_min,
			'date_max'=>$date_max,
			'page'=>0,
			'pageSize'=>10,
			'orderby'=>'date_created',
			'order'=>'desc',
			'errors'=>array(),
			'loading'=>false,
		),
		'delete'=>array(
			'action'=>'wh_ajax_error_delete',
			'n'=>wp_create_nonce('wh-nonce'),
			'error_id'=>0,
			'errors'=>array(),
			'loading'=>false,
			'deleted'=>false,
		),
		'retry'=>array(
			'action'=>'wh_ajax_error_retry',
			'n'=>wp_create_nonce('wh-nonce'),
			'error_id'=>0,
			'errors'=>array(),
			'loading'=>false,
			'deleted'=>false,
		),
	),
	'modal'=>false,
	'results'=>array(
		'page'=>0,
		'pages'=>0,
		'total'=>0,
		'items'=>array(),
	),
	'searched'=>false,
);

// JSON doesn't appreciate broken UTF.
common\ref\sanitize::utf8($data);
?>
<script>var whData=<?php echo json_encode($data, JSON_HEX_AMP); ?>;</script>
<div class="wrap" id="vue-search" v-cloak>
	<h1><?php echo __('Well-Handled: Send Errors', 'well-handled'); ?></h1>

	<div class="error" v-for="error in forms.search.errors"><p>{{error}}</p></div>
	<div class="error" v-for="error in forms.delete.errors"><p>{{error}}</p></div>
	<div class="error" v-for="error in forms.retry.errors"><p>{{error}}</p></div>

	<div class="updated" v-if="forms.delete.deleted"><p><?php echo __('The error has been deleted.', 'well-handled'); ?></p></div>

	<div class="updated" v-if="forms.retry.deleted"><p><?php echo __('The message worked this time!', 'well-handled') . ' ' . __('The error has been deleted.', 'well-handled'); ?></p></div>

	<div class="updated" v-if="!searched"><p><?php echo __('The errors are being fetched. Hold tight.', 'well-handled'); ?></p></div>

	<div id="poststuff">
		<div id="post-body" class="metabox-holder wh-columns one-two" v-if="searched">

			<!-- Results -->
			<div class="postbox-container two">
				<div class="postbox">
					<h3 class="hndle">
						<?php echo __('Send Errors', 'well-handled'); ?>
						<span v-if="results.total">({{results.total}})</span>
					</h3>
					<div class="inside">
						<p v-if="!results.total"><?php echo __('No errors matched the search. Sorry.', 'well-handled'); ?></p>

						<table v-if="results.total" class="wh-results">
							<thead>
								<tr>
									<th><?php echo __('Date', 'well-handled'); ?></th>
									<th><?php echo __('Error', 'well-handled'); ?></th>
									<th><?php echo __('Message', 'well-handled'); ?></th>
								</tr>
							</thead>
							<tbody>
								<tr v-for="item in results.items">
									<td>{{item.date_created}}</td>
									<td class="wh-fg-orange">
										<strong>{{item.error.code}}</strong><br>
										{{item.error.message}}

										<p>
											<button v-if="item.message.template_slug" v-on:click.prevent="retryError(item.error_id)" v-bind:disabled="forms.delete.loading || forms.retry.loading || forms.search.loading" type="button" class="button button-small button-primary">Retry</button>

											<button v-on:click.prevent="deleteError(item.error_id)" v-bind:disabled="forms.delete.loading || forms.retry.loading || forms.search.loading" type="button" class="button button-small">Delete</button>
										</p>
									</td>
									<td>
										<table class="wh-meta">
											<tbody>
												<tr v-if="item.message.to">
													<th scope="row"><?php echo __('To', 'well-handled'); ?></th>
													<td>{{item.message.to}}</td>
												</tr>
												<tr v-if="item.message.subject">
													<th scope="row"><?php echo __('Subject', 'well-handled'); ?></th>
													<td>{{item.message.subject}}</td>
												</tr>
												<tr v-if="item.message.headers.length">
													<th scope="row"><?php echo __('Headers', 'well-handled'); ?></th>
													<td>
														<div class="wh-code" v-for="header in item.message.headers">{{header}}</div>
													</td>
												</tr>
												<tr v-if="item.message.attachments.length">
													<th scope="row"><?php echo __('Attachments', 'well-handled'); ?></th>
													<td>
														<div class="wh-code" v-for="attachment in item.message.attachments">{{attachment.name}}</div>
													</td>
												</tr>
												<tr v-if="item.message.message">
													<th scope="row"><?php echo __('Message', 'well-handled'); ?></th>
													<td><a v-on:click.prevent="getMessage(item.message.message)" class="button button-small"><?php echo __('View', 'well-handled'); ?></a></td>
												</tr>
											</tbody>
										</table>
									</td>
								</tr>
							</tbody>
						</table>

						<nav class="wh-pagination" v-if="results.pages > 0">
							<a v-bind:disabled="forms.search.loading || results.page === 0" v-on:click.prevent="!forms.search.loading && pageSubmit(-1)" class="wh-pagination--link">
								<span class="dashicons dashicons-arrow-left-alt2"></span>
								<?php echo __('Back', 'well-handled'); ?>
							</a>

							<span class="wh-pagination--current wh-fg-grey">{{results.page + 1}} / {{results.pages + 1}}</span>

							<a v-bind:disabled="forms.search.loading || results.page === results.pages" v-on:click.prevent="!forms.search.loading && pageSubmit(1)" class="wh-pagination--link">
								<?php echo __('Next', 'well-handled'); ?>
								<span class="dashicons dashicons-arrow-right-alt2"></span>
							</a>
						</nav>
					</div>
				</div>
			</div><!--.postbox-container-->

			<!-- Search -->
			<div class="postbox-container one">

				<div class="postbox">
					<h3 class="hndle"><?php echo __('Search', 'well-handled'); ?></h3>
					<div class="inside">
						<form name="searchForm" method="post" action="<?php echo admin_url('admin-ajax.php'); ?>" v-on:submit.prevent="searchSubmit">
							<table class="wh-settings narrow">
								<tbody>
									<tr>
										<th scope="row"><label for="search-date_min"><?php echo __('From', 'well-handled'); ?></label></th>
										<td>
											<input type="date" id="search-date_min" v-model="forms.search.date_min" required min="<?php echo $date_min; ?>" max="<?php echo $date_max; ?>" />
										</td>
									</tr>
									<tr>
										<th scope="row"><label for="search-date_max"><?php echo __('To', 'well-handled'); ?></label></th>
										<td>
											<input type="date" id="search-date_max" v-model="forms.search.date_max" required min="<?php echo $date_min; ?>" max="<?php echo $date_max; ?>" />
										</td>
									</tr>
									<tr>
										<th scope="row"><label for="search-pageSize"><?php echo __('Items/Page', 'well-handled'); ?></label></th>
										<td>
											<input type="number" id="search-pageSize" v-model.number="forms.search.pageSize" min="1" max="50" step="1" />
										</td>
									</tr>
									<tr>
										<th scope="row"><label for="search-pageSize"><?php echo __('Order By', 'well-handled'); ?></label></th>
										<td>
											<select v-model="forms.search.orderby">
												<?php foreach ($orders as $k=>$v) { ?>
													<option value="<?php echo $k; ?>"><?php echo $v; ?></option>
												<?php } ?>
											</select>
											<select v-model="forms.search.order">
												<option value="asc"><?php echo __('ASC', 'well-handled'); ?></option>
												<option value="desc"><?php echo __('DESC', 'well-handled'); ?></option>
											</select>
										</td>
									</tr>
									<tr>
										<th scope="row">&nbsp;</th>
										<td>
											<button type="submit" class="button button-large button-primary" v-bind:disabled="forms.search.loading"><?php echo __('Search', 'well-handled'); ?></button>
										</td>
									</tr>
								</tbody>
							</table>
						</form>
					</div>
				</div>

			</div><!--.postbox-container-->

		</div><!--#post-body-->
	</div><!--#poststuff-->

	<!-- Message Preview -->
	<transition name="fade">
		<div v-if="modal" id="wh-modal" class="wh-modal">
			<span v-on:click.prevent="modal=false" id="wh-modal--close" class="dashicons dashicons-no"></span>

			<div id="wh-modal--inner">
				<div id="wh-modal--inner--message">
					<iframe id="wh-modal--message" src="about:blank" frameborder="0" allowfullscreen></iframe>
				</div>
			</div>
		</div>
	</transition>
</div><!--.wrap-->
