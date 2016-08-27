<?php

require_once('Debugger.php');

use RWD\WP_Debugger;

// Log

function dlog ($var, $title = NULL)
{
	WP_Debugger::log($var, $title, 'debug');
}

function ddebug ($var, $title = NULL)
{
	WP_Debugger::log($var, $title, 'debug');
}

function dtable ($var, $title = NULL)
{
	WP_Debugger::log($var, $title, 'table');
}

function dgroup ($title = NULL)
{
	WP_Debugger::log(NULL, $title, 'group');
}

function dgroupCollapsed ($title = NULL)
{
	WP_Debugger::log(NULL, $title, 'groupCollapsed');
}

function dgroupEnd ()
{
	WP_Debugger::log(NULL, NULL, 'groupEnd');
}

// Info

function dinfo ($var, $title = NULL)
{
	WP_Debugger::log($var, $title, 'info');
}

function dnotice ($var, $title = NULL)
{
	WP_Debugger::log($var, $title, 'notice');
}

// Warn

function dwarn ($var, $title = NULL)
{
	WP_Debugger::log($var, $title, 'warning');
}

// Error

function derror ($var, $title = NULL)
{
	WP_Debugger::log($var, $title, 'error');
}

function dcritical ($var, $title = NULL)
{
	WP_Debugger::log($var, $title, 'critical');
}

function dalert ($var, $title = NULL)
{
	WP_Debugger::log($var, $title, 'alert');
}

function demergency ($var, $title = NULL)
{
	WP_Debugger::log($var, $title, 'emergency');
}


function dexception ($var, $title = NULL)
{
	WP_Debugger::log($var, $title, 'exception');
}

function dtimer ($id = 'Default Timer', $params = NULL)
{
	WP_Debugger::timer($id, $params);
}

function dtimerStop ($id = 'Default Timer', $params = NULL)
{
	dtimer($id, $params);
}

function dtimerEnd ($id = 'Default Timer', $params = NULL)
{
	dtimer($id, $params);
}

function dtimers ()
{
	global $debugbar;

	if (empty($debugbar)) {
		return;
	}

	$timers = $debugbar['time']->getMeasures();

	if (empty($timers)) {
		return;
	}

	if (php_sapi_name() != 'cli') {
		$timer_list   = array();
		$timer_params = array();

		foreach ($timers as $timer) {
			$timer_list[] = array(
				'label'  => $timer['label'],
				'length' => $timer['duration_str']
			);
			if (!empty($timer['params'])) {
				$timer_params[] = array(
					$timer['label'] => $timer['params']
				);
			}
		}

		WP_Debugger::log($timer_list, 'Timer_List', 'table');

		if (!empty($timer_params)) {
			WP_Debugger::log($timer_params, 'Timer_Params', 'table');
		}
	}
	else {
		echo PHP_EOL, 'Timers:', PHP_EOL, '-------', PHP_EOL;
		foreach ($timers as $timer) {
			echo $timer['label'], ': ', $timer['duration_str'], PHP_EOL;
		}
		echo '-------', PHP_EOL, PHP_EOL;
	}
}

function dtrace ()
{
	if (!class_exists('Kint')) {
		var_dump(debug_backtrace());

		return;
	}

	WP_Debugger::trace();
}

function dextra ($set = TRUE)
{
	if (!empty($set)) {
		WP_Debugger::enableExtraInfo();
	}
	else {
		WP_Debugger::disableExtraInfo();
	}
}