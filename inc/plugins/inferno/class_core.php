<?php

/**
 * Inferno Shoutbox Class
 * Project Started: 12/16/2013
 *
 * @author Mattbox Solutions
 */
//Uhh, isn't not having this a security risk? Maybe this is what the post was referencing.

if(!defined("IN_MYBB"))
{
    die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

//Include plugin library
if(!defined("PLUGINLIBRARY"))
{
    define("PLUGINLIBRARY", MYBB_ROOT."inc/plugins/pluginlibrary.php");
}

class inferno_shoutbox
{
	public static $_instance = null;
	public $mybb;
	public $db;
	public $lang;
	public $settings;
	public $userinfo;
	public $admin = false;
	public $mod = false;
	public $banned = true;
	public $banned_usergroup = true;
	public $anus = false;
	public $version = '1.3.1';
	public $debug = false;

	/**
	 * Constructor Method
	 *
	 * @return void
	 */
	private function __construct()
	{
		global $mybb, $db, $settings, $footer, $lang;

		$this->mybb 	=& $mybb;
		$this->db 	=& $db;
		$this->settings =& $settings;
		$this->lang 	=& $lang;

		if ($this->is_installed())
		{
			$this->userinfo = $this->fetch_userinfo();
			$this->admin = $this->get_permissions('admin');
			$this->mod = $this->get_permissions('mod');
			$this->banned = $this->is_banned();
			$this->banned_usergroup = $this->is_banned(true);
			$this->anus = (bool) $this->settings['inferno_shoutbox_anus'];
//			$this->please_support_the_developer($footer);
		}
	}

	/**
	 * Inferno Instance Method
	 *
	 * @return object
	 */
	public static function get_instance()
	{
		if (!isset(self::$_instance))
		{
			self::$_instance = new inferno_shoutbox();
		}
		return self::$_instance;
	}

	public function is_banned($usergroup = false)
	{
		 $banned_groups = $this->get_setting_array('usergroups_banned', ',');
		 $posts = (int) $this->settings['inferno_minimum_posts'];

		 if ($posts && $this->mybb->user['postnum'] < $posts)
		 {
		 	return true;
		 }

		 if (!$usergroup)
		 {
		 	return ($this->userinfo['banned'] || in_array($this->mybb->user['usergroup'], $banned_groups));
		 }
		 else
		 {
		 	return (in_array($this->mybb->user['usergroup'], $banned_groups));
		 }
	}

	public function is_valid_shout($shout)
	{
		$shout = trim($shout);

		if (strlen($shout) > 0 && strlen($shout) < $this->settings['inferno_shout_max_chars'])
		{
			return true;
		}
		return false;
	}

	public function update_anus()
	{
		if ($this->anus)
		{
			$fp = fopen(MYBB_ROOT . 'inc/plugins/inferno/anus.php', 'w');
			fwrite($fp, TIME_NOW);
			return fclose($fp);
		}
		return false;
	}

	public function clean_shout($shout)
	{
		$shout = $this->db->escape_string($shout);
		return $shout;
	}

	public function set_activity()
	{
		return my_setcookie('inferno_wait', (TIME_NOW + $this->settings['inferno_shoutbox_flood'])); 
	}

	public function control_flood()
	{
		$now = TIME_NOW;
		$wait = $this->mybb->cookies['inferno_wait'];

		if ($now < $wait)
		{
			exit;
		}
		else
		{
			$this->set_activity();
		}
	}

	public function create_shout($uid, $shout, $me = 0, $pm = 0)
	{
		$uid = (int) $uid;
		$me = ($me) ? 1 : 0;
		$pm = (int) $pm;

		if ($uid > 0 && !$this->fetch_userinfo($uid))
		{
			$this->create_user($uid);
		}

		if (!$this->is_valid_shout($shout))
		{
			return false;
		}

		$query1 = $this->db->insert_query('inferno_shout', array(
			'sid' 		=> '0',
			'uid' 		=> $uid,
			'shout' 	=> $this->clean_shout($shout),
			'me' 		=> $me,
			'private' 	=> $pm,
			'timestamp'	=> TIME_NOW
		));

		$query2 = $this->db->update_query('inferno_user', array(
			'dateline' => TIME_NOW
		), "uid='{$uid}'");

		$this->update_anus();

		return ($query1 && $query2);
	}

	public function prune($uid = 0)
	{
		$uid = (int) $uid;
		if ($uid)
		{
			$query = $this->db->delete_query("inferno_shout WHERE uid = '{$uid}';");
			$this->log('Has pruned all shouts by a user (UID: ' . $uid . ')');
		}
		else
		{
			$query = $this->db->query("TRUNCATE " . TABLE_PREFIX . "inferno_shout");
			$this->log('Has pruned the entire shoutbox');
		}
		return $query;
	}

	public function delete_shout($shoutid)
	{
		$shoutid = (int) $shoutid;
		$shoutinfo = $this->get_shout($shoutid);
		$query = $this->db->delete_query("inferno_shout WHERE sid='{$shoutid}'");
		if ($query)
		{
			$this->update_anus();
			$this->log('Deleted shout by a user (UID: ' . $shoutinfo['uid'] . ') (ShoutID: ' . $shoutid . ')');
		}
		return ($query) ? true : false;
	}

	public function is_admin($groupid)
	{
		$admins = $this->get_setting_array('usergroups_admin', ',');
		return in_array($groupid, $admins);
	}

	public function get_shouts($pmonly = 0, $archive = false, $end_limit = 0)
	{
		require_once MYBB_ROOT . 'inc/class_parser.php';
		$parser = new postParser();
		$pmonly = intval($pmonly);

		$parse_options = array(
			'allow_html' 	=> false,
			'allow_mycode' 	=> (bool) $this->settings['inferno_allow_mycode'],
			'allow_smilies'	=> (bool) $this->settings['inferno_smilies'],
			'allow_imgcode' => true,
			'filter_badwords' => (bool) $this->settings['inferno_filter_badwords']
		);

		$shouts = array();
		$limit = ($archive !== false) ? (int) $archive : (int) $this->settings['inferno_shouts_display'];
		$limit = (!$end_limit) ? $limit : $limit . ', ' . (int) $end_limit;
//		$order = ($this->settings['inferno_shout_order']) ? 'DESC' : 'ASC';
		$order = ($this->settings['inferno_shout_order']);
		if (!$pmonly)
		{
			$where = "
			WHERE
			(
				(
					(s.private = '0')
					OR
					(s.private = '{$this->userinfo['uid']}')
					OR
					(s.private != '0' AND s.uid = '{$this->userinfo['uid']}')
				)
				AND
				(
					(u.silenced = '0')
			 		OR
					(u.silenced = '1' AND u.uid = '{$this->userinfo['uid']}')
				)
			)";
		}
		else
		{
			$where = "
			WHERE
			(
				(
					(s.uid = '{$this->userinfo['uid']}' AND s.private = '{$pmonly}')
					OR
					(s.uid = '{$pmonly}' AND s.private = '{$this->userinfo['uid']}')
				)
				AND
				(
					(u.silenced = '0')
			 		OR
					(u.silenced = '1' AND u.uid = '{$this->userinfo['uid']}')
				)
			)";
		}

		$result = $this->db->query("
			SELECT s.*, u.*
			FROM " . TABLE_PREFIX . "inferno_shout s
			LEFT JOIN " . TABLE_PREFIX . "inferno_user u
			ON s.uid = u.uid
			{$where}
			ORDER BY s.sid DESC
			LIMIT {$limit};
		");
		$usercache = array();

		while ($row = $this->db->fetch_array($result))
		{
			// Markup Username
			if (isset($usercache[$row['uid']]))
			{
				$shout_user = $usercache[$row['uid']];
			}
			else
			{
				$shout_user = get_user($row['uid']);
				$usercache[$row['uid']] = $shout_user;
			}

			$avi_pixels = intval($this->settings['inferno_avatars']);
			$row['avatar'] = '<img title="' . $shout_user['username'] . '\'s Avatar" src="' . $this->settings['bburl'] . '/' . $shout_user['avatar'] . '" height="' . $avi_pixels .'" width="' . $avi_pixels .'" />';
			$row['username'] = format_name($shout_user['username'], $shout_user['usergroup'], $shout_user['displaygroup']); 

			// markup so username is clickable
			if (!$pmonly && $this->settings['inferno_shoutbox_pm'] && $archive === false)
			{
				$row['username'] = '<a href="#" onclick="javascript: inferno.add_private_chat(' . $row['uid'] . ', \'' . $shout_user['username'] . '\'); return false;">' . $row['username'] . '</a>';
			}

			// Remove unwanted mycode if not admin
			if (!$this->is_admin($shout_user['usergroup']))
			{
				$row['shout'] = $this->strip_mycode($row['shout']);
			}
			$row['shout'] = stripslashes($row['shout']);			
			$row['shout'] = $parser->parse_message($row['shout'], $parse_options);

			if ($this->settings['inferno_shout_markup'])
			{
				$css = $this->render_css($row);

				if (!empty($css))
				{
					$row['shout'] = '<span style="' . $css . '">' . $row['shout'] . '</span>';
				}
			}

			// Markup Timestamp
			$unixtime = $row['timestamp'];
			// MyBB your shitty my_date function can get fucked by a cactus
			$row['timestamp'] = my_date($this->settings['dateformat'], $unixtime);
			$row['timestamp'] .= ' ' . my_date($this->settings['timeformat'], $unixtime);

			$shouts[] = $row;
		}
		if (!$order) {
			$shouts_reversed = array_reverse($shouts);
			$shouts = $shouts_reversed;
		}
		return $shouts;
	}

	public function strip_mycode($string)
	{
		$disallowed = explode(',', $this->settings['inferno_banned_mycode']);

		foreach ($disallowed as $code)
        {
       		$string = preg_replace_callback('#(\[' . $code . '(?:.*?)\](.*?)\[\/' . $code . '\])#', create_function('$matches', 'return empty($matches[2]) ? "." : $matches[2];'), $string);
        }

		return $string;
	}

	public function update_shout($sid, $value)
	{
		$sid = (int) $sid;
		$value = $this->db->escape_string($value);

		$query = $this->db->update_query('inferno_shout', array(
			'shout' => $value
		), "sid='{$sid}'");

		if ($query)
		{
			$this->update_anus();
		}
		return ($query) ? true : false;
	}

	public function get_shout($id)
	{
		$id = (int) $id;
		$query = $this->db->query("SELECT * FROM " . TABLE_PREFIX . "inferno_shout WHERE sid='{$id}'");
		$result = $this->db->fetch_array($query);
		return ($result) ? $result : false;
	}

	public function make_editable($shout, $id)
	{
		$string = '<span ondblclick="javascript: inferno.edit_shout(' . $id . '); return false;">' . $shout . '</span>';
		return $string;
	}

	public function render_css($array)
	{
		$css = '';
		if ($array['bold'])
		{
			$css .= 'font-weight:bold;';
		}
		if ($array['italic'])
		{
			$css .= 'font-style:italic;';
		}
		if ($array['underline'])
		{
			$css .= 'text-decoration:underline;';
		}
		if (!empty($array['color']))
		{
			$css .= 'color:' . $array['color'] . ';';
		}
		if (!empty($array['font']))
		{
			$css .= 'font-family:' . $array['font'] . ';';
		}
		return $css;
	}

	public function fetch_active_users()
	{
		$users = array();
		$result = $this->db->query("
			SELECT *
			FROM " . TABLE_PREFIX . "inferno_user
			WHERE dateline > " . (TIME_NOW - ($this->settings['inferno_shoutbox_cutoff'] * 60)) . "
		");

		while ($row = $this->db->fetch_array($result))
		{
			$shout_user = get_user($row['uid']);
			$row['username'] = format_name($shout_user['username'], $shout_user['usergroup'], $shout_user['displaygroup']); 
			// thanks ghetto ass build_profile_link function, I'll just do it on my own
			$row['username'] = $this->gen_profile_link($row['username'], $row['uid']);
			$users[] = $row['username'];
		}

		return $users;
	}

	public function gen_profile_link($text, $uid)
	{
		return '<a href="' . $this->settings['bburl'] . '/' . get_profile_link($uid) . '">' . $text . '</a>';
	}


	public function render_notice()
	{
		$string = $this->settings['inferno_shoutbox_notice'];
		require_once MYBB_ROOT . 'inc/class_parser.php';
		$parser = new postParser();

		$parse_options = array(
			'allow_html' 	=> false,
			'allow_mycode' 	=> (bool) $this->settings['inferno_allow_mycode'],
			'allow_smilies'	=> (bool) $this->settings['inferno_smilies']
		);

		return $this->lang->sprintf($this->lang->isb_c_notice_msg, $parser->parse_message($string, $parse_options));
	}

	public function update_notice($string = '')
	{
		$string = $this->db->escape_string($string);
		$logger = (empty($string)) ? 'has removed the notice' : 'has changed the notice to: ' . $string;
		$this->log($logger);

		$var = $this->db->update_query('settings', array(
			'value' => $string
		), 'name = \'inferno_shoutbox_notice\'');

		rebuild_settings();

		$this->update_anus();

		return $var;
	}

	/**
	 * Replace {inferno_*} variables in Inferno Templates
	 *
	 * @param string $string Template
	 * @return object
	 */
	public function replace_template_vars($string)
	{
		$replace_vars = array(
			array(
				'inferno_shoutbox_title',
				$this->settings['inferno_shoutbox_title']
			),
			array(
				'inferno_css_height',
				$this->settings['inferno_css_height']
			),
			array(
				'inferno_button_bold',
				'<input type="button" class="button" onclick="javascript: inferno.update_style(\'bold\', this); return false;" style="font-weight: bold;" value="B' . (($this->userinfo['bold']) ? '*' : '') . '"/>'
			),
			array(
				'inferno_button_underline',
				'<input type="button" class="button inferno_underline" onclick="javascript: inferno.update_style(\'underline\', this); return false;" style="text-decoration:underline;" value="U' . (($this->userinfo['underline']) ? '*' : '') .'"/>'
			),
			array(
				'inferno_button_italic',
				'<input type="button" class="button inferno_italic" onclick="javascript: inferno.update_style(\'italic\', this); return false;" style="font-style: italic;" value="I' . (($this->userinfo['italic']) ? '*' : '') . '"/>'
			),
			array(
				'inferno_button_colors',
				$this->render_select_box('color')
			),
			array(
				'inferno_button_fonts',
				$this->render_select_box('font')
			),
			array(
				'inferno_button_smilies',
				'<input type="button" class="button" name="btnSmilies" onclick="javascript:inferno.toggle_smilies(); return false;" value="' . $this->lang->isb_btn_smilies . '"/>'
			),
			array(
				'inferno_active_users',
				count($this->fetch_active_users())
			),
			array(
				'inferno_user_css',
				$this->render_css($this->userinfo)
			),
			array(
				'inferno_version',
				$this->version
			),
			array(
				'inferno_archive_shouts',
				$this->gen_archive()
			),
			array(
				'inferno_archive_nav',
				$this->gen_archive_nav()
			)
		);

		foreach ($replace_vars as $v)
		{
			if (isset($this->settings[$v[0]]) && !$this->settings[$v[0]])
			{
				$v[1] = '';
			}
			
			$string = str_replace('{' . $v[0] . '}', $v[1], $string);
		}

		return $string;
	}

	public function gen_archive_nav()
	{
		global $total_pages, $page;
		if (is_int($page)) {

			$html = '<tr><td class="tcat" align="center">';

			$jump = 3;
			$jumpback = ($page - $jump > 0) ? $page - $jump : false;
			$back = ($page - 1 > 0) ? $page - 1 : false;
			$forward = ($page + 1 <= $total_pages) ? $page + 1 : false;
			$jumpforward = ($page + $jump <= $total_pages) ? $page + $jump : false;

			$html .= '<b>' . $this->lang->isb_archive_page . ' <input type="text" id="a_page" style="width:30px; text-align:center;" value="' . $page . '" />/' . $total_pages . '</b>
				<input onclick="load_from_text();" type="button" class="button" value="' . $this->lang->isb_archive_btn_go . '" />
				<br />
			';

			if ($jumpback)
			{
				$html .= '<input onclick="load(' . $jumpback . ');" type="button" class="button" value="<<" />';
			}
			if ($back)
			{
				$html .= '<input onclick="load(' . $back . ');" type="button" class="button" value="<" />';
			}
			if ($forward)
			{
				$html .= '<input onclick="load(' . $forward . ');" type="button" class="button" value=">" />';
			}
			if ($jumpforward)
			{
				$html .= '<input onclick="load(' . $jumpforward . ');" type="button" class="button" value=">>" />';
			}

			$html .= '</td></tr>';

			return $html;
		}
	}

	public function gen_archive()
	{
		global $total_pages, $page, $offset, $shouts_per_page;
		if (is_int($page)) {

			$shouts = $this->get_shouts(false, $offset, $shouts_per_page);
			$html = '';

			$i = 0;
			foreach ($shouts as $s)
			{
				if (!$s['me'])
				{
					$shoutdata = '[' . $s['timestamp'] . '] ' . (($s['private']) ? '<b>[PM]</b> ' : '') . $s['username'] . ' : ' . $s['shout'];
				}
				else
				{
					$shoutdata = '*' . $s['username'] . ' ' . $s['shout'] . '*';
				}

				$shoutdata = $s['avatar'] . ' ' . $shoutdata;

				$css = ($i % 2) ? 'trow1' : 'trow2';
				$html .= '<tr><td class="' . $css . '">';
				$html .= $shoutdata;
				$html .= '</td></tr>';
				$i++;
			}
		}

		return $html;
	}

	public function count_total_shouts()
	{
		$where = "
		WHERE
		(
			(
				(s.private = '0')
				OR
				(s.private = '{$this->userinfo['uid']}')
				OR
				(s.private != '0' AND s.uid = '{$this->userinfo['uid']}')
			)
			AND
			(
				(u.silenced = '0')
		 		OR
				(u.silenced = '1' AND u.uid = '{$this->userinfo['uid']}')
			)
		)";
		$query = $this->db->query("
			SELECT COUNT(*) as total
			FROM " . TABLE_PREFIX . "inferno_shout s
			LEFT JOIN " . TABLE_PREFIX . "inferno_user u
			ON s.uid = u.uid
			{$where}
			ORDER BY s.sid;
		");
		$result = $this->db->fetch_array($query);

		return ($result['total']) ? (int) $result['total'] : 0;
	}

	/**
	 * Render Select drop downs
	 *
	 * @param string $string Select Drop Down
	 * @return string
	 */
	public function render_select_box($string)
	{
		$array = $this->get_setting_array('shoutbox_' . $string);
		// $mouseover = 'onmouseover="javascript: inferno.update_entry_style(\'' . $string . '\', this.value); return false;"';
		$html = '<select id="inferno_' . $string . '" onchange="javascript: inferno.update_style(\'' . $string . '\', this); return false;">';
		$html .= '<option value="Default">Default</option>';
		$css = ($string == 'color') ? 'color' : 'font-family';

		foreach ($array as $val)
		{
			$sel = (strtolower($this->userinfo[$string]) == strtolower($val)) ? 'selected="selected"' : '';
			$html .= '<option style="' . $css . ':' . $val . ';" ' . $sel . '>' . $val . '</option>';
		}

		$html .= '</select>';

		return $html;
	}

	public function get_permissions($perm)
	{
		$group = $this->get_setting_array('usergroups_' . $perm, ',');
		return (in_array($this->mybb->user['usergroup'], $group)) ? true : false;
	}

	/**
	 * Returns an aarray of a single setting
	 *
	 * @param string $setting Setting Name
	 * @return array
	 */
	public function get_setting_array($setting, $delimeter = "\r\n")
	{
		return explode($delimeter, $this->settings['inferno_' . $setting]);
	}

	public function update_styles($array)
	{
		$uid = (int) $this->mybb->user['uid'];
		return $this->db->update_query('inferno_user', $array, "uid = '{$uid}'");
	}

	public function fetch_mybb_userinfo($user)
	{
		if (is_numeric($user))
		{
			return get_user($user);
		}
		else
		{
			$data = $this->db->escape_string($user);
			$query = $this->db->simple_select("users","*","username='{$data}'");
			return $this->db->fetch_array($query);
		}
	}

	public function toggle_ban($uid, $yesno)
	{
		$yesno = ($yesno) ? 1 : 0;

		$logger = 'Has ' . ((!$yesno) ? 'un' : '') . 'banned a user (UID: ' . $uid . ')';
		$this->log($logger);

		return $this->update_userinfo(array(
			'banned' => $yesno
		), $uid);
	}

	public function toggle_silence($uid, $yesno)
	{
		$yesno = ($yesno) ? 1 : 0;

		$logger = 'Has ' . ((!$yesno) ? 'un' : '') . 'silenced a user (UID: ' . $uid . ')';
		$this->log($logger);

		return $this->update_userinfo(array(
			'silenced' => $yesno
		), $uid);
	}

	public function update_userinfo($array, $uid = 0)
	{
		$uid = ($uid == 0) ? (int) $this->mybb->user['uid'] : (int) $uid;
		return $this->db->update_query('inferno_user', $array, "uid = '{$uid}'");
	}

	/**
	 * Gets user's Inferno Info
	 *
	 * @return void
	 */
	public function fetch_userinfo($uid = 0)
	{
		$uid = ($uid == 0) ? (int) $this->mybb->user['uid'] : (int) $uid;
		$query = $this->db->query("SELECT * FROM " . TABLE_PREFIX . "inferno_user WHERE uid='{$uid}'");
		$result = $this->db->fetch_array($query);

		if ($uid && !$result)
		{
			if ($this->fetch_mybb_userinfo($uid))
			{
				$this->create_user($uid);
				return $this->fetch_userinfo($uid);
			}
		}

		return ($result) ? $result : false;
	}

	public function create_user($uid)
	{
		return $this->db->insert_query('inferno_user', array(
			'uid' => $uid
		));
	}

	public function delete_user($uid)
	{
		$q1 = $this->db->delete_query("inferno_user", "uid='{$uid}'");
		$q2 = $this->db->delete_query("inferno_shout", "uid='{$uid}'");
		return ($q1 && $q2);
	}

	public function gen_banlist()
	{
		$query = $this->db->query("SELECT * FROM " . TABLE_PREFIX . "inferno_user WHERE banned=1");
		$users = array();

		while ($row = $this->db->fetch_array($query))
		{
			$u = $this->fetch_mybb_userinfo($row['uid']);
			$users[] = $u['username'];
		}

		if (empty($users))
		{
			return $this->lang->sprintf($this->lang->isb_list_empty, strtolower($this->lang->isb_banned));
		}
		return $this->lang->sprintf($this->lang->isb_list, strtolower($this->lang->isb_banned), implode(', ', $users));
	}

	public function gen_silencelist()
	{
		$query = $this->db->query("SELECT * FROM " . TABLE_PREFIX . "inferno_user WHERE silenced=1");
		$users = array();

		while ($row = $this->db->fetch_array($query))
		{
			$u = $this->fetch_mybb_userinfo($row['uid']);
			$users[] = $u['username'];
		}

		if (empty($users))
		{
			return $this->lang->sprintf($this->lang->isb_list_empty, strtolower($this->lang->isb_silenced));
		}
		return $this->lang->sprintf($this->lang->isb_list, strtolower($this->lang->isb_silenced), implode(', ', $users));
	}

	public function log($string)
	{
		$log_entry = array(
			'uid' => $this->mybb->user['uid'],
			'ipaddress' => $this->db->escape_string(get_ip()),
			'dateline' => TIME_NOW,
			'fid' => '0',
			'tid' => '0',
			'pid' => '0',
			'action' => $this->db->escape_string($string),
			'data' => 'a:0:{}' // the fuk is this chit? $this->db->escape_string(@serialize($data))
		);

		$this->db->insert_query('moderatorlog', $log_entry);
	}

	/**
	 * Install the Inferno Shoutbox
	 *
	 * @return void
	 */
	public function install()
	{
		    if(!file_exists(PLUGINLIBRARY))
		    {
		        flash_message("The selected plugin could not be installed because <a href=\"http://mods.mybb.com/view/pluginlibrary\">PluginLibrary</a> is missing.", "error");
		        admin_redirect("index.php?module=config-plugins");
		    }

		global $PL;
		$PL or require_once PLUGINLIBRARY;
		// Create Setting Group
		$PL->settings("inferno", // group name and settings prefix 
                  "Inferno Shoutbox Options",
                  "This section allows you to manage the various settings of your Inferno Shoutbox.",
                	array(
			"enabled" => array(
				"title" 		=> "Shoutbox Online",
				"description" 	=> "Is the shoutbox system online?",
				"optionscode" 	=> "onoff",
				"value" 		=> "1"
			),
			"shoutbox_title" => array(
				"title" 		=> "Shoutbox Title",
				"description" 	=> "The shoutbox title displayed on the category row of the shoutbox on your forums.",
				"optionscode" 	=> "text",
				"value" 		=> trim($this->db->escape_string($this->settings["bbname"]) . " Shoutbox")
			),
			"shoutbox_anus" => array(
				"title" 		=> "Shoutbox ANUS (Advanced Network Updating System)",
				"description" 	=> "ANUS (Advanced Network Updating System) is a feature that greatly reduces the resources consumed by the shoutbox, which is ideal for small servers, or sites that wish to optimize the shoutbox. 
				<br />Turning this feature on will ensure the shoutbox only requests data when there is new data to be displayed
				<br />Note: Turning this option on may create a half or one second delay in displaying new shouts.",
				"optionscode" 	=> "onoff",
				"value" 		=> "0"
			),
			"shoutbox_pm" => array(
				"title" 		=> "Enable Private Messaging System",
				"description" 	=> "Set to \"Off\" to disable the shoutbox private messaging system.",
				"optionscode" 	=> "onoff",
				"value" 		=> "1"
			),
			"minimum_posts" => array(
				"title" 		=> "Minimum Posts to View Shoutbox",
				"description" 	=> "Enter the number of posts a user must have before they can participate in the shoutbox.
				<br />Leave blank to disable",
				"optionscode" 	=> "text",
				"value" 		=> ""
			),
			"alert_admincommands" => array(
				"title" 		=> "Disable Admin Command Notices",
				"description" 	=> "Switching this setting to Yes will mean that when an admin executes a command in the shoutbox (such as pruning, or banning a user) the notice that usually automatically shows will not be shown.",
				"optionscode" 	=> "yesno",
				"value" 		=> "0"
			),
			"shouts_display" => array(
				"title" 		=> "Shouts To Display",
				"description" 	=> "Select the number of shouts you wish to display within the shoutbox.
				<br />Note: the higher this number, the more intensive the shoutbox may be on your server.",
				"optionscode" 	=> "text",
				"value" 		=> "20"
			),
			"shout_order" => array(
				"title" 		=> "Shout Display Order",
				"description" 	=> "Select \"on\" to display shouts in descending order. Select \"off\" to display shouts in ascending order.",
				"optionscode" 	=> "onoff",
				"value" 		=> "1"
			),
			"avatars" => array(
				"title" 		=> "Display User Avatars",
				"description" 	=> "Enter a number in pixels to enable. This number will set the width and height of the avatar in each shout.
				<br>For example, setting this field to \"50\" will display each avatar and make the width=\"50\" and height=\"50\".
				<br>To disable this feature, keep this field empty",
				"optionscode" 	=> "text",
				"value" 		=> ""
			),
			"shoutbox_flood" => array(
				"title" 		=> "Flood Control",
				"description" 	=> "Set how many seconds a user must wait before posting another shout after a previous.
				<br />For example, if this was set to 3, and a user made a shout, they would only be able to shout again 3 seconds later.",
				"optionscode" 	=> "text",
				"value" 		=> "3"
			),
			"shoutbox_color" => array(
				"title" 		=> "Editor Colors",
				"description" 	=> "You may customize the colors automatically shown in the shoutbox editor for people to pick for the messages they shout.
				<br />Put one color on each line and make sure you only use hexcodes or color names.",
				"optionscode" 	=> "textarea",
				"value" 		=> "Red\r\nBlue\r\nGreen\r\nOrange\r\nBrown\r\nBlack\r\nYellow\r\nPurple\r\nPink\r\nSilver"
			),
			"shoutbox_font" => array(
				"title" 		=> "Editor Fonts",
				"description" 	=> "Similar to \"Editor Colors\", only this applies for font styles.",
				"optionscode" 	=> "textarea",
				"value" 		=> "Arial\r\nArial Black\r\nArial Narrow\r\nBook Antiqua\r\nCentury Gothic\r\nComic Sans MS\r\nCourier New\r\nFixedsys\r\nFranklin Gothic Medium\r\nGaramond\r\nGeorgia\r\nImpact\r\nLucida Console\r\nMicrosoft Sans Serif\r\nPalatino Linotype\r\nSystem\r\nTahoma\r\nTimes New Roman\r\nTrebuchet MS\r\nVerdana"
			),
			"css_height" => array(
				"title" 		=> "Default Shoutbox Window Height",
				"description" 	=> "Specify a number in pixels for the default height of the window where shouts will be displayed. You do not need to enter \"px\".",
				"optionscode" 	=> "text",
				"value" 		=> "210"
			),
			"button_bold" => array(
				"title" 		=> "Show Bold Button",
				"description" 	=> "Specify if the bold button will be displayed within the shoutbox editor.",
				"optionscode" 	=> "yesno",
				"value" 		=> "1"
			),
			"button_underline" => array(
				"title" 		=> "Show Underline Button",
				"description" 	=> "Specify if the underline button will be displayed within the shoutbox editor.",
				"optionscode" 	=> "yesno",
				"value" 		=> "1"
			),
			"button_italic" => array(
				"title" 		=> "Show Italic Button",
				"description" 	=> "Specify if the italic button will be displayed within the shoutbox editor.",
				"optionscode" 	=> "yesno",
				"value" 		=> "1"
			),
			"button_colors" => array(
				"title" 		=> "Show Colors Drop-Down",
				"description" 	=> "Specify if the colors drop-down will be displayed within the shoutbox editor.",
				"optionscode" 	=> "yesno",
				"value" 		=> "1"
			),
			"button_fonts" => array(
				"title" 		=> "Show Fonts Drop-Down",
				"description" 	=> "Specify if the fonts drop-down will be displayed within the shoutbox editor.",
				"optionscode" 	=> "yesno",
				"value" 		=> "1"
			),
			"button_smilies" => array(
				"title" 		=> "Show Smilies Button",
				"description" 	=> "Specify if the smilies button will be displayed within the shoutbox editor.",
				"optionscode" 	=> "yesno",
				"value" 		=> "1"
			),
			"shoutbox_notice" => array(
				"title" 		=> "Shoutbox Notice",
				"description" 	=> "This will be displayed above all shouts and remain static. Leave blank for no notice. You can either enter the notice here or use the following commands within the shoutbox:
				<pre>/notice [your message here]\n/removenotice</pre>",
				"optionscode" 	=> "text",
				"value" 		=> ""
			),
			"usergroups_admin" => array(
				"title" 		=> "Administrator Usergroups",
				"description" 	=> "Note any usergroups here that will have administrator commands.
				<br />Seperate each group with a comma",
				"optionscode" 	=> "text",
				"value" 		=> "4"
			),
			"usergroups_mod" => array(
				"title" 		=> "Moderator Usergroups",
				"description" 	=> "Note any usergroups here that will have moderator commands.
				<br />Seperate each group with a comma",
				"optionscode" 	=> "text",
				"value" 		=> "3,6"
			),
			"usergroups_protected" => array(
				"title" 		=> "Protected Usergroups",
				"description" 	=> "Note any usergroups here to be protected, they will not be allowed to be banned, silenced, etc.
				<br />Seperate each group with a comma",
				"optionscode" 	=> "text",
				"value" 		=> "4"
			),
			"usergroups_banned" => array(
				"title" 		=> "Banned Usergroups",
				"description" 	=> "Select the usergroups that are banned from the shoutbox. Users in these groups will not be able to see or access the shoutbox.
				<br />Seperate each group with a comma",
				"optionscode" 	=> "text",
				"value" 		=> "1,5,7"
			),
			"js_refresh" => array(
				"title" 		=> "Shoutbox Refresh Rate",
				"description" 	=> "Enter the amount in seconds before the shoutbox updates.
				<br />When a user is not idle within the shoutbox, AJAX will dynamically refresh the shoutbox to fetch new content, the faster this is, the more real time the shoutbox is. However, this speed comes at the cost of your system resources, and depending on your server/forum activity, it may be a very bad idea to set this to a low number.
				<br />To get the best results, I recommend testing new settings here, pushing time in small amounts (recommended: 1 second) and see what impact that makes on your server load.",
				"optionscode" 	=> "text",
				"value" 		=> "5"
			),
			"idle_timeout" => array(
				"title" 		=> "Shoutbox Idle Timeout",
				"description" 	=> "Enter the amount (in minutes) before a user will becomde idle client side due to inactivity. Default is 10 minutes
				<br />Note: When a user is idle, it will no longer refresh the shoutbox until they choose to un-idle.",
				"optionscode" 	=> "text",
				"value" 		=> "10"
			),
			"shout_max_chars" => array(
				"title" 		=> "Maximum Shout Length",
				"description" 	=> "The maximum amount of characters allowed in a single shout.",
				"optionscode" 	=> "text",
				"value" 		=> "300"
			),
			"thread_post" => array(
				"title" 		=> "New Thread Shout",
				"description" 	=> "Select \"Yes\" to automatically have a shout posted when a user posts a new thread.",
				"optionscode" 	=> "yesno",
				"value" 		=> "1"
			),
			"post_post" => array(
				"title" 		=> "New Post Shout",
				"description" 	=> "Select \"Yes\" to automatically have a shout posted when a user posts in a thread.",
				"optionscode" 	=> "yesno",
				"value" 		=> "1"
			),
			"thread_forums" => array(
				"title" 		=> "New Thread Shout Exempt Forums",
				"description" 	=> "Enter the ID for each forum that will NOT have a shout automatically posted when a user posts a new thread. Enter secret forums such as staff sections, 18+, etc.
				<br />Seperate each forum ID with a comma",
				"optionscode" 	=> "text",
				"value" 		=> ""
			),
			"newpost" => array(
				"title" 		=> "Post Count Shout",
				"description" 	=> "Have the shoutbox post a shout every time a user hits X number of posts.
				<br />For example: 100 would post a shout every 100, 200, 300... posts a user makes.
				<br />Leave blank to disable this feature",
				"optionscode" 	=> "text",
				"value" 		=> "100"
			),
			"shoutbox_cutoff" => array(
				"title" 		=> "Shoutbox Cutoff Time",
				"description" 	=> "You can customize how long a user is displayed as \"active\" in the \"Active Users Tab\" here. Default is 10 minutes.",
				"optionscode" 	=> "text",
				"value" 		=> "10"
			),
			"smilies" => array(
				"title" 		=> "Allow Smilies",
				"description" 	=> "Select \"On\" if you would like to allow users to use smilies within shout messages.",
				"optionscode" 	=> "yesno",
				"value" 		=> "1"
			),
			"smilies_limit" => array(
				"title" 		=> "Smiley Display Limit",
				"description" 	=> "Enter the maximum number of smilies to display when a person clicks the \"Smilies\" button
				<br />Enter 0 to disable",
				"optionscode" 	=> "text",
				"value" 		=> "0"
			),
			"shout_markup" => array(
				"title" 		=> "Markup Shouts",
				"description" 	=> "Select \"On\" to display user set bold, italic, underline, colors, and fonts in user shouts.",
				"optionscode" 	=> "onoff",
				"value" 		=> "1"
			),
			"allow_mycode" => array(
				"title" 		=> "Enable MyCode",
				"description" 	=> "Select \"On\" to parse all MyCode in user shouts.",
				"optionscode" 	=> "onoff",
				"value" 		=> "1"
			),
			"banned_mycode" => array(
				"title" 		=> "Banned MyCode",
				"description" 	=> "Enter the mycode tags that users will NOT be allowed to use. Administrators can still use banned MyCode
				<br > Seperate each mycode with a comma",
				"optionscode" 	=> "text",
				"value" 		=> "php,code,quote,img,list,size"
			),
			"filter_badwords" => array(
				"title" 		=> "Filter Bad Words",
				"description" 	=> "Select \"On\" to automatically censor bad words (based on your forum&#39;s filter) in shouts.",
				"optionscode" 	=> "onoff",
				"value" 		=> "1"
			),
			"archive" => array(
				"title" 		=> "Archive Online",
				"description" 	=> "Is the shoutbox archive system online?",
				"optionscode" 	=> "onoff",
				"value" 		=> "1"
			),
			"archive_shouts_per_page" => array(
				"title" 		=> "Archive: Shouts Per Page",
				"description" 	=> "Enter the number of shouts you want to be displayed per page of the archive.",
				"optionscode" 	=> "text",
				"value" 		=> "50"
			),
		)
	);

		// Insert Shoutbox Tables

		$this->db->query("
			CREATE TABLE IF NOT EXISTS `" . TABLE_PREFIX ."inferno_shout` (
			  `sid` int(11) NOT NULL AUTO_INCREMENT,
			  `uid` int(11) NOT NULL,
			  `shout` longtext NOT NULL,
			  `me` tinyint(1) NOT NULL DEFAULT '0',
			  `private` int(11) NOT NULL DEFAULT '0',
			  `timestamp` int(10) NOT NULL DEFAULT '0',
			  PRIMARY KEY (`sid`)
			) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=2 ;
		");

		$this->db->query("
			CREATE TABLE IF NOT EXISTS `" . TABLE_PREFIX ."inferno_user` (
			  `pid` int(11) NOT NULL AUTO_INCREMENT,
			  `uid` int(11) NOT NULL,
			  `bold` tinyint(1) NOT NULL DEFAULT '0',
			  `italic` tinyint(1) NOT NULL DEFAULT '0',
			  `underline` tinyint(1) NOT NULL DEFAULT '0',
			  `color` varchar(100) NOT NULL DEFAULT '',
			  `font` varchar(100) NOT NULL DEFAULT '',
			  `banned` tinyint(1) NOT NULL DEFAULT '0',
			  `silenced` tinyint(1) NOT NULL DEFAULT '0',
			  `dateline` int(10) NOT NULL DEFAULT '0',
			  PRIMARY KEY (`pid`)
			) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=8 ;
		");


		//Using pluginlibrary
		//Should I just copy/paste contents to reduce risk?
		$PL->templates("inferno", 
                   "Inferno Shoutbox",
			array(
				"shoutbox" => file_get_contents(MYBB_ROOT . "inc/plugins/inferno/templates/shoutbox.html"),

				"archive" => file_get_contents(MYBB_ROOT . "inc/plugins/inferno/templates/archive.html"),

				"archive_table" => file_get_contents(MYBB_ROOT . "inc/plugins/inferno/templates/archive_table.html"),
			)
		);

		$install_shouts = array(
			'Congratulations! Your copy of Inferno Shoutbox ' . $this->version . ' has been installed successfully!',
			'Shoutbox settings can be found at Admin CP -> Configuration -> Inferno Shoutbox Options',
			'Command names can be modified to your liking in the Inferno language file',
			'View the Archive by clicking the Shoutbox Title',
			'Double click a shout to edit or delete it',
			'Erase these shouts by typing /prune'
		);

		foreach ($install_shouts as $s)
		{
			$this->create_shout($this->mybb->user['uid'], $s);
		}
	}

	public function is_installed()
	{
		return ($this->db->table_exists('inferno_shout')) ? true : false;
	}

	public function activate()
	{
		global $PL;   		
		$PL or require_once PLUGINLIBRARY;
		//Install CSS to all themes with pluginlibrary
		$PL->stylesheet('inferno', file_get_contents(MYBB_ROOT . "/inc/plugins/inferno/inferno_style.css"));
		require_once MYBB_ROOT . '/inc/adminfunctions_templates.php';
		find_replace_templatesets('index', '#' . preg_quote('{$forums}') . '#', '{$inferno_shoutbox}{$forums}');
	}

	public function deactivate()
	{
		global $PL;   		
		$PL or require_once PLUGINLIBRARY;
		//Clean cache		
		$PL->cache_delete("inferno");
		//Deactivate Stylesheets
		$PL->stylesheet_deactivate('inferno', true);
		require_once MYBB_ROOT . '/inc/adminfunctions_templates.php';
		find_replace_templatesets('index', '#' . preg_quote('{$inferno_shoutbox}') . '#', '');
	}

//Sorry, but it breaks too many themes. I'll probably put this in a comment in the javascript and HTML source
/*	public function please_support_the_developer(&$footer)
	{
		$string = 'Inferno Shoutbox v' . $this->version . ' by <a href="http://community.mybb.com/thread-149231-post-1053656.html#pid1053656">Mattbox Solutions</a>.<br />';
		$footer = str_replace('<div id="copyright">', '<div title="Made in loving memory of J.A." id="copyright">' . $string, $footer);
	}
*/
	public function uninstall()
	{
		// Delete all tables & Info!    		
		global $PL;   		
		$PL or require_once PLUGINLIBRARY;
		//Delete settings
  		$PL->settings_delete("inferno");
		//Delete templates
		$PL->templates_delete("inferno");
		//Delete CSS
		$PL->stylesheet_delete('inferno', true);		
		//Drop tables		
		$this->db->query("DROP TABLE IF EXISTS " . TABLE_PREFIX . "inferno_shout");
		$this->db->query("DROP TABLE IF EXISTS " . TABLE_PREFIX . "inferno_user");

		rebuild_settings();
	}
}
