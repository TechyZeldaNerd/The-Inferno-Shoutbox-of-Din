<?php

// coding playlist... fuark
// http://www.youtube.com/watch?v=C_lQbLU6RkM
// http://www.youtube.com/watch?v=TPYVPt8GSSc


define('IN_MYBB', true);
//define('MYBB_LOCATION', 'Inferno Shoutbox');

require_once 'global.php';
require_once MYBB_ROOT . 'inc/plugins/inferno/class_core.php';

$inferno = inferno_shoutbox::get_instance();

$allowed_actions = array(
	'getshouts',
	'newshout',
	'getactiveusers',
	'getsmilies',
	'updatestyles',
	'getprivateshouts',
	'getshout',
	'deleteshout',
	'updateshout',
	'openanus',
	'archive'
);

$action = (isset($_GET['action']) ? $_GET['action'] : false);

// Disallow failed get requests or if shoutbox is offline
if (!in_array($action, $allowed_actions) || !$settings['inferno_enabled'])
{
	exit;
}

$lang->load('inferno');

// Shorthand Userinfo
$uid 		= $inferno->userinfo['uid'];
$ugid 		= $mybb->user['usergroup'];
$bold 		= $inferno->userinfo['bold'];
$admin		= $inferno->admin;
$mod		= ($admin) ? true : $inferno->mod;
$italic		= $inferno->userinfo['italic'];
$underline 	= $inferno->userinfo['underline'];
$banned 	= $inferno->banned;
$silenced 	= $inferno->userinfo['silenced'];

if ($action == 'openanus')
{
	echo (int) file_get_contents(MYBB_ROOT . 'inc/plugins/inferno/anus.php');
}

if ($action == 'getshout')
{
	$id = (int) $_GET['id'];

	if ($id)
	{
		$shout = $inferno->get_shout($id);

		if ($shout)
		{
			if ($mod || $uid == $shout['uid'])
			{
				echo json_encode($shout);
			}
		}
	}
}

if ($action == 'updateshout')
{
	$sid = (int) $_POST['sid'];
	$message = trim($_POST['shout']);

	if ($sid && $message)
	{
		$shout = $inferno->get_shout($sid);

		if ($shout)
		{
			if ($mod || $uid == $shout['uid'])
			{
				$inferno->update_shout($sid, $message);
			}
		}
	}
}

if ($action == 'deleteshout')
{
	$sid = (int) $_POST['sid'];

	if ($sid)
	{
		$shout = $inferno->get_shout($sid);

		if ($shout)
		{
			if ($mod || $uid == $shout['uid'])
			{
				$inferno->delete_shout($sid);
			}
		}
	}
}

if ($action == 'getshouts')
{
	$pm = (isset($_GET['id'])) ? intval($_GET['id']) : 0;
	$shouts = $inferno->get_shouts($pm);
	$active_users = count($inferno->fetch_active_users());

	echo $active_users . '<<~!PARSE_SHOUT!~>>';

	if ($banned)
	{
		echo $lang->sprintf($lang->isb_banned_sb, $settings['inferno_shoutbox_title']);
		exit;
	}

	if (!empty($settings['inferno_shoutbox_notice']))
	{
		$div = '<div class="inferno_notice" style="padding-top: 1px; padding-bottom: 1px;padding-bottom: 6px;">';
		echo $div . '<span class="inferno_notice">' . $inferno->render_notice() . '</span></div>';
	}

	if (empty($shouts))
	{
		echo '<i>No ' . (($pm) ? 'private' : '') . ' shouts to display...</i>';
		exit;
	}

	if (!$pm)
	{
		foreach ($shouts as $s)
		{
			$string = '';

			if ($settings['inferno_avatars'])
			{
				$string .= $s['avatar'] . ' ';
			}

			// Echo Me Shout
			if ($s['me'])
			{
				$string .= '*' . $s['username'] . ' ' . $s['shout'] . '*';
			
				if ($mod || $uid == $s['uid'])
				{
					$string = $inferno->make_editable($string, $s['sid']);
				}

				echo $string;
			}
			// Private message
			else if ($s['private'] && $settings['inferno_shoutbox_pm'])
			{
				$to = '';

				if ($s['private'] !== $uid)
				{
					$pminfo = $inferno->fetch_mybb_userinfo($s['private']);
					$to = ' to ' . $pminfo['username'];
				}

				$string .= '[' . $s['timestamp'] . '] <b>[PM' . $to . ']</b> ' . $s['username'] . ' : ' . $s['shout'];

				if ($mod || $uid == $s['uid'])
				{
					$string = $inferno->make_editable($string, $s['sid']);
				}

				echo $string;
			}
			// Echo Standard Shout
			else
			{
				$string .= '[' . $s['timestamp'] . '] ' . $s['username'] . ' : ' . $s['shout'];
				
				if ($mod || $uid == $s['uid'])
				{
					$string = $inferno->make_editable($string, $s['sid']);
				}

				echo $string;
			}
			echo '<br />';
		}
	}
	else
	{
		if ($settings['inferno_shoutbox_pm'])
		{
			foreach ($shouts as $s)
			{
				$string = '[' . $s['timestamp'] . '] ' . $inferno->gen_profile_link($s['username'], $s['uid']) . ' : ' . $s['shout'] . '<br />';

				if ($mod || $uid == $s['uid'])
				{
					$string = $inferno->make_editable($string, $s['sid']);
				}

				echo $string;
			}
		}
		else
		{
			echo 'Private messaging has been disabled by the administrators.';
		}
	}
}

