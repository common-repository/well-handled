<?php
// @codingStandardsIgnoreFile
/**
 * Well-Handled - Handlebar Helpers
 *
 * @see {https://github.com/mardix/Handlebars}
 *
 * @package Well-Handled
 * @author  Blobfolio, LLC <hello@blobfolio.com>
 */

namespace blobfolio\wp\wh\lib;
use blobfolio\wp\wh\vendor\common;

class handlebars implements \IteratorAggregate {

	// Helper names.
	const HELPERS = array(
		'avg',
		'capitalize',
		'capitalize_words',
		'count',
		'currency',
		'date',
		'default',
		'format_date',
		'ifEqual',
		'ifGreater',
		'ifLesser',
		'inflect',
		'join',
		'lower',
		'max',
		'min',
		'nl2br',
		'now',
		'raw',
		'repeat',
		'reverse',
		'strtolower',
		'strtoupper',
		'sum',
		'truncate',
		'ucfirst',
		'ucwords',
		'upper',
		'with',
		'wp_bloginfo',
		'wp_site_url'
	);

	// Some aliases.
	const ALIASES = array(
		'format_date'=>'date',
		'strtolower'=>'lower',
		'strtoupper'=>'upper',
		'ucfirst'=>'capitalize',
		'ucwords'=>'capitalize_words'
	);

	// Helper=>function() pairs.
	protected $helpers = array();

	/**
	 * Construct
	 *
	 * @return void Nothing.
	 */
	public function __construct() {
		foreach (static::HELPERS as $h) {
			$func = '_' . $h;
			if (array_key_exists($h, static::ALIASES)) {
				$func = '_' . static::ALIASES[$h];
			}
			$this->helpers[$h] = array($this, $func);
		}
	}

	/**
	 * Sanitize HTML
	 *
	 * @param string $str String.
	 * @return string String.
	 */
	public function sanitize($str='') {
		common\ref\cast::to_string($str, true);
		return common\sanitize::html($str);
	}

	/**
	 * Traversable Iterator
	 *
	 * @return \ArrayIterator Iterator.
	 */
	public function getIterator() : \Traversable {
		return new \ArrayIterator($this->helpers);
	}

	/**
	 * Comparison Helper
	 *
	 * This code is shared by ifEqual, ifGreater, and
	 * ifLesser helpers.
	 *
	 * @param \Handlebars\Template $template Template object.
	 * @param bool $condition Condition.
	 * @param \Handlebars\Context $context Context.
	 * @return mixed $buffer Buffer.
	 */
	protected function parse_comparison($template, $condition, $context) {
		if ($condition) {
			$template->setStopToken('else');
			$buffer = $template->render($context);
			$template->setStopToken(false);
		} else {
			$template->setStopToken('else');
			$template->discard();
			$template->setStopToken(false);
			$buffer = $template->render($context);
		}

		return $buffer;
	}

	/**
	 * Array Average
	 *
	 * @param \Handlebars\Template $template Template object.
	 * @param \Handlebars\Context $context Context.
	 * @param array $args Arguments.
	 * @param string $source Source.
	 * @return mixed $buffer Buffer.
	 */
	public function _avg($template, $context, $args, $source) {
		$data = $template->parseArguments($args);
		if (empty($data)) {
			return '';
		}

		$arr = (array) $context->get($data[0]);
		return empty($arr) ? 0 : array_sum($arr) / count($arr);
	}

	/**
	 * Sentence Case
	 *
	 * @param \Handlebars\Template $template Template object.
	 * @param \Handlebars\Context $context Context.
	 * @param array $args Arguments.
	 * @param string $source Source.
	 * @return mixed $buffer Buffer.
	 */
	public function _capitalize($template, $context, $args, $source) {
		$data = $template->parseArguments($args);
		if (empty($data)) {
			return '';
		}
		return static::sanitize(common\mb::ucfirst($context->get($data[0])));
	}

	/**
	 * Title Case
	 *
	 * @param \Handlebars\Template $template Template object.
	 * @param \Handlebars\Context $context Context.
	 * @param array $args Arguments.
	 * @param string $source Source.
	 * @return mixed $buffer Buffer.
	 */
	public function _capitalize_words($template, $context, $args, $source) {
		$data = $template->parseArguments($args);
		if (empty($data)) {
			return '';
		}
		return static::sanitize(common\mb::ucwords($context->get($data[0])));
	}

