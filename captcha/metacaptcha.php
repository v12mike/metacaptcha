<?php
/**
*
* @copyright (c) v12mike <http://www....>
* @license GNU General Public License, version 2 (GPL-2.0)
*
*/

namespace v12mike\metacaptcha\captcha;

/**
* META captcha with extending of the QA captcha class.
*/
class metacaptcha extends \phpbb\captcha\plugins\qa
{
	public $type;
	public $solved = 0;
	protected $captcha_session_id;
	protected $acp_form_key = 'acp_metacaptcha';

	// Constants for $this->solved status
	private const NOT_SOLVED = 0;
	private const SOLVED = 1;

	protected $phpbb_container;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var \phpbb\cache\driver\driver_interface */
	protected $cache;

	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\log\log_interface */
//	protected $log;

	/** @var \phpbb\request\request_interface */
	protected $request;

	/** @var \phpbb\template\template */
	protected $template;

	/** @var \phpbb\user */
	protected $user;

	protected $language;

	/** @var \phpbb\captcha\factory */
	protected $captcha_factory;

	protected $plugin_id;

	/** @var string */
	protected $plugin_name;
	protected $plugin_service_name;

    protected $table_metacaptcha_sessions;
    protected $table_metacaptcha_plugins;

	/**
	 *
	 * @param \phpbb\db\driver\driver_interface		$db
	 * @param \phpbb\cache\driver\driver_interface	$cache
	 * @param \phpbb\config\config					$config
	 * @param \phpbb\log\log_interface				$log
	 * @param \phpbb\request\request_interface		$request
	 * @param \phpbb\template\template				$template
	 * @param \phpbb\user							$user
	 * @param \phpbb\language\language				$language,
	 * @param \phpbb\captcha\factory				$captcha_factory, 
	 * @param string								$table_metacaptcha_plugins 
	 * @param string								$table_metacaptcha_sessions
	 */
	public function __construct(\phpbb\db\driver\driver_interface $db,
								\phpbb\cache\driver\driver_interface $cache,
								\phpbb\config\config $config,
								\phpbb\log\log_interface $log,
								\phpbb\request\request_interface $request,
								\phpbb\template\template $template,
								\phpbb\user $user,
								\phpbb\language\language $language,
								\phpbb\captcha\factory $captcha_factory,
								$table_metacaptcha_plugins,
								$table_metacaptcha_sessions)
	{
		$this->db = $db;
		$this->cache = $cache;
		$this->config = $config;
	//	$this->log = $log;
		$this->request = $request;
		$this->template = $template;
		$this->user = $user;
		$this->language = $language;
		$this->captcha_factory = $captcha_factory;
		$this->table_metacaptcha_plugins = $table_metacaptcha_plugins;
		$this->table_metacaptcha_sessions = $table_metacaptcha_sessions;
	}

	/**
	* @param int $type  as per the CAPTCHA API docs, the type
	*/
	public function init($type)
	{
		$this->type = (int) $type;
		// initially assume that all plugins have been solved, this will be updated in load_plugin()
		$this->solved = $this::SOLVED;

		// read any session_id from input
		$this->captcha_session_id = $this->request->variable('metacaptcha_session_id', '');
		if (!$this->captcha_session_id)
		{
			// find any existing captcha session for this user session or create a new session
			$this->load_captcha_session_id();
		}
		$this->load_plugin();
	}