if ($action == 'updatestyles')
{
	$allowed_colors = $inferno->get_setting_array('shoutbox_color');
	$allowed_fonts  = $inferno->get_setting_array('shoutbox_font');

	if (!isset($_POST['styles']) || $banned)
	{
		exit;
	}

	$styles = json_decode($_POST['styles'], true);

	$db_color		= in_array($styles['color'], $allowed_colors) ? $db->escape_string($styles['color']) : '';
	$db_font  		= in_array($styles['font'], $allowed_fonts)   ? $db->escape_string($styles['font'])  : '';
	$db_bold  		= ($styles['bold']) ? 1 : 0;
	$db_italic 		= ($styles['italic']) ? 1 : 0;
	$db_underline	= ($styles['underline']) ? 1 : 0;

	echo $db_color;

	$dbinfo = array(
		'bold'		=> $db_bold,
		'italic'	=> $db_italic,
		'underline'	=> $db_underline,
		'font'		=> $db_font,
		'color'		=> $db_color
	);

	$inferno->update_styles($dbinfo);
}

if ($action == 'getsmilies')
{
	$smilies = array();
	$limit = $settings['inferno_smilies_limit'] ? ' LIMIT ' . $settings['inferno_smilies_limit'] : '';
	$query = $db->query("SELECT * FROM " . TABLE_PREFIX . "smilies" . $limit);

	while ($s = $db->fetch_array($query))
	{
		$smilies[] = $s;
	}

	shuffle($smilies);
	$html = '';

	foreach ($smilies as $s)
	{
		$html .= '<a href="javascript:void(0)" onclick="javascript: inferno.append(\'' . $s['find'] . '\'); return false;"><img title="' . $s['name'] . '" src="' . $s['image'] . '" /></a> ';
	}

	echo trim($html);
}

if ($action == 'getactiveusers')
{
	$num = (isset($_GET['num'])) ? true : false;

	if ($banned)
	{
		echo $lang->sprintf($lang->isb_banned_sb, $settings['inferno_shoutbox_title']);
		exit;
	}

	$active_users = $inferno->fetch_active_users();

	if ($num)
	{
		echo count($active_users);
	}
	else
	{
		echo $lang->sprintf($lang->isb_activeusers, count($active_users)) . '<br />';
		//echo 'Currently Active Shoutbox Users: ' . count($active_users) . '<br />';
		echo (count($active_users) == 0) ? 'No users are currently active.' : implode(', ', $active_users);
	}
}

