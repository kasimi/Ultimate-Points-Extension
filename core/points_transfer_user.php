<?php
/**
*
* @package phpBB Extension - Ultimate Points
* @copyright (c) 2015 dmzx & posey - http://www.dmzx-web.net
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace dmzx\ultimatepoints\core;

/**
* @package Ultimate Points
*/

class points_transfer_user
{
	/** @var \dmzx\ultimatepoints\core\functions_points */
	protected $functions_points;

	/** @var \phpbb\auth\auth */
	protected $auth;

	/** @var \phpbb\template\template */
	protected $template;

	/** @var \phpbb\user */
	protected $user;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var \phpbb\request\request */
	protected $request;

	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\controller\helper */
	protected $helper;

	/** @var string */
	protected $phpEx;

	/** @var string phpBB root path */
	protected $phpbb_root_path;

	/**
	* The database tables
	*
	* @var string
	*/
	protected $points_config_table;

	protected $points_log_table;

	protected $points_values_table;

	/**
	* Constructor
	*
	* @param \phpbb\template\template		 	$template
	* @param \phpbb\user						$user
	* @param \phpbb\db\driver\driver_interface	$db
	* @param \phpbb\request\request		 		$request
	* @param \phpbb\config\config				$config
	* @param \phpbb\controller\helper		 	$helper
	* @param									$phpEx
	* @param									$phpbb_root_path
	* @param string 							$points_config_table
	* @param string 							$points_log_table
	* @param string 							$points_values_table
	*
	*/

	public function __construct(\dmzx\ultimatepoints\core\functions_points $functions_points, \phpbb\auth\auth $auth, \phpbb\template\template $template, \phpbb\user $user, \phpbb\db\driver\driver_interface $db, \phpbb\request\request $request, \phpbb\config\config $config, \phpbb\controller\helper $helper, $phpEx, $phpbb_root_path, $points_config_table, $points_log_table, $points_values_table)
	{
		$this->functions_points		= $functions_points;
		$this->auth					= $auth;
		$this->template 			= $template;
		$this->user 				= $user;
		$this->db 					= $db;
		$this->request 				= $request;
		$this->config 				= $config;
		$this->helper 				= $helper;
		$this->phpEx 				= $phpEx;
		$this->phpbb_root_path 		= $phpbb_root_path;
		$this->points_config_table 	= $points_config_table;
		$this->points_log_table 	= $points_log_table;
		$this->points_values_table 	= $points_values_table;
	}

	var $u_action;

	function main($checked_user)
	{
		add_form_key('transfer_user');

		// Get all point config names and config values
		$sql = 'SELECT config_name, config_value
				FROM ' . $this->points_config_table;
		$result = $this->db->sql_query($sql);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$points_config[$row['config_name']] = $row['config_value'];
		}
		$this->db->sql_freeresult($result);

		// Get all values
		$sql = 'SELECT *
				FROM ' . $this->points_values_table;
		$result = $this->db->sql_query($sql);
		$points_values = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		// Grab the message variable
		$message = $this->request->variable('comment', '', true);

		// Check, if transferring is allowed
		if (!$points_config['transfer_enable'])
		{
			$message = $this->user->lang['TRANSFER_REASON_TRANSFER'] . '<br /><br /><a href="' . $this->helper->route('dmzx_ultimatepoints_controller') . '">&laquo; ' . $this->user->lang['BACK_TO_PREV'] . '</a>';
			trigger_error($message);
		}

		// Check, if user is allowed to use the transfer module
		if (!$this->auth->acl_get('u_use_transfer'))
		{
			$message = $this->user->lang['NOT_AUTHORISED'] . '<br /><br /><a href="' . $this->helper->route('dmzx_ultimatepoints_controller') . '">&laquo; ' . $this->user->lang['BACK_TO_PREV'] . '</a>';
			trigger_error($message);
		}

		// Add part to bar
		$this->template->assign_block_vars('navlinks', array(
			'U_VIEW_FORUM'	=> $this->helper->route('dmzx_ultimatepoints_controller', array('mode' => 'transfer_user')),
			'FORUM_NAME'	=>	sprintf($this->user->lang['TRANSFER_TITLE'], $this->config['points_name']),
		));

