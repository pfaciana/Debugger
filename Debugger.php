<?php

namespace RWD;

use DebugBar;
use Tracy;
use Kint;
use ChromePhp;

class Debugger
{
	static protected $_init = FALSE;
	static protected $_extraInfo = TRUE;
	static protected $_varName = NULL;
	static protected $_varTitle = NULL;
	static protected $_activeTimers = array();
	static public $kint_colors = array(
		'main-background'      => '#f7f7f7',
		'secondary-background' => '#f1f1f1',
		'text-name'            => '',
		'variable-type'        => '#0073aa',
		'variable-type-hover'  => '',
		'border'               => '',
		'td-top'               => '#f7fcfe',
		'td-bottom'            => '#f7fcfe',
		'td-empty'             => '',
		'tab-top'              => '#ccc',
		'tab-bottom'           => '#ccc',
		'trace-highlight'      => 'none; border: solid 2px #f00',
		'footer-text'          => '#111',
		'active-tab'           => '3px; font-weight: bold; text-transform: uppercase',
		'var-name'             => 'dfn{font-style:normal;font-family:monospace;color:#880088}',
	);

	static public function init ($vendorUrl = NULL, $strictMode = TRUE)
	{
		if (self::$_init) {
			return;
		}

		global $debugbar, $debugbarRenderer;

		if (class_exists('DebugBar\\StandardDebugBar') && !empty($vendorUrl)) {
			$debugbar         = new DebugBar\StandardDebugBar(); // Returns as an implementation of ArrayAccess
			$debugbarRenderer = $debugbar->getJavascriptRenderer($vendorUrl . '/vendor/maximebf/debugbar/src/DebugBar/Resources/');
		}

		if (class_exists('Tracy\\Debugger')) {
			Tracy\Debugger::enable();
			Tracy\Debugger::$strictMode = $strictMode;
		}

		if (class_exists('Kint') && php_sapi_name() == 'cli') {
			Kint::enabled(Kint::MODE_WHITESPACE);
		}

		self::showAllErrors();

		self::$_init = TRUE;
	}

	static public function showAllErrors ()
	{
		@error_reporting(E_ALL);
		@ini_set('display_errors', TRUE);
		@ini_set('display_startup_errors', TRUE);

		if (class_exists('Tracy')) {
			register_shutdown_function(array('Tracy\\Debugger', 'shutdownHandler'));
			set_exception_handler(array('Tracy\\Debugger', 'exceptionHandler'));
			set_error_handler(array('Tracy\\Debugger', 'errorHandler'));
		}
	}

