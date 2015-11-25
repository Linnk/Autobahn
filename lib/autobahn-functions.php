<?php

if (!function_exists('getMicrotime'))
{
	function getMicrotime()
	{
		list($usec, $sec) = explode(' ', microtime());
		return ((float) $usec + (float) $sec);
	}
}

if (!function_exists('pr'))
{
	function pr($mixed)
	{
		if (PHP_SAPI != 'cli')
		{
			echo '<pre>';
			print_r($mixed);
			echo '</pre>';
		}
		else
			print_r($mixed);
	}
}

if (!function_exists('vd'))
{
	function vd($mixed)
	{
		if (PHP_SAPI != 'cli')
		{
			echo '<pre>';
			var_dump($mixed);
			echo '</pre>';
		}
		else
			var_dump($mixed);
	}
}

if (!function_exists('camelize'))
{
	function camelize($lowerCaseAndUnderscoredWord)
	{
		return str_replace(' ', '', ucwords(str_replace('_', ' ', $lowerCaseAndUnderscoredWord)));
	}
}

if (!function_exists('underscore'))
{
	function underscore($camelCasedWord)
	{
		return strtolower(preg_replace('/(?<=\\w)([A-Z])/', '_\\1', $camelCasedWord));
	}
}

if (!function_exists('humanize'))
{
	function humanize($lowerCaseAndUnderscoredWord)
	{
		return ucwords(str_replace('_', ' ', $lowerCaseAndUnderscoredWord));
	}
}
