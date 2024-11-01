<?php
/**
 * Well-Handled message template.
 *
 * @package Well-Handled
 * @author  Blobfolio, LLC <hello@blobfolio.com>
 */

namespace blobfolio\wp\wh;

use blobfolio\wp\wh\vendor\common;

class template {

	const TEMPLATE = array(
		'slug'=>'',
		'raw'=>'',
		'title'=>'',
	);

	// Template build options.
	const OPTIONS = array(
		'collapse_whitespace'=>true,
		'css_inline'=>true,
		'debug'=>false,
		'link_target'=>'_blank',
		'linkify'=>false,
		'snippet'=>false,
		'strip_comments'=>true,
		'strip_style_tags'=>true,
		'utm'=>false,
		'utm_data'=>array(
			'utm_campaign'=>'',
			'utm_medium'=>'email',
			'utm_source'=>'transactional',
			'utm_term'=>'',
			'utm_content'=>'',
		),
		'utm_local_only'=>true,
		'validate_html'=>true,
	);

	// HTML Validation Config.
	const HTML_VALIDATION = array(
		'base_url'=>'',
		'cdata'=>1,
		'deny_attribute'=>'on*',
		'safe'=>1,
		'schemes'=>'href:mailto,http,https,tel;src:http,https',
		'comment'=>2,
	);

	protected $template;
	protected $log;
	protected $log_start;
	protected $build = array();

	protected static $_instances = array();
	protected static $slugs;


	// ---------------------------------------------------------------------
	// Slugs
	// ---------------------------------------------------------------------

