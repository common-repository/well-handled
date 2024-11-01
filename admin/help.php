<?php
/**
 * Admin: Help
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
use blobfolio\wp\wh\vendor\md;



// Our help files.
$Parsedown = new md\Parsedown();
$help = array(
	'Template/Markup'=>WH_BASE . 'skel/help/template.md',
	'Functions'=>WH_BASE . 'skel/help/function.md',
	'Hooks'=>WH_BASE . 'skel/help/hook.md',
);
$links = array();
foreach ($help as $k=>$v) {
	if (! file_exists($v)) {
		unset($help[$k]);
		continue;
	}

	try {
		$help[$k] = $Parsedown->text(file_get_contents($v));

		// Ready for PrismJS.
		$help[$k] = preg_replace_callback(
			'/<pre>(.*)<\/pre>/sU',
			function($match) {
				$classes = array();

				// Is this PHP?
				if (
					(false !== strpos($match[1], '<?php')) ||
					(false !== strpos($match[1], '&lt;?php'))
				) {
					$classes[] = 'language-php';
				}
				else {
					$classes[] = 'language-handlebars';
				}
				$classes[] = 'line-numbers';

				return '<pre class="' . implode(' ', $classes) . "\">{$match[1]}</pre>";
			},
			$help[$k]
		);

		// Now generate the corresponding links.
		$help[$k] = $help[$k] = preg_replace_callback(
			'/<(h2|h3)>(.*)<\/\\1>/sU',
			function($match) use(&$links, $k) {

				if (! isset($links[$k])) {
					$links[$k] = array();
				}

				$id = sanitize_title($k) . '--' . sanitize_title(str_replace('/', '_', $match[2]));
				$links[$k][] = array(
					'name'=>$match[2],
					'id'=>"#$id",
					'child'=>('h3' === $match[1] && ! empty($links)),
				);

				return "<{$match[1]} id=\"$id\">{$match[2]}</{$match[1]}>";
			},
			$help[$k]
		);

		// Also, build up links.
	} catch (Throwable $e) {
		unset($help[$k]);
	}
}
if (empty($help)) {
	wp_die(__('The reference files are missing.', 'well-handled'), 'Error');
}
$first = array_keys($help);
$first = $first[0];



$data = array(
	'help'=>common\format::array_to_indexed($help),
	'links'=>$links,
	'showingHelp'=>$first,
);

// JSON doesn't appreciate broken UTF.
common\ref\sanitize::utf8($data);
?>
<script>var whData=<?php echo json_encode($data, JSON_HEX_AMP); ?>;</script>
<div class="wrap" id="vue-help" v-cloak>
	<h1><?php echo __('Well-Handled: Help', 'well-handled'); ?></h1>

	<div id="poststuff">
		<div id="post-body" class="metabox-holder wh-columns one-two fixed">

			<!-- Reference -->
			<div class="postbox-container two">

				<!-- navigation for relative stats -->
				<h3 class="nav-tab-wrapper">
					<a v-for="item in help" v-on:click.prevent="showingHelp = item.key" style="cursor:pointer" class="nav-tab" v-bind:class="{'nav-tab-active' : showingHelp === item.key}">{{item.key}}</a>
				</h3>

				<!-- ==============================================
				Reference
				=============================================== -->
				<div v-for="item in help" class="postbox" v-show="showingHelp === item.key">
					<h3 class="hndle">{{item.key}}</h3>
					<div class="inside wh-reference" v-html="item.value"></div>
				</div>

			</div><!--.postbox-container-->

			<!-- Links -->
			<div class="postbox-container one">

				<!-- ==============================================
				LINKS
				=============================================== -->
				<div class="postbox">
					<h3 class="hndle"><?php echo __('Quick Links', 'well-handled'); ?></h3>
					<div class="inside">
						<ul class="wh-reference--links">
							<li class="wh-reference--link" v-for="item in links[showingHelp]" v-bind:class="{'child' : item.child}"><a v-bind:href="item.id" style="cursor:pointer">{{item.name}}</a></li>
						</ul>
					</div>
				</div>
			</div><!--.postbox-container-->

		</div><!--#post-body-->
	</div><!--#poststuff-->

</div><!--.wrap-->