	static protected function _setStack ($var)
	{
		self::$_varName  = NULL;
		self::$_varTitle = NULL;

		$call = NULL;

		foreach (debug_backtrace() as $trace) {
			if (!empty($trace['class']) && $trace['class'] == __CLASS__) {
				continue;
			}

			$call = $trace;
			break;
		}

		if (empty($call) || empty($call['file']) || !isset($call['line'])) {
			return;
		}

		$varType = (gettype($var) != 'object') ? gettype($var) : get_class($var);

		$file = str_replace(array('\\', str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'])), array('/', ''), $call['file']);

		self::$_varTitle = $varType . ' (' . $file . ':' . $call['line'] . ')';

		$lines = file($call['file']);

		if (empty($lines)) {
			return;
		}

		self::$_varName = (preg_match('/[^(]*\({1}([^,)]*)/', $lines[$call['line'] - 1], $matches) && !empty($matches[1])) ? $matches[1] : NULL;

		if (!empty(self::$_varName)) {
			self::$_varTitle = self::$_varName . ' | ' . self::$_varTitle;
		}
	}

	static public function timer ($id = 'Default Timer', $params = NULL)
	{
		global $debugbar;

		if (empty($debugbar)) {
			return;
		}

		static::init();

		self::_setStack($id);

		$index = array_search($id, self::$_activeTimers);

		if ($index === FALSE) {
			self::$_activeTimers[] = $id;
			$debugbar['time']->startMeasure($id, $id);
		}
		else {
			unset(self::$_activeTimers[$index]);
			$debugbar['time']->stopMeasure($id, $params);
		}
	}

	static public function log ($var, $title = NULL, $level = 'debug')
	{
		static::init();

		self::_setStack($var);

		self::logChrome($var, $title, $level);

		self::logKint($var, $title);

		self::logDebugBar($var, $title, $level);

		self::logFallback($var, $title);
	}

	static public function logFallback ($var, $title = NULL)
	{
		if (class_exists('Kint')) {
			return;
		}

		if (class_exists('Tracy\\Debugger')) {
			if (!empty($title)) {
				Tracy\Debugger::dump($title);
			}

			Tracy\Debugger::dump($var);
		}
		else {
			if (!empty($title)) {
				var_dump('Title: ' . $title);
			}

			var_dump($var);
		}
	}

	static public function logDebugBar ($var, $title = NULL, $level = 'debug')
	{
		global $debugbar;

		if (empty($debugbar)) {
			return;
		}

		if (!empty($title)) {
			$debugbar['messages']->addMessage($title, 'info');
		}

		if (!empty(self::$_extraInfo)) {
			$debugbar['messages']->addMessage(self::$_varTitle, 'info');
		}

		$debugbar['messages']->addMessage($var, $level);

		$title = !empty($title) ? $title : @explode(' ', self::$_varTitle, 2)[0];

		if (is_array($var)) {
			list($usec, $sec) = explode(' ', microtime());
			$debugbar->addCollector(new DebugBar\DataCollector\ConfigCollector($var, $title . ' ' . round($usec * 100000)));
		}

	}

	static public function logChrome ($var, $title = NULL, $level = 'log')
	{
		if (headers_sent()) {
			return;
		}

		if (!class_exists('ChromePhp')) {
			return;
		}

		if (in_array($level, array('debug'))) {
			$level = 'log';
		}

		if (in_array($level, array('notice'))) {
			$level = 'info';
		}

		if (in_array($level, array('warning'))) {
			$level = 'warn';
		}

		if (in_array($level, array('critical', 'alert', 'emergency', 'exception'))) {
			$level = 'error';
		}


		if (!empty($title)) {
			ChromePhp::info($title);
		}

		if (!empty(self::$_extraInfo)) {
			ChromePhp::info(self::$_varTitle);
		}

		ChromePhp::$level($var);
	}

	static public function logKint ($var, $title = NULL)
	{
		if (!class_exists('Kint')) {
			return;
		}

		ob_start('static::_cleanUpOutput');

		if (!empty($title)) {
			Kint::dump($title);
		}
		elseif (!empty(self::$_extraInfo)) {
			Kint::dump(self::$_varTitle);
		}

		Kint::dump($var);

		ob_end_flush();
	}

	static public function trace ()
	{
		if (!class_exists('Kint')) {
			return;
		}

		ob_start('static::_cleanUpOutput');

		Kint::trace();

		ob_end_flush();
	}

	static protected function _cleanUpOutput ($output)
	{
		$defaults = array(
			'main-background'      => '#e0eaef',
			'secondary-background' => '#c1d4df',
			'var-name'             => 'dfn{font-style:normal;font-family:monospace;color:#1d1e1e}',
			'text-name'            => '#1d1e1e',
			'variable-type'        => '#0092db', // border-hover
			'variable-type-hover'  => '#5cb730',
			'border'               => '#b6cedb',
			'td-top'               => '#e3ecf0', // dt top background
			'td-bottom'            => '#c0d4df', // dt bottom background
			'td-empty'             => '#d33682',
			'tab-top'              => '#9dbed0',
			'tab-bottom'           => '#b2ccda',
			'trace-highlight'      => '#f0eb96',
			'footer-text'          => '#ddd',
			'active-tab'           => '-1px'
		);

		if (preg_match('%((?:<div class="kint">){1}.*?(?:\) \"){1}(.*?)(?:[ \"]).*?(?:</div>){1}){1}.*?(?:<dfn>)(.*?)(?:</dfn>)%m', $output, $matches)) {
			$output = str_replace(array($matches[1], '<dfn>' . $matches[3] . '</dfn>'), array('', $matches[2]), $output);
		}

		foreach ($defaults as $key => $value) {
			if (!empty(self::$kint_colors[$key])) {
				$output = str_replace($defaults[$key], self::$kint_colors[$key], $output);
			}
		}

		return $output;
	}

	static public function enableExtraInfo ()
	{
		self::$_extraInfo = TRUE;
	}

	static public function disableExtraInfo ()
	{
		self::$_extraInfo = FALSE;
	}
}

class WP_Debugger extends Debugger
{
	static public $enableGuestMode = FALSE;

	static public function init ($vendorUrl = NULL, $strictMode = TRUE)
	{
		if (self::$_init) {
			return;
		}

		if (function_exists('add_action')) {
			add_action('wp_print_scripts', array(__CLASS__, 'debugbar_header_func'));
			add_action('wp_print_footer_scripts', array(__CLASS__, 'debugbar_footer_func'));
			add_action('wp_print_footer_scripts', 'dtimers');
			add_action('plugins_loaded', array(__CLASS__, 'showAllErrors'));
			add_action('admin_bar_init', array(__CLASS__, 'showAllErrors'), 999);
		}

		if (function_exists('add_filter')) {
			add_filter('debug_bar_panels', array(__CLASS__, 'add_kint_debug_bar_panel'), 999);
		}

		parent::init($vendorUrl, $strictMode);
	}

	static public function _cleanUpOutput ($buffer)
	{
		global $kint_debug;

		$buffer = parent::_cleanUpOutput($buffer);

		$kint_debug[] = $buffer;

		if (class_exists('Debug_Bar') && !self::$enableGuestMode) {
			return '';
		}

		return $buffer;
	}

	static public function add_kint_debug_bar_panel ($panels)
	{
		array_unshift($panels, new Kint_Debug_Bar_Panel);

		return $panels;
	}

	static public function debugbar_header_func ()
	{
		global $debugbarRenderer;

		if (empty($debugbarRenderer)) {
			return;
		}

		echo $debugbarRenderer->renderHead();
	}

	static public function debugbar_footer_func ()
	{
		global $debugbarRenderer;

		if (empty($debugbarRenderer)) {
			return;
		}

		echo $debugbarRenderer->render();
		echo '<style> a.phpdebugbar-tab i { display:inline-block; padding-right: 4px; } </style>';
	}
}

class Kint_Debug_Bar_Panel
{
	public function title ()
	{
		return 'Debugger';
	}

	public function prerender ()
	{

	}

	public function is_visible ()
	{
		return TRUE;
	}

	public function render ()
	{
		global $kint_debug;

		if (is_array($kint_debug)) {
			foreach ($kint_debug as $output) {
				echo $output;
			}
		}
	}
}