if ($action == 'newshout')
{
	$shout = trim($_POST['shout']);
	$pmid = intval($_POST['pmid']);
	$protected_groups = $inferno->get_setting_array('usergroups_protected', ',');

	$hide_admin = $settings['inferno_alert_admincommands'];

	if ($banned)
	{
		exit;
	}

	$inferno->control_flood();

	if ($pmid)
	{
		if ($settings['inferno_shoutbox_pm'])
		{
			$inferno->create_shout($uid, $shout, false, $pmid);
		}
		exit;
	}

	// Admin and mod commands
	if ($mod)
	{
		// Prune entire shoutbox
		if ($shout == '/' . $lang->isb_c_prune)
		{
			$inferno->prune();
			$inferno->create_shout($uid, $lang->isb_prune_msg, true);
			exit;
		}

		// Prune a certain user
		if (preg_match('/^\\/' . preg_quote($lang->isb_c_prune) . '[\s]+(.*)$/', $shout, $matches))
		{
			$mybb_user = $inferno->fetch_mybb_userinfo($matches[1]);

			if ($mybb_user)
			{
				if (in_array($mybb_user['usergroup'], $protected_groups))
				{
					$inferno->create_shout($uid, $lang->sprintf($lang->isb_protected, $mybb_user['username']), false, $uid);
					exit;
				}

				$inferno->prune($mybb_user['uid']);

				if ($admin && $hide_admin)
				{
					exit;
				}

				$inferno->create_shout($uid, $lang->sprintf($lang->isb_prune_user_msg, $mybb_user['username']), true);
			}
			else
			{
				$inferno->create_shout($uid, $lang->sprintf($lang->isb_not_exists, $matches[1]), false, $uid);
			}
			exit;
		}

		// Set a notice
		if (preg_match('/^\\/' . preg_quote($lang->isb_c_notice) . '[\s]+(.*)$/', $shout, $matches))
		{
			$inferno->update_notice($matches[1]);
			exit;
		}

		// Remove a notice
		if ($shout == '/' . $lang->isb_c_removenotice)
		{
			$inferno->update_notice();
			exit;
		}

		// Fetch banlist
		if ($shout == '/' . $lang->isb_c_banlist)
		{
			$banlist = $inferno->gen_banlist();
			$inferno->create_shout($uid, $banlist, false, $uid);
			exit;
		}

		// Ban a certain user
		if (preg_match('/^\\/' . preg_quote($lang->isb_c_ban) . '[\s]+(.*)$/', $shout, $matches))
		{
			$mybb_user = $inferno->fetch_mybb_userinfo($matches[1]);

			if ($mybb_user)
			{

				if (in_array($mybb_user['usergroup'], $protected_groups))
				{
					$inferno->create_shout($uid, $lang->sprintf($lang->isb_protected, $mybb_user['username']), false, $uid);
					exit;
				}

				$user = $inferno->fetch_userinfo($mybb_user['uid']);

				if (!$user['banned'])
				{
					$inferno->toggle_ban($user['uid'], true);

					if ($admin && $hide_admin)
					{
						exit;
					}

					$inferno->create_shout($uid, $lang->sprintf($lang->isb_ban_msg, $mybb_user['username']), true);
				}
			}
			else
			{
				$inferno->create_shout($uid, $lang->sprintf($lang->isb_not_exists, $matches[1]), false, $uid);
			}
			exit;
		}

		// Unban a certain user
		if (preg_match('/^\\/' . preg_quote($lang->isb_c_unban) . '[\s]+(.*)$/', $shout, $matches))
		{
			$mybb_user = $inferno->fetch_mybb_userinfo($matches[1]);

			if ($mybb_user)
			{

				if (in_array($mybb_user['usergroup'], $protected_groups))
				{
					$inferno->create_shout($uid, $lang->sprintf($lang->isb_protected, $mybb_user['username']), false, $uid);
					exit;
				}

				$user = $inferno->fetch_userinfo($mybb_user['uid']);

				if ($user['banned'])
				{
					$inferno->toggle_ban($user['uid'], false);

					if ($admin && $hide_admin)
					{
						exit;
					}

					$inferno->create_shout($uid, $lang->sprintf($lang->isb_unban_msg, $mybb_user['username']), true);
				}
			}
			else
			{
				$inferno->create_shout($uid, $lang->sprintf($lang->isb_not_exists, $matches[1]), false, $uid);
			}
			exit;
		}

		// Fetch silencelist
		if ($shout == '/' . $lang->isb_c_silencelist)
		{
			$silencelist = $inferno->gen_silencelist();
			$inferno->create_shout($uid, $silencelist, false, $uid);
			exit;
		}

		// Silence a certain user
		if (preg_match('/^\\/' . preg_quote($lang->isb_c_silence) . '[\s]+(.*)$/', $shout, $matches))
		{
			$mybb_user = $inferno->fetch_mybb_userinfo($matches[1]);

			if ($mybb_user)
			{

				if (in_array($mybb_user['usergroup'], $protected_groups))
				{
					$inferno->create_shout($uid, $lang->sprintf($lang->isb_protected, $mybb_user['username']), false, $uid);
					exit;
				}

				$user = $inferno->fetch_userinfo($mybb_user['uid']);

				if (!$user['silenced'])
				{
					$inferno->toggle_silence($user['uid'], true);

					if ($admin && $hide_admin)
					{
						exit;
					}

					$inferno->create_shout($uid, $lang->sprintf($lang->isb_silence_msg, $mybb_user['username']), true);
				}
			}
			else
			{
				$inferno->create_shout($uid, $lang->sprintf($lang->isb_not_exists, $matches[1]), false, $uid);
			}
			exit;
		}

		// Unsilence a certain user
		if (preg_match('/^\\/' . preg_quote($lang->isb_c_unsilence) . '[\s]+(.*)$/', $shout, $matches))
		{
			$mybb_user = $inferno->fetch_mybb_userinfo($matches[1]);

			if ($mybb_user)
			{

				if (in_array($mybb_user['usergroup'], $protected_groups))
				{
					$inferno->create_shout($uid, $lang->sprintf($lang->isb_protected, $mybb_user['username']), false, $uid);
					exit;
				}

				$user = $inferno->fetch_userinfo($mybb_user['uid']);

				if ($user['silenced'])
				{
					$inferno->toggle_silence($user['uid'], false);

					if ($admin && $hide_admin)
					{
						exit;
					}

					$inferno->create_shout($uid, $lang->sprintf($lang->isb_unsilence_msg, $mybb_user['username']), true);
				}
			}
			else
			{
				$inferno->create_shout($uid, $lang->sprintf($lang->isb_not_exists, $matches[1]), false, $uid);
			}
			exit;
		}
	}

	// Admin only commands
	if ($admin)
	{
		if (preg_match('/^\\/' . preg_quote($lang->isb_c_say) . '[\s]+([^;]+);[\s]*(.+)$/', $shout, $matches))
		{
			$mybb_user = $inferno->fetch_mybb_userinfo($matches[1]);

			if ($mybb_user)
			{
				$inferno->create_shout($mybb_user['uid'], $matches[2]);
			}
			else
			{
				$inferno->create_shout($uid, $lang->sprintf($lang->isb_not_exists, $matches[1]), false, $uid);
			}
			exit;
		}

		// if ($shout == '/debug')
		// {
		// 	for ($i = 0; $i < 1000; $i++)
		// 	{
		// 		$inferno->create_shout($uid, $i + 1);
		// 	}
		// 	exit;
		// }
	}

	// Me message
	if (preg_match('/^\\/' . preg_quote($lang->isb_c_me) . '[\s]+(.*)$/', $shout, $matches))
	{
		$inferno->create_shout($uid, $matches[1], true);
		exit;
	}

	// Private message
	if (preg_match('/^\\/' . preg_quote($lang->isb_c_pm) . '[\s]+([^;]+);[\s]*(.+)$/', $shout, $matches) && $settings['inferno_shoutbox_pm'])
	{
		$mybb_user = $inferno->fetch_mybb_userinfo($matches[1]);

		if ($mybb_user)
		{
			$inferno->create_shout($uid, $matches[2], false, $mybb_user['uid']);
		}
		else
		{
			$inferno->create_shout($uid, $lang->sprintf($lang->isb_not_exists, $matches[1]), false, $uid);
		}
		exit;
	}

	$inferno->create_shout($uid, $shout);
}

