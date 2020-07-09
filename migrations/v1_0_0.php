<?php
/**
*
* @copyright (c) v12mike <http://www...>
* @license GNU General Public License, version 2 (GPL-2.0)
*
*/

namespace v12mike\metacaptcha\migrations;

class v1_0_0 extends \phpbb\db\migration\migration
{
	public function effectively_installed()
	{
		return $this->db_tools->sql_table_exists($this->table_prefix . 'metacaptcha_plugins');
	}

	public function update_schema()
	{
		return array(
			'add_tables'		=> array(
				$this->table_prefix . 'metacaptcha_plugins'	=> array(
					'COLUMNS' => array(
						'plugin_id'		=> array('UINT', null, 'auto_increment'),
						'plugin_service_name'   => array('VCHAR:50', ''),
						'plugin_name'	=> array('CHAR:20', ''),
						'plugin_priority'		=> array('UINT', 0),
					),
					'PRIMARY_KEY'		=> 'plugin_id',
					'KEYS'				=> array(
						'iso'			=> array('INDEX', 'plugin_service_name'),
					),
				),
				$this->table_prefix . 'metacaptcha_sessions' => array(
					'COLUMNS' => array(
						'captcha_session_id'	=> array('CHAR:32', ''),
						'plugin_id' 			=> array('UINT', 0),
						'user_session_id'		=> array('CHAR:32', ''),
						'type'  				=> array('UINT', 0),
						'random'				=> array('CHAR:32', ''),
						'solved'				=> array('BOOL', 0),
					),
					'PRIMARY_KEY'		=> 'captcha_session_id, plugin_id',
					'KEYS'				=> array(
						'usid'			=> array('INDEX', 'user_session_id'),
					),
				),
			),
		);
	}

	public function revert_schema()
	{
		return array(
			'drop_tables'		=> array(
				$this->table_prefix . 'metacaptcha_sessions',
				$this->table_prefix . 'metacaptcha_plugins',
			),
		);
	}

	public function update_data()
	{
		return [
			['custom', [[$this, 'load_default_plugin']]],
		];
	}

	public function load_default_plugin()
	{
		$sql = 'INSERT INTO ' . $this->table_prefix . 'metacaptcha_plugins' . " (plugin_service_name, plugin_name, plugin_priority)
					VALUES ('core.captcha.plugins.gd', 'CAPTCHA_GD', 1)";
		$this->db->sql_query($sql);
	}
}