	/**
	 * Initialise the session table for this session.
	 * This entails finding all the configured plugins
	 * and creating a session item for each one
	 */
	protected function init_session_table()
	{
		$sql = 'SELECT plugin_id, plugin_service_name
				FROM ' . $this->table_metacaptcha_plugins	;
		$result = $this->db->sql_query($sql, 3600);

		while ($row = $this->db->sql_fetchrow($result))
		{
			// do not create session for unavailable plugins
		/*	$plugin = $this->captcha_factory->get_instance($row['plugin_service_name']);
			$plugin->init($this->type);
			if ($plugin->is_available()) */ //this code is probably too expensive to run here
            {
                $sql = 'INSERT INTO ' . $this->table_metacaptcha_sessions . ' ' . $this->db->sql_build_array('INSERT', array(
                    'captcha_session_id'	=> $this->db->sql_escape($this->captcha_session_id),
                    'user_session_id'   	=> $this->db->sql_escape($this->user->session_id),
                    'type'  				=> (int) $this->type,
                    'plugin_id' 			=> (int) $row['plugin_id'],
                    'random'				=> md5(unique_id($user->ip)),
                    'solved'				=> 0,
                ));
                $this->db->sql_query($sql);
            }
		}
		$this->db->sql_freeresult($result);
	}

	/**
	 * Load the plugin data for the current session.
	 * The highest priority unsolved plugin is loaded
	 */
	protected function load_plugin()
	{
		$sql = 'SELECT p.plugin_id, plugin_service_name, solved
				FROM ' . $this->table_metacaptcha_sessions . ' s
				LEFT JOIN ' . $this->table_metacaptcha_plugins . ' p
					ON s.plugin_id = p.plugin_id
				WHERE s.captcha_session_id = "' . $this->db->sql_escape($this->captcha_session_id) . '"
				ORDER BY s.solved ASC, p.plugin_priority ASC, s.random ASC';
		$result = $this->db->sql_query_limit($sql, 1);
		if($row = $this->db->sql_fetchrow($result))
		{
			// we have a session and this is the captcha to run
			$this->plugin_id = $row['plugin_id'];
			$this->plugin_service_name = $row['plugin_service_name'];
			$this->plugin_name = $row['plugin_name'];
			// the trick here is that the results are sorted by 'solved' so if the found session is solved, then they all are
			$this->solved = ($row['solved']) ? $this::SOLVED : $this::NOT_SOLVED;
		}
		$this->db->sql_freeresult($result);
		if ($this->solved <> $this::SOLVED)
		{
			// instantiate and initialise the plugin
			$this->plugin = $this->captcha_factory->get_instance($this->plugin_service_name);
			$this->plugin->init($this->type);
			return true;
		}
		return false;
	}

	/**
	*  API function - for the metacaptcha to be available, it must have installed itself
	*  and there has to be at least one captcha plugin configured
	*/
	public function is_available()
	{
		// load language file for pretty display in the ACP dropdown
		$this->language->add_lang('acp_metacaptcha_name', 'v12mike/metacaptcha');

		$sql = 'SELECT COUNT(plugin_service_name) AS plugin_count
			FROM ' . $this->table_metacaptcha_plugins;
		$result = $this->db->sql_query($sql);
		$plugin_count = $this->db->sql_fetchfield('plugin_count');
		$this->db->sql_freeresult($result);

		return ((bool) $plugin_count);
	}

	/**
	*  API function
	*/
	public function has_config()
	{
		return true;
	}

	/**
	*  API function
	*/
	static public function get_name()
	{
		return 'METACAPTCHA';
	}


	function execute()
	{
		if (empty($this->captcha_session_id))
		{
			if (!$this->load_plugin())
			{
				// all plugins have been solved
				return false;
			}
		}
		return $this->plugin->execute();
	}

	/**
	*  API function
	*/
	public function get_template()
	{
		if ($this->solved)
		{
			return false;
		}
		// call plugin function
		$template = $this->plugin->get_template();
		while (!$template)
		{
			// that one is solved choose another plugin
			$this->mark_plugin_solved();
			if ($this->solved)
			{
				return false;
			}
			$template = $this->plugin->get_template();
		}
		return $template;
	}

	/**
	*  API function - we just display an explanation of how metacaptcha works
	*/
	public function get_demo_template()
	{
		return '@v12mike_metacaptcha/captcha_metacaptcha_acp_demo.html';
	}