if ($action == 'archive')
{
	add_breadcrumb($settings['inferno_shoutbox_title'] . ' ' . $lang->isb_archive, 'infernoshout.php?action=archive');

	if (!$settings['inferno_archive'] || $banned)
	{
		$title = $settings['inferno_shoutbox_title'] . $lang->isb_archive_dt;
		$error = $lang->isb_archive_disabled;
		if ($banned)
		{
			$error = $lang->isb_archive_noview;
		}
		eval("\$archive = \"".$templates->get("error")."\";");
	}
	else
	{
		$total_pages = $inferno->count_total_shouts();
		$shouts_per_page = $settings['inferno_archive_shouts_per_page'];
		$shouts_per_page = ($shouts_per_page > 0 && $shouts_per_page <= $total_pages) ? $shouts_per_page : 50;
		$page = (isset($_GET['page']) ? (int) round($_GET['page']) : 1);
		$page = ($page > 0 && $page <= $total_pages) ? $page : 1;
		$total_pages = ceil($inferno->count_total_shouts() / $shouts_per_page);
		$offset = ($page <= 1 || $page > $total_pages) ? 0 : ($page - 1) * $shouts_per_page;
		$plugins->run_hooks('inferno_archive_start');
		eval("\$archive = \"".$templates->get("inferno_archive")."\";");
	}

	output_page($archive);
}
