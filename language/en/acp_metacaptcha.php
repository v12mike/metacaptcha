<?php
/**
*
* metacaptcha [English]
*
* @copyright (c) v12mike <http://www....>
* @license GNU General Public License, version 2 (GPL-2.0)
*
*/

/**
* DO NOT CHANGE
*/
if (!defined('IN_PHPBB'))
{
	exit;
}

if (empty($lang) || !is_array($lang))
{
	$lang = array();
}

// DEVELOPERS PLEASE NOTE
//
// All language files should use UTF-8 as their encoding and the files must not contain a BOM.
//
// Placeholders can now contain order information, e.g. instead of
// 'Page %s of %s' you can (and should) write 'Page %1$s of %2$s', this allows
// translators to re-order the output of data while ensuring it remains correct
//
// You do not need this where single placeholders are used, e.g. 'Message %d' is fine
// equally where a string contains only two placeholders which are used to wrap text
// in a url you again do not need to specify an order e.g., 'Click %sHERE%s' is fine

$lang = array_merge($lang, array(
	'AVAILABLE_PLUGINS' 			=> 'Available Plugins',
	'AVAILABLE_PLUGINS_EXPLAIN' 	=> 'These plugins are not active',
	'CONFIGURED_PLUGINS'			=> 'Configured Plugins',
	'CONFIGURED_PLUGINS_EXPLAIN'	=> 'These plugins are active (if available) and are presented to the user in order of the configured priorities',
	'PLUGINS'   					=> 'Plugins',
	'PLUGIN_ACTIVE' 				=> 'Active',
	'PLUGIN_DELETED'				=> 'Plugin Deleted',
	'PLUGIN_NAME'   				=> 'Name',
	'PLUGIN_PRIORITY'   			=> 'Priority',
	'PLUGIN_STATUS' 				=> 'Status',
	'PLUGIN_UNCONFIGURED'			=> 'Unconfigured',
	'UPDATE_PRIORITY'				=> 'Update',
));