	/**
	*  API function
	*/
	public function get_hidden_fields()
	{
		$hidden_fields = array();

		$hidden_fields['metacaptcha_session_id'] = $this->captcha_session_id;

		if ($this->solved <> $this::SOLVED)
		{
			// call plugin function and add our own
			$hidden_fields = array_merge($this->plugin->get_hidden_fields(), $hidden_fields);
		}
		return $hidden_fields;
	}

	/**
	*  API function, if sessions are pruned also remove related metacaptcha_session rows
	*/
	public function garbage_collect($type = 0)
	{
		//call garbage collection for all configured plugins
		$sql = 'SELECT plugin_service_name
				FROM ' . $this->table_metacaptcha_plugins	;
		$result = $this->db->sql_query($sql, 3600);

		while ($row = $this->db->sql_fetchrow($result))
		{
			$plugin = $this->captcha_factory->get_instance($this->plugin_service_name);
			$plugin->garbage_collect($type);
		}

		// clear out our stale metacapture sessions
		// Using subquery for SQLite support (instead of using DELETE with LEFT JOIN directly) this however causes the following
		// problem in MySQL "You can't specify target table for update in FROM clause", workaround by adding a derived table on the subquery result
		$sql = 'DELETE FROM ' . $this->table_metacaptcha_sessions . '
			WHERE captcha_session_id IN (
				SELECT derived.captcha_session_id
				FROM (
					SELECT c.captcha_session_id
					FROM ' . $this->table_metacaptcha_sessions . ' c
					LEFT JOIN ' . SESSIONS_TABLE . ' s
						ON (c.user_session_id = s.session_id)
					WHERE s.session_id IS NULL' .
						((empty($type)) ? '' : ' AND c.type = ' . (int) $type) .
					') derived)';
		$this->db->sql_query($sql);
	}

	/**
	*  API function - we don't drop the tables here, as that would
	*  cause the loss of all configured plugins.
	*/
	public function uninstall()
	{
		$this->garbage_collect();
	}

	/**
	*  API function - install
	*/
	public function install()
	{
		// This is handled by migrations when enabling this extension.
		// Because this class extends the Q&A captcha, this function needs to be specified here to prevent generation of the QA captcha tables.
	}

	/**
	*  API function - see what has to be done to validate
	*/
	public function validate()
	{
		$error = false;
		if($this->load_plugin())
		{
			// call plugin validate()
			$error = $this->plugin->validate();
			if (!$error)
			{
				$this->mark_plugin_solved();
				// try for another plugin
				if($this->load_plugin())
				{
					// there is another plugin to be solved);
					$this->language->add_lang(array('metacaptcha'), 'v12mike/metacaptcha');
					$error = $this->language->lang('ANOTHER_CAPTCHA_TO_SOLVE');
				}
			}
		}
		return $error;
	}


	private function mark_plugin_solved()
	{
		// This plugin captcha has been solved, clear it out and load the next one
		$this->plugin->garbage_collect($this->type);
		$sql = 'UPDATE ' . $this->table_metacaptcha_sessions . "
				SET solved = 1
				WHERE captcha_session_id = '" . $this->db->sql_escape($this->captcha_session_id) . "'
					AND plugin_id = " . (int) $this->plugin_id;
		$this->db->sql_query($sql);
	}

	/**
	* See if there is already an entry for the current session.
	*/
	private function load_captcha_session_id()
	{
		$sql = 'SELECT captcha_session_id
			FROM ' . $this->table_metacaptcha_sessions . "
			WHERE
				user_session_id = '" . $this->db->sql_escape($this->user->session_id) . "'
				AND type = " . (int) $this->type;
		$result = $this->db->sql_query_limit($sql, 1);
		$session_id = $this->db->sql_fetchfield('captcha_session_id');
		$this->db->sql_freeresult($result);

		if ($session_id)
		{
			$this->captcha_session_id = $session_id;
		}
		else
		{
			// start a new session if required
			$this->captcha_session_id = md5(unique_id($user->ip));
			$this->init_session_table();
		}
	}

	/**
	*  API function
	*/
	public function get_attempt_count()
	{
		if ($this->solved <> $this::SOLVED)
		{
			return $this->plugin->get_attempt_count();
		}
        // all plugins are done, we don't really care about attempts
		return 0;
	}

	/**
	*  API function
	*/
	public function reset()
	{
		$sql = 'DELETE FROM ' . $this->table_metacaptcha_sessions . "
				WHERE user_session_id = '" . $this->db->sql_escape($this->user->session_id) . "'
					AND type = " . (int) $this->type;
		$this->db->sql_query($sql);

		// we leave the class usable by choosing and new plugin
		$this->load_plugin();
	}

	/**
	*  API function
	*/
	public function is_solved()
	{
		return (bool) ($this->solved === $this::SOLVED);
	}


	/**
	*  API function - The ACP backend, this marks the end of the easy methods
	*/
	public function acp_page($id, $module)
	{
		$this->language->add_lang(array('acp/board'));
		$this->language->add_lang(array('acp_metacaptcha'), 'v12mike/metacaptcha');

		$module->tpl_name = '@v12mike_metacaptcha/captcha_metacaptcha_acp';
		$module->page_title = 'ACP_VC_SETTINGS';//???
		add_form_key($this->acp_form_key);

		$plugin_name = $this->request->variable('plugin_name', '');
		$plugin_id = $this->request->variable('plugin_id', 0);
		$action = $this->request->variable('action', '');
		$plugin_service_name = $this->request->variable('plugin_service_name', '');
		$configure = $this->request->variable('configure', 0);
		$submit = $this->request->variable('submit', false);

		$this->acp_list_url = $module->u_action . "&amp;configure=1&amp;select_captcha=" . $this->get_service_name();

		$this->template->assign_vars(array(
			'U_ACTION'	=> $module->u_action,
			'PLUGIN_ID'	=> $plugin_id ,
			'SERVICE_NAME'	=> $this->get_service_name(),
		));

		// Delete plugin
		if ($plugin_service_name && $action == 'delete')
		{
			// Show confirm box and check for last captcha
			$this->acp_plugin_delete_confirm($plugin_id, $plugin_service_name);
			$this->acp_plugin_list($module);
		}
		// Update the settings for this plugin
		else if ($plugin_service_name && $action == 'update' && check_form_key($this->acp_form_key))
		{
			$this->acp_update_plugin($plugin_id);
			$this->acp_plugin_list($module);
		}
		// Delegate module configuration
		else if ($plugin_service_name && $action == 'edit')
		{
			$config_captcha = $this->captcha_factory->get_instance($plugin_service_name);
			$module->u_action = $module->u_action . "&amp;configure=1&amp;select_captcha=" . $this->get_service_name();
			$config_captcha->acp_page($id, $module);
		}
		else if ($plugin_service_name && $action == 'add')
		{
			// Get possible plugin input data
			$plugin_input = $this->acp_get_plugin_input();

			if (!$this->validate_input($plugin_input))
			{
				$this->template->assign_vars(array(
					'S_ERROR'			=> true,
				));
			}
			else
			{
				$this->acp_insert_plugin($plugin_input);
				$this->acp_plugin_list($module);
			}
		}
		else
			// Show the list?
		{
			$this->acp_plugin_list($module);
		}
	}

	/**
	 * Shows a confirm_box and deletes the captcha plugin when
	 * confirmed. This function displays an error when an admin
	 * tries to delete the last captcha while this captcha plugin is
	 * set as default.
	 *
	 * @param int $plugin_id
	 */
	private function acp_plugin_delete_confirm($plugin_id, $plugin_service_name)
	{
		// Make sure the user is not deleting the last plugin when this plugin is active
		if (!$this->acp_is_last($plugin_id))
		{
			// When the deletion is confirmed
			if (confirm_box(true))
			{
				$this->acp_delete_plugin($plugin_id);
				trigger_error($this->language->lang('PLUGIN_DELETED') . adm_back_link($this->acp_list_url));
			}
			else
			{
				// Show deletion confirm box
				confirm_box(false, $this->language->lang('CONFIRM_OPERATION'), build_hidden_fields(array(
					'plugin_service_name'		=> $plugin_service_name,
					'action'		=> 'delete',
					'configure'		=> 1,
					'select_plugin'	=> $this->get_service_name(),
					))
				);
			}
		}
		else
		{
			// Prevent the deletion of the last plugin since this captcha is set as active
			trigger_error($this->language->lang('METACAPTCHA_LAST_CAPTCHA') . adm_back_link($this->acp_list_url), E_USER_WARNING);
		}
	}

	/**
	*  This handles the list overview
	*/
	public function acp_plugin_list($module)
	{
		// get the available plugins
		$plugins = $this->captcha_factory->get_captcha_types();

		//get the currently configured plugins
		$sql = 'SELECT *
				FROM ' . $this->table_metacaptcha_plugins	. '
				ORDER BY plugin_priority ASC';
		$result = $this->db->sql_query($sql, 3600);

		$this->template->assign_vars(array(
			'S_LIST'			=> true,
		));

		$configured_plugins = array();
		while ($row = $this->db->sql_fetchrow($result))
		{
			$configured_plugins[] = $row['plugin_service_name'];
			$url = $module->u_action . "&amp;configure=1&amp;select_captcha={$this->get_service_name()}&amp;plugin_service_name={$row['plugin_service_name']}&amp;plugin_id={$row['plugin_id']}&amp;";
			$this->template->assign_block_vars('configured_plugins', array(
				'PLUGIN_NAME'		=> $this->language->lang($row['plugin_name']),
				'PLUGIN_SERVICE_NAME'		=> $row['plugin_service_name'],
				'PLUGIN_ID' 		=> $row['plugin_id'],
				'PLUGIN_PRIORITY'	=> strval($row['plugin_priority']),
				'PLUGIN_AVAILABLE'	=> (array_key_exists($row['plugin_service_name'], $plugins['available'])) ? 1 : 0,
				'U_DELETE'			=> "{$url}action=delete",
				'U_EDIT'			=> "{$url}action=edit",
				'U_UPDATE'			=> "{$url}action=update",
			));
		}
		$this->db->sql_freeresult($result);


		foreach ($plugins['available'] as $service_name => $name)
		{
			if (!in_array($service_name, $configured_plugins) && ($service_name <> $this->get_service_name())) // dont show configured plugins as available
			{
				$url = $module->u_action . "&amp;select_captcha=" . $this->get_service_name() . "&amp;plugin_name={$name}&amp;plugin_service_name={$service_name}&amp;configure=1&amp;";

				$this->template->assign_block_vars('available_plugins', array(
					'PLUGIN_NAME'		=> $this->language->lang($name),
					'PLUGIN_AVAILABLE'	=> 1,
					'U_ADD' 			=> "{$url}action=add",
					'U_EDIT'			=> "{$url}action=edit",
				));
			}
		}
		foreach ($plugins['unavailable'] as $service_name => $name)
		{
			if (!in_array($service_name, $configured_plugins) && ($service_name <> THIS_SERVICE_NAME)) // dont show configured plugins as available
			{
				$url = $module->u_action . "&amp;select_captcha=" . $this->get_service_name() . "&amp;plugin_name={$name}&amp;plugin_service_name={$service_name}&amp;configure=1&amp;";

				$this->template->assign_block_vars('available_plugins', array(
					'PLUGIN_NAME'		=> $name,
					'PLUGIN_AVAILABLE'	=> 0,
					'U_ADD' 			=> "{$url}action=add",
					'U_EDIT'			=> "{$url}action=edit",
				));
			}
		}
	}

	/**
	*  Grab a plugin from database and bring it into a format the
	*  editor understands
	*/
	private function acp_get_plugin_data($plugin_id)
	{
		if ($plugin_id)
		{
			$sql = 'SELECT *
				FROM ' . $this->table_metacaptcha_plugins . '
				WHERE plugin_id = ' . (int) $plugin_id;
			$result = $this->db->sql_query($sql);
			$plugin = $this->db->sql_fetchrow($result);
			$this->db->sql_freeresult($result);

			if (!$plugin)
			{
				return false;
			}
			return $plugin;
		}
	}

	/**
	*  Grab a plugin from input and bring it into a format the
	*  editor understands
	*/
	private function acp_get_plugin_input()
	{
		$plugin = array(
			'plugin_id' 			=> $this->request->variable('plugin_id', ''),
			'plugin_name'   		=> $this->request->variable('plugin_name', ''),
			'plugin_service_name'	=> $this->request->variable('plugin_service_name', ''),
			'plugin_priority'		=> $this->request->variable('plugin_priority', 1),
		);

		return $plugin;
	}

	/**
	 * Update parameters of a plugin
	 */
	private function acp_update_plugin()
	{

		$data = $this->acp_get_plugin_input();
		// validate input ???
		$plugin_ary = $data;

		$sql = "UPDATE " . $this->table_metacaptcha_plugins . '
			SET ' . $this->db->sql_build_array('UPDATE', $plugin_ary) . "
			WHERE plugin_id = " . (int) $data['plugin_id'];
		$this->db->sql_query($sql);

		$this->cache->destroy('sql', $this->table_metacaptcha_plugins);
	}

	/**
	 * Insert a plugin
	 * @param mixed $data An array as created from request
	 */
	public function acp_insert_plugin($data)
	{
		$plugin_ary = $data;
		unset ($plugin_ary['plugin_id']);

		$sql = 'INSERT INTO ' . $this->table_metacaptcha_plugins . $this->db->sql_build_array('INSERT', $plugin_ary);
		$this->db->sql_query($sql);

		$this->cache->destroy('sql', $this->table_metacaptcha_plugins);
	}


	/**
	 * Delete a plugin
	 * @param integer $plugin_id
	 */
	public function acp_delete_plugin($plugin_id)
	{
		$tables = array($this->table_metacaptcha_plugins, $this->table_metacaptcha_sessions);
		foreach ($tables as $table)
		{
			$sql = "DELETE FROM $table
				WHERE plugin_id = '" . $plugin_id . "'";
			$this->db->sql_query($sql);
		}

		$this->cache->destroy('sql', $tables);
	}

	/**
	*  Check if the entered data can be inserted/used
	* param mixed $data : an array as created from request
	*/
	public function validate_input($input_data)
	{
		if (!$input_data['plugin_id'])
		{
			// new entry: dont allow duplicate entries
			$sql = 'SELECT COUNT(*) as count
				FROM ' . $this->table_metacaptcha_plugins . "
				WHERE plugin_name = '" . $input_data['plugin_name'] . "'
				OR plugin_service_name = '" . $input_data['plugin_service_name'] . "'";
			$result = $this->db->sql_query($sql);
			$count = $this->db->sql_fetchfield('count');
			$this->db->sql_freeresult($result);
			if ($count)
			{
				return false;
			}
			return true;
		}

		// never allow this (metacaptcha) plugin to be added to the table
		if (!$input_data['plugin_service_name'] == $this->get_service_name())
		{
			return false;
		}
		return true;
	}

	/**
	 * See if there is a configured and available plugin other than
	 * the one we have
	 *
	 * @param integer $plugin_id
	 * @return boolean
	 */
	public function acp_is_last($plugin_id)
	{
		$sql = 'SELECT COUNT(*) as count
			FROM ' . $this->table_metacaptcha_plugins . "
			WHERE plugin_id <> {$plugin_id}";
		$result = $this->db->sql_query($sql);
		$count = $this->db->sql_fetchfield('count');
		$this->db->sql_freeresult($result);
		if ($count)
		{
			return false;
		}
		return true;
	}
}