	/**
	 * Array Count
	 *
	 * @param \Handlebars\Template $template Template object.
	 * @param \Handlebars\Context $context Context.
	 * @param array $args Arguments.
	 * @param string $source Source.
	 * @return mixed $buffer Buffer.
	 */
	public function _count($template, $context, $args, $source) {
		$data = $template->parseArguments($args);
		$out = common\cast::to_array($context->get($data[0]));
		return count($out);
	}

	/**
	 * US Currency
	 *
	 * @param \Handlebars\Template $template Template object.
	 * @param \Handlebars\Context $context Context.
	 * @param array $args Arguments.
	 * @param string $source Source.
	 * @return mixed $buffer Buffer.
	 */
	public function _currency($template, $context, $args, $source) {
		$data = $template->parseArguments($args);
		if (empty($data)) {
			return '';
		}

		return common\format::money($context->get($data[0]));
	}

	/**
	 * Date
	 *
	 * @param \Handlebars\Template $template Template object.
	 * @param \Handlebars\Context $context Context.
	 * @param array $args Arguments.
	 * @param string $source Source.
	 * @return mixed $buffer Buffer.
	 */
	public function _date($template, $context, $args, $source) {
		$data = $template->parseArguments($args);
		if (count($data) < 2) {
			return '';
		}
		$date = common\sanitize::datetime($context->get($data[0]));
		return '0000-00-00 00:00:00' !== $date ? static::sanitize(date($context->get($data[1]), strtotime($date))) : '';
	}

	/**
	 * Fallback Content
	 *
	 * @param \Handlebars\Template $template Template object.
	 * @param \Handlebars\Context $context Context.
	 * @param array $args Arguments.
	 * @param string $source Source.
	 * @return mixed $buffer Buffer.
	 */
	public function _default($template, $context, $args, $source) {
		$data = $template->parseArguments($args);
		if (count($data) < 2) {
			return '';
		}

		return $context->get($data[0]) ? static::sanitize($context->get($data[0])) : static::sanitize($data[1]);
	}

	/**
	 * If Equal
	 *
	 * @param \Handlebars\Template $template Template object.
	 * @param \Handlebars\Context $context Context.
	 * @param array $args Arguments.
	 * @param string $source Source.
	 * @return mixed $buffer Buffer.
	 */
	public function _ifEqual($template, $context, $args, $source) {
		$data = $template->parseArguments($args);
		if (count($data) < 2) {
			return '';
		}

		// @codingStandardsIgnoreStart
		return $this->parse_comparison($template, ($context->get($data[0]) == $context->get($data[1])), $context);
		// @codingStandardsIgnoreEnd
	}

	/**
	 * If Greater
	 *
	 * @param \Handlebars\Template $template Template object.
	 * @param \Handlebars\Context $context Context.
	 * @param array $args Arguments.
	 * @param string $source Source.
	 * @return mixed $buffer Buffer.
	 */
	public function _ifGreater($template, $context, $args, $source) {
		$data = $template->parseArguments($args);
		if (count($data) < 2) {
			return '';
		}

		return $this->parse_comparison($template, ($context->get($data[0]) > $context->get($data[1])), $context);
	}

	/**
	 * If Lesser
	 *
	 * @param \Handlebars\Template $template Template object.
	 * @param \Handlebars\Context $context Context.
	 * @param array $args Arguments.
	 * @param string $source Source.
	 * @return mixed $buffer Buffer.
	 */
	public function _ifLesser($template, $context, $args, $source) {
		$data = $template->parseArguments($args);
		if (count($data) < 2) {
			return '';
		}

		return $this->parse_comparison($template, ($context->get($data[0]) < $context->get($data[1])), $context);
	}

	/**
	 * Inflection (Singular/Plural)
	 *
	 * @param \Handlebars\Template $template Template object.
	 * @param \Handlebars\Context $context Context.
	 * @param array $args Arguments.
	 * @param string $source Source.
	 * @return mixed $buffer Buffer.
	 */
	public function _inflect($template, $context, $args, $source) {
		$data = $template->parseArguments($args);
		if (count($data) < 3) {
			return '';
		}

		return static::sanitize(common\format::inflect($context->get($data[0]), $context->get($data[1]), $context->get($data[2])));
	}