	/**
	 * Get Slugs
	 *
	 * @return array Slugs.
	 */
	public static function get_slugs() {
		if (null === static::$slugs) {
			global $wpdb;
			static::$slugs = array();
			$dbResult = $wpdb->get_results("
				SELECT DISTINCT `post_name`
				FROM `{$wpdb->prefix}posts`
				WHERE
					`post_type`='wh-template' AND
					`post_status`='publish'
				ORDER BY `post_name` ASC
			", \ARRAY_A);
			if (isset($dbResult[0])) {
				foreach ($dbResult as $Row) {
					static::$slugs[] = $Row['post_name'];
				}
			}
		}

		return static::$slugs;
	}

	/**
	 * Is Slug?
	 *
	 * @param string $slug Slug.
	 * @return bool True/false
	 */
	public static function is_slug($slug=null) {
		static::get_slugs();
		common\ref\cast::to_string($slug, true);
		common\ref\sanitize::whitespace($slug);
		return $slug && \in_array($slug, static::$slugs, true);
	}

	/**
	 * A/B Testing.
	 *
	 * @param string|array $slug Slug.
	 * @return string|bool Slug or false.
	 */
	public static function ab($slug=null) {
		common\ref\cast::to_array($slug);
		foreach ($slug as $k=>$v) {
			common\ref\cast::to_string($slug[$k], true);
			common\ref\sanitize::whitespace($slug[$k]);
		}
		static::get_slugs();

		// Tease out valid entries.
		$slugs = \array_intersect(static::$slugs, $slug);
		if (empty($slugs)) {
			return false;
		}

		return $slugs[\array_rand($slugs)];
	}

	// --------------------------------------------------------------------- end slugs



	// ---------------------------------------------------------------------
	// Init
	// ---------------------------------------------------------------------

	/**
	 * Pre-Construct
	 *
	 * Cache static objects locally for better performance.
	 *
	 * @param mixed $slug Slug.
	 * @param bool $refresh Refresh.
	 * @return object Instance.
	 */
	public static function get($slug=null, $refresh=false) {
		// Figure out whether we're making a new instance or not.
		if (! isset(self::$_instances[static::class])) {
			self::$_instances[static::class] = array();
		}

		$slug = static::ab($slug);
		if (! $slug) {
			return new static();
		}

		// Get the right object.
		if ($refresh || ! isset(self::$_instances[static::class][$slug])) {
			self::$_instances[static::class][$slug] = new static($slug);
			if (! self::$_instances[static::class][$slug]->is_template()) {
				unset(self::$_instances[static::class][$slug]);
				return new static();
			}
		}

		return self::$_instances[static::class][$slug];
	}

	/**
	 * Constructor
	 *
	 * @param string|array $slug Slug.
	 * @return bool true/false.
	 */
	public function __construct($slug=null) {
		global $wpdb;

		// Reset.
		$this->template = static::TEMPLATE;

		// Pluck a template.
		if (false === ($slug = static::ab($slug))) {
			return false;
		}

		// And get the content.
		$dbResult = $wpdb->get_results("
			SELECT
				`post_content` AS `raw`,
				`post_name` AS `slug`,
				`post_title` AS `title`
			FROM `{$wpdb->prefix}posts`
			WHERE
				`post_type`='wh-template' AND
				`post_status`='publish' AND
				`post_name`='" . \esc_sql($slug) . "'
		");
		if (! isset($dbResult[0])) {
			return false;
		}
		$Row = common\data::array_pop_top($dbResult);
		$this->template = common\data::parse_args($Row, static::TEMPLATE);

		return true;
	}

	/**
	 * Is Template?
	 *
	 * @return bool True/false
	 */
	public function is_template() {
		return isset($this->template['raw']) && $this->template['raw'];
	}

	/**
	 * Magic Getter
	 *
	 * @param string $method Method name.
	 * @param mixed $args Arguments.
	 * @return mixed Variable.
	 * @throws \Exception Invalid method.
	 */
	public function __call($method, $args) {
		\preg_match_all('/^get_(.+)$/', $method, $matches);
		if (
			! empty($matches[0]) &&
			\array_key_exists($matches[1][0], static::TEMPLATE)
		) {
			$variable = $matches[1][0];
			$value = $this->is_template() ? $this->template[$variable] : static::TEMPLATE[$variable];

			return $value;
		}

		throw new \Exception(\sprintf(\__('The required method "%s" does not exist for %s', 'well-handled'), $method, static::class));
	}

	/**
	 * Get Build Log
	 *
	 * @return array Build details.
	 */
	public function get_log() {
		return $this->log;
	}

	// --------------------------------------------------------------------- end init



	// ---------------------------------------------------------------------
	// Processing
	// ---------------------------------------------------------------------

	/**
	 * Logging
	 *
	 * Record the build history so we can log or print it.
	 *
	 * @param mixed $action Action.
	 * @param bool $state Start or stop log.
	 * @return array|bool Log or true/false.
	 */
	protected function debug($action='', $state=null) {

		$func = \debug_backtrace();
		$func = $func[1]['function'] . '()';
		$time = \current_time('Y-m-d H:i:s');

		// Starting.
		if (null === $this->log_start || true === $state) {
			$this->log = array(
				\sprintf('[%s] %s ' . \__('Processing started.', 'well-handled'), $time, $func),
			);
			$this->log_start = \microtime(true);
		}

		// An error.
		if (\is_wp_error($action)) {
			$action = $action->get_error_message();
			$this->log[] = \sprintf('[%s] [' . \__('error', 'well-handled') . '] %s %s.', $time, $func, $action);
			\error_log(\sprintf('[%s] ' . \__('Well-Handled %s exception: %s', 'well-handled'), $time, $func, $action));
		}
		else {
			common\ref\cast::to_string($action, true);
			if ($action) {
				$this->log[] = \sprintf('[%s] %s %s.', $time, $func, $action);
			}
		}

		// End it.
		if (false === $state) {
			$end = \microtime(true);
			$this->log[] = \sprintf('[%s] %s ' . \__('Processing completed in %f seconds.', 'well-handled'), $time, $func, \round($end - $this->log_start, 3));
			$tmp = array(
				'time'=>\round($end - $this->log_start, 5),
				'log'=>$this->log,
			);

			$this->log = $this->log_start = null;

			return $tmp;
		}

		return true;
	}

	/**
	 * Build
	 *
	 * Compile a template with data.
	 *
	 * @param array $data Data.
	 * @param array $options Options.
	 * @param string $raw Pass raw template.
	 * @return array|bool Build details or false.
	 */
	public function make($data=null, $options=null, $raw=null) {
		if (! $this->is_template()) {
			if (null !== $raw) {
				common\ref\cast::to_string($raw, true);
				$this->template['slug'] = 'PREVIEW';
				$this->template['title'] = 'PREVIEW';
				$this->template['raw'] = $raw;
			}
			else {
				return false;
			}
		}
		$this->debug(\__('Working template', 'well-handled') . ": {$this->template['slug']}", true);

		// Run shortcodes first.
		$raw = common\cast::to_string(\do_shortcode($this->template['raw']), true);

		// Preprocess filters.
		$content = \apply_filters('wh_preprocess_template', $raw, $this->template['slug']);
		common\ref\cast::to_string($content, true);
		if (! $content) {
			$this->debug(new \WP_Error(
				\__('Build Failed', 'well-handled'),
				 'wh_preprocess_template ' . \__('filter(s)', 'well-handled')
			));
			return false;
		}

		// Parse options.
		$defaults = static::OPTIONS;
		$defaults['debug'] = \defined('WP_DEBUG') && \WP_DEBUG;
		$defaults['utm_campaign'] = $this->template['slug'];
		$options = common\data::parse_args($options, $defaults);

		// Disable UTM if none of the options exist.
		if ($options['utm'] && empty(\array_filter($options['utm_data'], 'strlen'))) {
			$options['utm'] = false;
		}

		$this->debug(\__('Parsed options.', 'well-handled'));

		// Handlebars.
		if (\is_array($data)) {
			try {
				$extraHelpers = new vendor\Handlebars\Helpers(new lib\handlebars());
				$handlebars = new vendor\Handlebars\Handlebars();
				$handlebars->getHelpers()->addHelpers($extraHelpers);

				$content = $handlebars->render($content, $data);
				common\ref\cast::to_string($content, true);

				$this->debug('Parsed handlebars.');
			} catch (\Throwable $e) {
				$this->debug(new \WP_Error(
					\__('Build Failed', 'well-handled'),
					'[handlebars] ' . $e->getMessage()
				));
				return false;
			}
		}

		// Make link-like things clickable? Do this early as later options might
		// alter them.
		if ($options['linkify']) {
			common\ref\format::links($content);
		}

		// Process as a snippet?
		$dom_html_args = 0;
		if ($options['snippet'] && \defined('LIBXML_HTML_NOIMPLIED') && \defined('LIBXML_HTML_NODEFDTD')) {
			$dom_html_args = \LIBXML_HTML_NOIMPLIED | \LIBXML_HTML_NODEFDTD;
		}

		// Load up the DOM.
		$dom = static::html_import($content, $dom_html_args);

		if ($options['validate_html']) {
			if (false === (static::validate_html($content, $dom, $dom_html_args))) {
				$this->debug(new \WP_Error(
					\__('Build Failed', 'well-handled'),
					\__('Invalid HTML', 'well-handled')
				));
				return false;
			}
			else {
				$this->debug(\__('HTML validated.', 'well-handled'));
			}
		}

		// Link manipulation?
		if ($options['utm'] || $options['link_target']) {
			// Build a UTM query.
			$utm_query = false;
			if ($options['utm']) {
				$utm_query = array();
				foreach ($options['utm_data'] as $k=>$v) {
					common\ref\sanitize::whitespace($v);
					if ($v) {
						$utm_query[] = \esc_attr("$k=$v");
					}
				}
				$utm_query = ! empty($utm_query) ? \implode('&', $utm_query) : false;
			}

			try {
				$links = $dom->getElementsByTagName('a');
				if ($links->length) {
					$length = $links->length;
					for ($x = 0; $x < $length; ++$x) {
						$url = $links->item($x)->getAttribute('href');
						if (\preg_match('/^(mailto|tel):/ui', $url, $tmp)) {
							switch (\strtolower($tmp[1] ?? '')) {
							case 'mailto':
								$url = \trim(\substr($url, 7));
								$url = \strtolower(\sanitize_email($url));
								if ($url) {
									$url = "mailto:$url";
								}
								else {
									$url = '';
								}

								break;
							case 'tel':
								$url = \trim(\substr($url, 4));
								$url = \preg_replace('/[^\d.+]/ui', '', $url);
								if ($url) {
									$url = "tel:$url";
								}
								else {
									$url = '';
								}

								break;
							}
						}
						else {
							common\ref\sanitize::url($url);
						}

						// Append UTM?
						if (
							false !== $utm_query &&
							$url &&
							\preg_match('/^https?/i', $url) &&
							(! $options['utm_local_only'] || static::is_site_url($url))
						) {
							$parsed = common\mb::parse_url($url);
							if (isset($parsed['query']) && $parsed['query']) {
								$parsed['query'] .= "&$utm_query";
							}
							else {
								$parsed['query'] = $utm_query;
							}

							$url = common\file::unparse_url($parsed);
						}

						// Update the URL.
						if (! $url) {
							$url = '#';
						}
						$links->item($x)->setAttribute('href', $url);

						// Update the target.
						if ($options['link_target']) {
							$links->item($x)->setAttribute('target', $options['link_target']);
						}
					}

					$this->debug(\__('Parsed links.', 'well-handled'));
					$content = static::html_export($dom);
				}
			} catch (\Throwable $e) {
				$this->debug(new \WP_Error(
					\__('Build Failed', 'well-handled'),
					'[links] ' . $e->getMessage()
				));
				return false;
			}
		}// End links.

		if ($options['strip_comments']) {
			try {
				$xpath = new \DOMXPath($dom);
				foreach ($xpath->query('//comment()') as $comment) {
					$comment->parentNode->removeChild($comment);
				}
				$content = static::html_export($dom);
				$this->debug(\__('Comments removed.', 'well-handled'));
			} catch (\Throwable $e) {
				$this->debug(new \WP_Error(
					\__('Build Failed', 'well-handled'),
					'[comments] ' . $e->getMessage()
				));
				return false;
			}

			if (! $content) {
				$this->debug(new \WP_Error(
					\__('Build Failed', 'well-handled'),
					\__('Comments.', 'well-handled')
				));
				return false;
			}
		}

		if ($options['css_inline']) {
			try {
				// Are there any styles to inline?
				$tmp = $dom->getElementsByTagName('style');
				$css = '';
				if ($tmp->length) {
					foreach ($tmp as $t) {
						$css .= "{$t->nodeValue}\n";
					}
				}

				// Now we can inline everything.
				if ($css) {
					$cssToInlineStyles = new vendor\TijsVerkoyen\CssToInlineStyles\CssToInlineStyles();
					$content = $cssToInlineStyles->convert($content, $css);

					if (! $content) {
						$this->debug(new \WP_Error(
							\__('Build Failed', 'well-handled'),
							\__('CSS inlining.', 'well-handled')
						));
						return false;
					}

					// Reload the DOM.
					$dom = static::html_import($content, $dom_html_args);
					$this->debug('Inlined CSS.');
				}
			} catch (\Throwable $e) {
				$this->debug(new \WP_Error(
					\__('Build Failed', 'well-handled'),
					'[CSS] ' . $e->getMessage()
				));
				return false;
			}
		}

		if ($options['strip_style_tags']) {
			try {
				$styles = $dom->getElementsByTagName('style');
				while ($styles->length) {
					$styles->item(0)->parentNode->removeChild($styles->item(0));
				}

				if (! $content) {
					$this->debug(new \WP_Error(
						\__('Build Failed', 'well-handled'),
						\__('Removing styles.', 'well-handled')
					));
					return false;
				}

				$content = static::html_export($dom);
				$this->debug(\__('Removed style tags.', 'well-handled'));
			} catch (\Throwable $e) {
				$this->debug(new \WP_Error(
					\__('Build Failed', 'well-handled'),
					'[Styles] ' . $e->getMessage()
				));
				return false;
			}
		}

		if ($options['collapse_whitespace']) {
			common\ref\sanitize::whitespace($content);
			if (! $content) {
				$this->debug(new \WP_Error(
					\__('Build Failed', 'well-handled'),
					\__('Collapsing whitespace.', 'well-handled')
				));
				return false;
			}
		}

		// One last check, just in case anything was missed.
		if (! $content) {
			$this->debug(new \WP_Error(
				\__('Build Failed', 'well-handled'),
				\__('No content.', 'well-handled')
			));
			return false;
		}

		// Postprocess filters.
		$content = \apply_filters('wh_postprocess_template', $content, $this->template['slug']);
		common\ref\cast::to_string($content, true);
		if (! $content) {
			$this->debug(new \WP_Error(
				\__('Build Failed', 'well-handled'),
				'wh_postprocess_template ' . \__('filter(s)', 'well-handled')
			));
			return false;
		}

		$debug = $this->debug('', false);
		if ($options['debug']) {
			// @codingStandardsIgnoreStart
			$content .= "\n" .
				"<!-- ============================================\n" .
				"          WELL-HANDLED EMAIL TEMPLATES\n" .
				"-------------------------------------------------\n" .
				esc_html(implode("\n", $debug['log'])) . "\n" .
				"============================================= -->";
			// @codingStandardsIgnoreEnd
		}

		// TODO mail details?
		$out = array(
			'compilation_time'=>$debug['time'],
			'template_slug'=>$this->template['slug'],
			'template_data'=>\is_array($data) ? \json_encode($data) : '',
			'template_options'=>\json_encode($options),
			'content'=>$content,
		);
		$this->build[] = $out;

		return $out;
	}

	/**
	 * Validate HTML
	 *
	 * @param string $content Content.
	 * @param \DOMDocument $dom DOM object.
	 * @param int $dom_html_args DOM arguments.
	 * @return bool True/false.
	 */
	public static function validate_html(&$content, ?\DOMDocument $dom=null, $dom_html_args=0) {
		common\ref\cast::to_string($content, true);
		common\ref\cast::to_int($dom_html_args, true);

		// Initialize.
		if (null === $dom) {
			$dom = static::html_import($content, $dom_html_args);
		}

		try {
			// Does this have a <body>?
			\preg_match('/(<body[^>]*>)(.*?)(<\/body>)/is', $content, $matches);
			$full_content = false;
			if (isset($matches[2])) {
				$full_content = true;
				$body = $matches[2];
			}
			// Otherwise treat it as a ready fragment.
			else {
				$body = $content;
			}

			// Thank goodness there's an alternative to HTMLPurifier!
			$config = static::HTML_VALIDATION;
			$config['base_url'] = \site_url();
			$body = lib\htmlawed::filter($body, $config);

			// Rebuild the content.
			if ($full_content) {
				$content = \preg_replace_callback(
					'/(<body[^>]*>)(.*?)(<\/body>)/is',
					function ($matches) use($body) {
						return $matches[1] . $body . $matches[3];
					},
					$content
				);

				// We don't need anything after the </body> except </html>.
				$content = \preg_replace('/<\/body>.*/is', '</body>', $content);
				if (\preg_match('/<html/i', $content)) {
					$content .= '</html>';
				}
			} else {
				$content = $body;
			}
		} catch (\Throwable $e) {
			$content = '';
			return false;
		}

		if (! $content) {
			return false;
		}

		// Reload the DOM.
		$dom->loadHTML($content, $dom_html_args);

		try {
			// Unfortunately HTMLLawed can only handle the body content.
			$html = $dom->getElementsByTagName('html');
			if ($html->length) {
				static::sanitize_node_attributes($html->item(0));
			}

			$head = $dom->getElementsByTagName('head');
			if ($head->length) {
				static::sanitize_node_attributes($head->item(0));

				// Sanitize <head> children.
				if ($head->item(0)->hasChildNodes()) {
					// Work backwards.
					$length = $head->item(0)->childNodes->length;
					for ($x = $length - 1; $x >= 0; $x--) {
						$child = $head->item(0)->childNodes->item($x);
						// Skip non-tags.
						if (! isset($child->tagName) || ! $child->tagName) {
							continue;
						}

						// Strip any tag that isn't meta, style, or title.
						if (! \preg_match('/^(meta|style|title)$/i', $child->tagName)) {
							$child->parentNode->removeChild($child);
							continue;
						}

						static::sanitize_node_attributes($child);
					}
				}
			}

			$body = $dom->getElementsByTagName('body');
			if ($body->length) {
				static::sanitize_node_attributes($body->item(0));
			}

			$content = static::html_export($dom);
			return true;
		} catch (\Throwable $e) {
			$content = '';
			return false;
		}
	}

	/**
	 * Template Fragment (Shortcode)
	 *
	 * @param array $atts Attributes.
	 * @return string|bool Fragment or false.
	 */
	public static function make_fragment($atts) {
		if (! \is_array($atts) || ! isset($atts['template'])) {
			return false;
		}

		// Commas indicate A/B array.
		common\ref\cast::to_string($atts['template'], true);
		if (false !== \strpos($atts['template'], ',')) {
			$atts['template'] = \explode(',', $atts['template']);
		}

		$fragment = new template($atts['template']);
		if ($fragment->is_template()) {
			return \do_shortcode($fragment->get_raw());
		}

		return false;
	}

	/**
	 * Sanitize Node Attributes
	 *
	 * Remove on* attributes.
	 *
	 * @param \DOMNode $node Node.
	 * @return bool True/false.
	 */
	protected static function sanitize_node_attributes(\DOMNode $node) {
		try {
			if ($node->hasAttributes()) {
				// Run it backwards.
				$length = $node->attributes->length;
				for ($x = $length - 1; $x >= 0; $x--) {
					if (\preg_match('/^on/i', $node->attributes->item($x)->name)) {
						$node->removeAttribute($node->attributes->item($x)->name);
					}
				}
			}
			return true;
		} catch (\Throwable $e) {
			return false;
		}
	}

	/**
	 * Is Site URL?
	 *
	 * @param string $url URL.
	 * @return bool True/false.
	 */
	public static function is_site_url($url) {
		common\ref\sanitize::hostname($url, false);
		$url2 = common\sanitize::hostname(\site_url(), false);

		return $url === $url2;
	}

	/**
	 * DOM from HTML
	 *
	 * @param string $html HTML.
	 * @param int $dom_html_args DOM Arguments.
	 * @return DOMDocument Dom object.
	 */
	protected static function html_import($html, $dom_html_args) {
		common\ref\cast::to_string($html, true);

		// A weird hack to force UTF-8, which for some reason is
		// necessary even though DOMDocument takes an encoding
		// argument as part of its constructor. Haha.
		$html = '<?xml version="1.0" encoding="UTF-8"?>' . $html;

		\libxml_use_internal_errors(true);
		if (\PHP_VERSION_ID < 80000) {
			// phpcs:ignore
			\libxml_disable_entity_loader(true);
		}

		$dom = new \DOMDocument('1.0', 'UTF-8');
		$dom->loadHTML($html, $dom_html_args);

		return $dom;
	}

	/**
	 * HTML from DOM
	 *
	 * @param DOMDocument $dom DOM object.
	 * @return string HTML.
	 */
	protected static function html_export($dom) {
		// Have to export the documentElement or else the XML
		// hack will be exported too.
		$html = $dom->saveHTML($dom->documentElement);

		common\ref\cast::to_string($html, true);

		// If this is all there is, it is wrong.
		$html = \str_replace('<?xml version="1.0" encoding="UTF-8"?>', '', $html);

		return $html;
	}

	// --------------------------------------------------------------------- end processing
}