		$submit = (isset($_POST['submit'])) ? true : false;
		if ($submit)
		{
			if (!check_form_key('transfer_user'))
			{
				trigger_error('FORM_INVALID');
			}

			// Grab need variables for the transfer
			$am 		= round($this->request->variable('amount', 0.00),2);
			$comment	= $this->request->variable('comment', '', true);
			$username1 	= $this->request->variable('username', '', true);
			$username 	= strtolower($username1);

			// Select the user data to transfer to
			$sql_array = array(
				'SELECT'	=> '*',
				'FROM'		=> array(
					USERS_TABLE => 'u',
				),
				'WHERE'		=> 'username_clean = "' . $this->db->sql_escape(utf8_clean_string($username)) . '"',
			);
			$sql = $this->db->sql_build_query('SELECT', $sql_array);
			$result = $this->db->sql_query($sql);
			$transfer_user = $this->db->sql_fetchrow($result);
			$this->db->sql_freeresult($result);

			if ($transfer_user == null)
			{
				$message = $this->user->lang['TRANSFER_NO_USER_RETURN'] . '<br /><br /><a href="' . $this->helper->route('dmzx_ultimatepoints_controller', array('mode' => 'transfer_user')) . '">&laquo; ' . $this->user->lang['BACK_TO_PREV'] . '</a>';
				trigger_error($message);
			}

			// Select the old user_points from user_id to transfer to
			$sql_array = array(
				'SELECT'	=> 'user_points',
				'FROM'		=> array(
					USERS_TABLE => 'u',
				),
				'WHERE'		=> 'user_id = ' . (int) $transfer_user['user_id'],
			);
			$sql = $this->db->sql_build_query('SELECT', $sql_array);
			$result = $this->db->sql_query($sql);
			$transfer_user_old_points = (int) $this->db->sql_fetchfield('user_points');
			$this->db->sql_freeresult($result);

			// Check, if the sender has enough cash
			if ($this->user->data['user_points'] < $am)
			{
				$message = sprintf($this->user->lang['TRANSFER_REASON_MINPOINTS'], $this->config['points_name']) . '<br /><br /><a href="' . $this->helper->route('dmzx_ultimatepoints_controller', array('mode' => 'transfer_user')) . '">&laquo; ' . $this->user->lang['BACK_TO_PREV'] . '</a>';
				trigger_error($message);
			}

			// Check, if the amount is 0 or below
			if ($am <= 0)
			{
				$message = sprintf($this->user->lang['TRANSFER_REASON_UNDERZERO'], $this->config['points_name']) . '<br /><br /><a href="' . $this->helper->route('dmzx_ultimatepoints_controller', array('mode' => 'transfer_user')) . '">&laquo; ' . $this->user->lang['BACK_TO_PREV'] . '</a>';
				trigger_error($message);
			}

			// Check, if user is trying to send to himself
			if ($this->user->data['user_id'] == $transfer_user['user_id'])
			{
				$message = sprintf($this->user->lang['TRANSFER_REASON_YOURSELF'], $this->config['points_name']) . '<br /><br /><a href="' . $this->helper->route('dmzx_ultimatepoints_controller', array('mode' => 'transfer_user')) . '">&laquo; ' . $this->user->lang['BACK_TO_PREV'] . '</a>';
				trigger_error($message);
			}

			// Add cash to receiver
			$this->functions_points->add_points($transfer_user['user_id'], $am);

			// Remove cash from sender
			$this->functions_points->substract_points($this->user->data['user_id'], $am);

			// Get current time for log
			$current_time = time();

			// Add transferring information to the log
			$text = utf8_normalize_nfc($message);

			$sql = 'INSERT INTO ' . $this->points_log_table . ' ' . $this->db->sql_build_array('INSERT', array(
				'point_send'	=> (int) $this->user->data['user_id'],
				'point_recv'	=> (int) $transfer_user['user_id'],
				'point_amount'	=> $am,
				'point_sendold'	=> $this->user->data['user_points'] ,
				'point_recvold'	=> $transfer_user_old_points,
				'point_comment'	=> $text,
				'point_type'	=> '1',
				'point_date'	=> $current_time,
			));
			$this->db->sql_query($sql);

			// Send pm to receiver, if PM is enabled
			if (!$points_config['transfer_pm_enable'] == 0 && $transfer_user['user_allow_pm'])
			{

				$points_name = $this->config['points_name'];
				$comment = $this->db->sql_escape($comment);
				$pm_subject	= utf8_normalize_nfc(sprintf($this->user->lang['TRANSFER_PM_SUBJECT']));
				$pm_text	= utf8_normalize_nfc(sprintf($this->user->lang['TRANSFER_PM_BODY'], $am, $points_name, $text));

				$poll = $uid = $bitfield = $options = '';
				generate_text_for_storage($pm_subject, $uid, $bitfield, $options, false, false, false);
				generate_text_for_storage($pm_text, $uid, $bitfield, $options, true, true, true);

				$pm_data = array(
					'address_list'		=> array ('u' => array($transfer_user['user_id'] => 'to')),
					'from_user_id'		=> $this->user->data['user_id'],
					'from_username'		=> $this->user->data['username'],
					'icon_id'			=> 0,
					'from_user_ip'		=> '',

					'enable_bbcode'		=> true,
					'enable_smilies'	=> true,
					'enable_urls'		=> true,
					'enable_sig'		=> true,

					'message'		=> $pm_text,
					'bbcode_bitfield'	=> $bitfield,
					'bbcode_uid'		=> $uid,
				);

				submit_pm('post', $pm_subject, $pm_data, false);
			}

			// Change $username back to regular username
			$sql_array = array(
				'SELECT'	=> 'username',
				'FROM'		=> array(
					USERS_TABLE => 'u',
				),
				'WHERE'		=> 'user_id = ' . (int) $transfer_user['user_id'],
			);
			$sql = $this->db->sql_build_query('SELECT', $sql_array);

			$result = $this->db->sql_query($sql);
			$show_user = $this->db->sql_fetchfield('username');
			$this->db->sql_freeresult($result);

			// Show the successful transfer message
			$message = sprintf($this->user->lang['TRANSFER_REASON_TRANSUCC'], $this->functions_points->number_format_points($am), $this->config['points_name'], $show_user) . '<br /><br /><a href="' . $this->helper->route('dmzx_ultimatepoints_controller', array('mode' => 'transfer_user')) . '">&laquo; ' . $this->user->lang['BACK_TO_PREV'] . '</a>';
			trigger_error($message);

			$this->template->assign_vars(array(
				'U_ACTION'				=> $this->u_action,
			));
		}