	/**
	 * Join Array Elements
	 *
	 * @param \Handlebars\Template $template Template object.
	 * @param \Handlebars\Context $context Context.
	 * @param array $args Arguments.
	 * @param string $source Source.
	 * @return mixed $buffer Buffer.
	 */
	public function _join($template, $context, $args, $source) {
		$data = $template->parseArguments($args);
		if (empty($data)) {
			return '';
		}

		$data[0] = common\cast::to_array($context->get($data[0]));
		$data[1] = isset($data[1]) ? $context->get($data[1]) : '';

		return static::sanitize(implode($data[1], $data[0]));
	}

	/**
	 * Lowercase
	 *
	 * @param \Handlebars\Template $template Template object.
	 * @param \Handlebars\Context $context Context.
	 * @param array $args Arguments.
	 * @param string $source Source.
	 * @return mixed $buffer Buffer.
	 */
	public function _lower($template, $context, $args, $source) {
		$data = $template->parseArguments($args);
		return empty($data) ? '' : static::sanitize(common\mb::strtolower($context->get($data[0])));
	}

	/**
	 * Largest Array Value
	 *
	 * @param \Handlebars\Template $template Template object.
	 * @param \Handlebars\Context $context Context.
	 * @param array $args Arguments.
	 * @param string $source Source.
	 * @return mixed $buffer Buffer.
	 */
	public function _max($template, $context, $args, $source) {
		$data = $template->parseArguments($args);
		if (empty($data)) {
			return '';
		}

		$arr = common\cast::to_array($context->get($data[0]));
		return max($arr);
	}

	/**
	 * Smallest Array Value
	 *
	 * @param \Handlebars\Template $template Template object.
	 * @param \Handlebars\Context $context Context.
	 * @param array $args Arguments.
	 * @param string $source Source.
	 * @return mixed $buffer Buffer.
	 */
	public function _min($template, $context, $args, $source) {
		$data = $template->parseArguments($args);
		if (empty($data)) {
			return '';
		}

		$arr = common\cast::to_array($context->get($data[0]));
		return min($arr);
	}

	/**
	 * New Lines to <br>
	 *
	 * @param \Handlebars\Template $template Template object.
	 * @param \Handlebars\Context $context Context.
	 * @param array $args Arguments.
	 * @param string $source Source.
	 * @return mixed $buffer Buffer.
	 */
	public function _nl2br($template, $context, $args, $source) {
		$data = $template->parseArguments($args);
		return empty($data) ? '' : nl2br(static::sanitize($context->get($data[0])));
	}

	/**
	 * Current Time
	 *
	 * @param \Handlebars\Template $template Template object.
	 * @param \Handlebars\Context $context Context.
	 * @param array $args Arguments.
	 * @param string $source Source.
	 * @return mixed $buffer Buffer.
	 */
	public function _now($template, $context, $args, $source) {
		$date = current_time('mysql');

		$data = $template->parseArguments($args);
		return empty($data) ? $date : static::sanitize(date($context->get($data[0]), strtotime($date)));
	}

	/**
	 * Raw Passthrough
	 *
	 * @param \Handlebars\Template $template Template object.
	 * @param \Handlebars\Context $context Context.
	 * @param array $args Arguments.
	 * @param string $source Source.
	 * @return mixed $buffer Buffer.
	 */
	public function _raw($template, $context, $args, $source) {
		$data = $template->parseArguments($args);
		return $context->get($data[0]);
	}

	/**
	 * Repeat String
	 *
	 * @param \Handlebars\Template $template Template object.
	 * @param \Handlebars\Context $context Context.
	 * @param array $args Arguments.
	 * @param string $source Source.
	 * @return mixed $buffer Buffer.
	 */
	public function _repeat($template, $context, $args, $source) {
		$data = $template->parseArguments($args);
		$repeat = empty($data) ? 1 : intval($context->get($data[0]));
		common\ref\sanitize::to_range($repeat, 1);

		$buffer = $template->render($context);
		return str_repeat($buffer, $repeat);
	}

