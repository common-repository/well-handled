<?php
/**
 * Admin: Statistics
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
	FROM `{$wpdb->prefix}wh_messages`
")) {
	$date_max = $wpdb->get_var("
		SELECT DATE(MAX(`date_created`))
		FROM `{$wpdb->prefix}wh_messages`
	");
}
else {
	$date_min = current_time('Y-m-d');
	$date_max = current_time('Y-m-d');
}



$data = array(
	'date_min'=>$date_min,
	'date_max'=>$date_max,
	'forms'=>array(
		'search'=>array(
			'action'=>'wh_ajax_stats',
			'n'=>wp_create_nonce('wh-nonce'),
			'date_min'=>$date_min,
			'date_max'=>$date_max,
			'slice'=>10,
			'errors'=>array(),
			'loading'=>false,
		),
	),
	'ranged'=>false,
	'stats'=>array(),
	'hasStats'=>false,
	'hasClicks'=>false,
	'searched'=>false,
	'showingBar'=>'',
	'showingClicks'=>'',
);

// JSON doesn't appreciate broken UTF.
common\ref\sanitize::utf8($data);
?>
<script>var whData=<?php echo json_encode($data, JSON_HEX_AMP); ?>;</script>
<div class="wrap" id="vue-search" v-cloak>
	<h1><?php echo __('Well-Handled: Stats', 'well-handled'); ?></h1>

	<div class="error" v-for="error in forms.search.errors"><p>{{error}}</p></div>
	<div class="updated" v-if="!searched"><p><?php echo __('The stats are being crunched. Hold tight.', 'well-handled'); ?></p></div>
	<div class="error" v-if="searched && !hasStats"><p><?php echo __('No stats were found for this period.', 'well-handled'); ?></p></div>

	<div id="poststuff">
		<div id="post-body" class="metabox-holder wh-columns one-two fixed" v-if="searched">

			<!-- Results -->
			<div class="postbox-container two" v-if="hasStats">
				<!-- ==============================================
				Period
				=============================================== -->
				<div class="postbox" v-if="hasStats && stats.volume.all.labels.length > 1">
					<h3 class="hndle"><?php echo __('Send Volume', 'well-handled'); ?></h3>
					<div class="inside">
						<chartist
							ratio="ct-major-eleventh"
							type="Line"
							:data="stats.volume.all"
							:options="lineOptions">
						</chartist>
					</div>
				</div>

				<!-- navigation for relative stats -->
				<h3 class="nav-tab-wrapper" v-if="hasStats && !ranged">
					<a v-on:click.prevent="showBar('this_month')" style="cursor:pointer" class="nav-tab" v-bind:class="{'nav-tab-active' : showingBar === 'this_month'}" v-if="stats.volume.this_month.labels.length > 0"><?php echo __('This Month', 'well-handled'); ?></a>

					<a v-on:click.prevent="showBar('this_year')" style="cursor:pointer" class="nav-tab" v-bind:class="{'nav-tab-active' : showingBar === 'this_year'}" v-if="stats.volume.this_year.labels.length > 0"><?php echo __('This Year', 'well-handled'); ?></a>

					<a v-on:click.prevent="showBar('years')" style="cursor:pointer" class="nav-tab" v-bind:class="{'nav-tab-active' : showingBar === 'years'}" v-if="stats.volume.years.labels.length > 1"><?php echo __('Year to Year', 'well-handled'); ?></a>
				</h3>

				<!-- ==============================================
				Year to Year
				=============================================== -->
				<div class="postbox" v-show="showingBar === 'years'" v-if="hasStats && !ranged && stats.volume.years.labels.length > 1">
					<h3 class="hndle"><?php echo __('Sent by Year', 'well-handled'); ?></h3>
					<div class="inside">
						<chartist
							ratio="ct-major-twelfth"
							type="Bar"
							:data="stats.volume.years"
							:options="barOptions">
						</chartist>
					</div>
				</div>

				<!-- ==============================================
				This Year
				=============================================== -->
				<div class="postbox" v-show="showingBar === 'this_year'" v-if="hasStats && !ranged && stats.volume.this_year.labels.length > 0">
					<h3 class="hndle"><?php echo __('Sent This Year', 'well-handled'); ?></h3>
					<div class="inside">
						<chartist
							ratio="ct-major-twelfth"
							type="Bar"
							:data="stats.volume.this_year"
							:options="barOptions">
						</chartist>
					</div>
				</div>

				<!-- ==============================================
				This Month
				=============================================== -->
				<div class="postbox" v-show="showingBar === 'this_month'" v-if="hasStats && !ranged && stats.volume.this_month.labels.length > 0">
					<h3 class="hndle"><?php echo __('Sent This Month', 'well-handled'); ?></h3>
					<div class="inside">
						<chartist
							ratio="ct-major-twelfth"
							type="Bar"
							:data="stats.volume.this_month"
							:options="barOptions">
						</chartist>
					</div>
				</div>

				<!-- ==============================================
				Clicks Stats
				=============================================== -->
				<!-- navigation for relative stats -->
				<h3 class="nav-tab-wrapper" v-if="hasClicks">
					<a v-on:click.prevent="showingClicks='template'" style="cursor:pointer" class="nav-tab" v-bind:class="{'nav-tab-active' : showingClicks === 'template'}" v-if="stats.clicks.template.length > 0"><?php echo __('Template', 'well-handled'); ?></a>

					<a v-on:click.prevent="showingClicks='subject'" style="cursor:pointer" class="nav-tab" v-bind:class="{'nav-tab-active' : showingClicks === 'subject'}" v-if="stats.clicks.subject.length > 0"><?php echo __('Subject', 'well-handled'); ?></a>

					<a v-on:click.prevent="showingClicks='domain'" style="cursor:pointer" class="nav-tab" v-bind:class="{'nav-tab-active' : showingClicks === 'domain'}" v-if="stats.clicks.domain.length > 0"><?php echo __('Domain', 'well-handled'); ?></a>

					<a v-on:click.prevent="showingClicks='url'" style="cursor:pointer" class="nav-tab" v-bind:class="{'nav-tab-active' : showingClicks === 'url'}" v-if="stats.clicks.url.length > 0"><?php echo __('URL', 'well-handled'); ?></a>
				</h3>

				<div class="postbox" v-if="hasClicks && showingClicks === 'template'">
					<h3 class="hndle"><?php echo __('Clicks By Template', 'well-handled'); ?></h3>
					<div class="inside">
						<table class="wh-clicks">
							<thead>
								<tr>
									<th><?php echo __('Template', 'well-handled'); ?></th>
									<th><?php echo __('Clicks', 'well-handled'); ?></th>
								</tr>
							</thead>
							<tbody>
								<tr v-for="item in stats.clicks.template">
									<td>{{item.key}}</td>
									<td>{{item.value}}</td>
								</tr>
							</tbody>
						</table>
					</div>
				</div>

				<div class="postbox" v-if="hasClicks && showingClicks === 'subject'">
					<h3 class="hndle"><?php echo __('Clicks By Email Subject', 'well-handled'); ?></h3>
					<div class="inside">
						<table class="wh-clicks">
							<thead>
								<tr>
									<th><?php echo __('Subject', 'well-handled'); ?></th>
									<th><?php echo __('Clicks', 'well-handled'); ?></th>
								</tr>
							</thead>
							<tbody>
								<tr v-for="item in stats.clicks.subject">
									<td>{{item.key}}</td>
									<td>{{item.value}}</td>
								</tr>
							</tbody>
						</table>
					</div>
				</div>

				<div class="postbox" v-if="hasClicks && showingClicks === 'domain'">
					<h3 class="hndle"><?php echo __('Clicks By Domain', 'well-handled'); ?></h3>
					<div class="inside">
						<table class="wh-clicks">
							<thead>
								<tr>
									<th><?php echo __('Domain', 'well-handled'); ?></th>
									<th><?php echo __('Clicks', 'well-handled'); ?></th>
								</tr>
							</thead>
							<tbody>
								<tr v-for="item in stats.clicks.domain">
									<td>{{item.key}}</td>
									<td>{{item.value}}</td>
								</tr>
							</tbody>
						</table>
					</div>
				</div>

				<div class="postbox" v-if="hasClicks && showingClicks === 'url'">
					<h3 class="hndle"><?php echo __('Clicks By URL', 'well-handled'); ?></h3>
					<div class="inside">
						<table class="wh-clicks">
							<thead>
								<tr>
									<th><?php echo __('URL', 'well-handled'); ?></th>
									<th><?php echo __('Clicks', 'well-handled'); ?></th>
								</tr>
							</thead>
							<tbody>
								<tr v-for="item in stats.clicks.url">
									<td>{{item.key}}</td>
									<td>{{item.value}}</td>
								</tr>
							</tbody>
						</table>
					</div>
				</div>

			</div><!--.postbox-container-->

			<!-- Search -->
			<div class="postbox-container one">

				<!-- ==============================================
				SEARCH FORM
				=============================================== -->
				<div class="postbox">
					<h3 class="hndle"><?php echo __('Search', 'well-handled'); ?></h3>
					<div class="inside">
						<form name="searchForm" method="post" action="<?php echo admin_url('admin-ajax.php'); ?>" v-on:submit.prevent="searchSubmit">
							<table class="wh-settings narrow">
								<tbody>
									<tr>
										<th scope="row"><label for="search-date_min"><?php echo __('From', 'well-handled'); ?></label></th>
										<td>
											<input type="date" id="search-date_min" v-model="forms.search.date_min" required v-bind:min="date_min" v-bind:max="date_max" />
										</td>
									</tr>
									<tr>
										<th scope="row"><label for="search-date_max"><?php echo __('To', 'well-handled'); ?></label></th>
										<td>
											<input type="date" id="search-date_max" v-model="forms.search.date_max" required v-bind:min="date_min" v-bind:max="date_max" />
										</td>
									</tr>
									<tr>
										<th scope="row"><label for="search-slice"><?php echo __('Limit Lists', 'well-handled'); ?></label></th>
										<td>
											<input type="number" id="search-slice" v-model.number="forms.search.slice" required min="5" max="100" />
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



				<!-- ==============================================
				BREAKDOWN
				=============================================== -->
				<div class="postbox" v-if="hasStats">
					<h3 class="hndle"><?php echo __('Breakdown', 'well-handled'); ?></h3>
					<div class="inside">
						<table class="wh-meta natural">
							<tbody>
								<tr>
									<th scope="row"><?php echo __('Period', 'well-handled'); ?></th>
									<td>
										{{stats.period}}
										<span v-if="stats.period === 1"><?php echo __('day', 'well-handled'); ?></span>
										<span v-else><?php echo __('days', 'well-handled'); ?></span>
									</td>
								</tr>
								<tr>
									<th scope="row"><?php echo __('Total Sent', 'well-handled'); ?></th>
									<td>
										{{stats.sent.total}}
									</td>
								</tr>
								<tr>
									<th scope="row"><?php echo __('Daily Avg', 'well-handled'); ?></th>
									<td>
										{{stats.sent.avg}}
									</td>
								</tr>
								<tr>
									<th scope="row"><?php echo __('Recipients', 'well-handled'); ?><a href="#footnote-1" class="wh-footnote-link">*</a></th>
									<td>
										{{stats.sent.recipients}}
									</td>
								</tr>
								<tr>
									<th scope="row"><?php echo __('Open Rate', 'well-handled'); ?><a href="#footnote-2" class="wh-footnote-link">*</a></th>
									<td>
										{{stats.sent.open_rate}}%
									</td>
								</tr>
								<tr v-if="stats.clicks.total > 0">
									<th scope="row"><?php echo __('Total Clicks', 'well-handled'); ?></th>
									<td>
										{{stats.clicks.total}}
									</td>
								</tr>
								<tr v-if="stats.clicks.total > 0">
									<th scope="row"><?php echo __('Avg Clicks', 'well-handled'); ?></th>
									<td>
										{{stats.clicks.avg}}/email
									</td>
								</tr>
								<tr>
									<th scope="row"><?php echo __('Build Time', 'well-handled'); ?><a href="#footnote-3" class="wh-footnote-link">*</a></th>
									<td>
										{{stats.compilation_time.avg}} <?php echo __('seconds', 'well-handled'); ?>
									</td>
								</tr>
								<tr>
									<th scope="row"><?php echo __('Send Time', 'well-handled'); ?><a href="#footnote-4" class="wh-footnote-link">*</a></th>
									<td>
										{{stats.execution_time.avg}} <?php echo __('seconds', 'well-handled'); ?>
									</td>
								</tr>
							</tbody>
						</table>
					</div>
				</div><!--breakdown-->



				<!-- ==============================================
				Open Rate
				=============================================== -->
				<div class="postbox" v-if="hasStats">
					<h3 class="hndle"><?php echo __('Open Rate', 'well-handled'); ?></h3>
					<div class="inside">
						<chartist
							ratio="ct-square"
							type="Pie"
							:data="stats.sent.opened"
							:options="pieOptions">
						</chartist>
					</div>
				</div>



				<!-- ==============================================
				SEND METHOD
				=============================================== -->
				<div class="postbox" v-if="hasStats && stats.sent.method.labels.length > 1">
					<h3 class="hndle"><?php echo __('Send Methods', 'well-handled'); ?></h3>
					<div class="inside">
						<chartist
							ratio="ct-square"
							type="Pie"
							:data="stats.sent.method"
							:options="pieOptions">
						</chartist>
					</div>
				</div>



				<!-- ==============================================
				TEMPLATES
				=============================================== -->
				<div class="postbox" v-if="hasStats && stats.sent.template.labels.length > 1">
					<h3 class="hndle"><?php echo __('Templates', 'well-handled'); ?></h3>
					<div class="inside">
						<chartist
							ratio="ct-square"
							type="Pie"
							:data="stats.sent.template"
							:options="pieOptions">
						</chartist>
					</div>
				</div>



				<!-- ==============================================
				FOOTNOTES
				=============================================== -->
				<div class="postbox" v-if="hasStats">
					<h3 class="hndle"><?php echo __('Footnotes', 'well-handled'); ?></h3>
					<div class="inside">
						<ol>
							<li id="footnote-1"><?php echo __('CC and BCC recipients are not counted.', 'well-handled'); ?></li>
							<li id="footnote-2"><?php echo __('For privacy reasons, it is not always possible to track when a person has opened an email. This metric is likely under-counted.', 'well-handled'); ?></li>
							<li id="footnote-3"><?php echo __('This is the amount of time it takes to generate the raw email message (compiling the template, swapping data, etc.).', 'well-handled'); ?></li>
							<li id="footnote-4"><?php echo __('This is the amount of time it takes to hand off the raw email message to the outgoing mailserver (network lookups, authentication, etc.).', 'well-handled'); ?></li>
						</ol>
					</div>
				</div>

			</div><!--.postbox-container-->

		</div><!--#post-body-->
	</div><!--#poststuff-->

</div><!--.wrap-->