		$this->template->assign_vars(array(
			'USER_POINTS'				=> sprintf($this->functions_points->number_format_points($checked_user['user_points'])),
			'POINTS_NAME'				=> $this->config['points_name'],
			'POINTS_COMMENTS'			=> ($points_config['comments_enable']) ? true : false,
			'LOTTERY_NAME'				=> $points_values['lottery_name'],
			'BANK_NAME'					=> $points_values['bank_name'],

			'L_TRANSFER_DESCRIPTION'	=> sprintf($this->user->lang['TRANSFER_DESCRIPTION'], $this->config['points_name']),

			'U_TRANSFER_USER'			=> $this->helper->route('dmzx_ultimatepoints_controller', array('mode' => 'transfer_user')),
			'U_LOGS'					=> $this->helper->route('dmzx_ultimatepoints_controller', array('mode' => 'logs')),
			'U_LOTTERY'					=> $this->helper->route('dmzx_ultimatepoints_controller', array('mode' => 'lottery')),
			'U_BANK'					=> $this->helper->route('dmzx_ultimatepoints_controller', array('mode' => 'bank')),
			'U_ROBBERY'					=> $this->helper->route('dmzx_ultimatepoints_controller', array('mode' => 'robbery')),
			'U_INFO'					=> $this->helper->route('dmzx_ultimatepoints_controller', array('mode' => 'info')),
			'U_FIND_USERNAME'			=> append_sid("{$this->phpbb_root_path}memberlist.{$this->phpEx}", "mode=searchuser&amp;form=post&amp;field=username"),
			'U_USE_TRANSFER'			=> $this->auth->acl_get('u_use_transfer'),
			'U_USE_LOGS'				=> $this->auth->acl_get('u_use_logs'),
			'U_USE_LOTTERY'				=> $this->auth->acl_get('u_use_lottery'),
			'U_USE_BANK'				=> $this->auth->acl_get('u_use_bank'),
			'U_USE_ROBBERY'				=> $this->auth->acl_get('u_use_robbery'),

			'S_ALLOW_SEND_PM'			=> $this->auth->acl_get('u_sendpm'),
			));

		// Generate the page
		page_header(sprintf($this->user->lang['TRANSFER_TITLE'], $this->config['points_name']));

		// Generate the page template
		$this->template->set_filenames(array(
			'body' => 'points/points_transfer_user.html',
		));

		page_footer();
	}
}