	/**
	 * Reverse String
	 *
	 * @param \Handlebars\Template $template Template object.
	 * @param \Handlebars\Context $context Context.
	 * @param array $args Arguments.
	 * @param string $source Source.
	 * @return mixed $buffer Buffer.
	 */
	public function _reverse($template, $context, $args, $source) {
		$data = $template->parseArguments($args);
		if(empty($data)){
			return '';
		}

		$str = $context->get($data[0]);
		return static::sanitize(common\mb::strrev($str));
	}

	/**
	 * Array Sum
	 *
	 * @param \Handlebars\Template $template Template object.
	 * @param \Handlebars\Context $context Context.
	 * @param array $args Arguments.
	 * @param string $source Source.
	 * @return mixed $buffer Buffer.
	 */
	public function _sum($template, $context, $args, $source) {
		$data = $template->parseArguments($args);
		if (empty($data)) {
			return '';
		}

		$arr = common\cast::to_array($context->get($data[0]));
		return array_sum($arr);
	}

	/**
	 * Truncate Text
	 *
	 * @param \Handlebars\Template $template Template object.
	 * @param \Handlebars\Context $context Context.
	 * @param array $args Arguments.
	 * @param string $source Source.
	 * @return mixed $buffer Buffer.
	 */
	public function _truncate($template, $context, $args, $source) {
		$data = $template->parseArguments($args);
		if (empty($data)) {
			return '';
		}
		elseif (count($data) === 1) {
			return static::sanitize($context->get($data[0]));
		}

		$value = $context->get($data[0]);
		$limit = (int) $context->get($data[1]);
		$ellipsis = isset($data[2]) ? $context->get($data[2]) : '';

		if (common\mb::strlen($value) > $limit) {
			$value = trim(common\mb::substr($value, 0, $limit)) . ($ellipsis ? $ellipsis : '');
		}

		return static::sanitize($value);
	}

	/**
	 * Uppercase
	 *
	 * @param \Handlebars\Template $template Template object.
	 * @param \Handlebars\Context $context Context.
	 * @param array $args Arguments.
	 * @param string $source Source.
	 * @return mixed $buffer Buffer.
	 */
	public function _upper($template, $context, $args, $source) {
		$data = $template->parseArguments($args);
		return empty($data) ? '' : static::sanitize(common\mb::strtoupper($context->get($data[0])));
	}

	/**
	 * Change Contexts From Inside Loop
	 *
	 * @param \Handlebars\Template $template Template object.
	 * @param \Handlebars\Context $context Context.
	 * @param array $args Arguments.
	 * @param string $source Source.
	 * @return mixed $buffer Buffer.
	 */
	public function _with($template, $context, $args, $source) {
		$data = $template->parseArguments($args);
		if (empty($data)) {
			return '';
		}

		$tmp = $context->get($data[0]);
		$context->push($tmp);
		$buffer = $template->render($context);
		$context->pop();

		return $buffer;
	}

	/**
	 * WP Bloginfo
	 *
	 * Pluck a key from get_bloginfo().
	 *
	 * @param \Handlebars\Template $template Template object.
	 * @param \Handlebars\Context $context Context.
	 * @param array $args Arguments.
	 * @param string $source Source.
	 * @return mixed $buffer Buffer.
	 */
	public function _wp_bloginfo($template, $context, $args, $source) {
		$data = $template->parseArguments($args);
		$out = empty($data) ? '' : get_bloginfo($context->get($data[0]));
		common\ref\format::decode_entities($out);
		return static::sanitize($out);
	}

	/**
	 * WP Site URL
	 *
	 * Generate a site URL.
	 *
	 * @param \Handlebars\Template $template Template object.
	 * @param \Handlebars\Context $context Context.
	 * @param array $args Arguments.
	 * @param string $source Source.
	 * @return mixed $buffer Buffer.
	 */
	public function _wp_site_url($template, $context, $args, $source) {
		$data = $template->parseArguments($args);
		if (empty($data)) {
			return site_url();
		}

		$data[0] = $data[0] ? $context->get($data[0]) : null;
		$data[1] = isset($data[1]) ? $context->get($data[1]) : null;

		return site_url($data[0], $data[1]);
	}

}


