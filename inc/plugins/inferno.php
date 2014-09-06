<?php

// Project Created: 12/16/2013

if (!defined('IN_MYBB'))
{
	exit;
}

if(!defined("PLUGINLIBRARY"))
{
    define("PLUGINLIBRARY", MYBB_ROOT."inc/plugins/pluginlibrary.php");
}

$plugins->add_hook('global_intermediate', 'inferno_global');
$plugins->add_hook('admin_user_users_delete_commit', 'inferno_delete_user');
$plugins->add_hook('inferno_archive_start', 'inferno_archive');
$plugins->add_hook('newthread_do_newthread_end', 'inferno_newthread');
$plugins->add_hook('newreply_do_newreply_end', 'inferno_newpost');

function inferno_info()
{
	$inferno = inferno_init();
	return array(
		'name'			=> 'Inferno Shoutbox',
		'description'	=> 'Inferno Shoutbox is a powerful shoutbox for your MyBB forum. (Inspired by Inferno vBShout for vBulletin)',
		'website'		=> 'http://community.mybb.com/thread-149231.html',
		'author'		=> 'Mattbox Solutions',
		'authorsite'	=> 'http://community.mybb.com/user-79350.html',
		'version'		=> $inferno->version,
		'guid' 			=> '606e3d645badf30fd787b8f668bcd5b8',
		'compatibility' => '18*'
	);
}

function inferno_init()
{
	require_once MYBB_ROOT . 'inc/plugins/inferno/class_core.php';
	return inferno_shoutbox::get_instance();
}

function inferno_is_installed()
{
	$inferno = inferno_init();
	return $inferno->is_installed();
}

function inferno_install()
{
	$inferno = inferno_init();
	$inferno->install();
}

function inferno_activate()
{
	$inferno = inferno_init();
	$inferno->activate();
}

function inferno_deactivate()
{
	$inferno = inferno_init();
	$inferno->deactivate();
}

function inferno_uninstall()
{
	$inferno = inferno_init();
	$inferno->uninstall();
}

function inferno_global()
{
	global $mybb, $templates, $settings, $inferno_shoutbox, $lang;
	$lang->load('inferno');

	if (IN_MYBB != 'infernoshout')
	{
		if ($settings['inferno_enabled'])
		{
			$inferno = inferno_init();

			if (!$inferno->banned_usergroup)
			{
				eval("\$inferno_shoutbox = \"".$templates->get("inferno_shoutbox")."\";");
				$inferno_shoutbox = $inferno->replace_template_vars($inferno_shoutbox);
			}
		}
	}
}

function inferno_archive()
{
	global $mybb, $templates, $settings, $inferno_archive_table, $lang;
	$lang->load('inferno');

	if ($settings['inferno_enabled'] && $settings['inferno_archive'])
	{
		$inferno = inferno_init();
		eval("\$inferno_archive_table = \"".$templates->get("inferno_archive_table")."\";");
		$inferno_archive_table = $inferno->replace_template_vars($inferno_archive_table);
	}
}

function inferno_newthread()
{
	global $mybb, $db, $settings, $url, $lang;

	if ($settings['inferno_enabled'])
	{
		$inferno = inferno_init();
		$data = $mybb->input;
		$fid = $data['fid'];

		if ($settings['inferno_thread_post'] && !in_array($fid, explode(',', $settings['inferno_thread_forums'])))
		{
			$link = '[url=' . $settings['bburl'] . '/' . $url . ']' . $db->escape_string($data['subject']) . '[/url]';
			$shout = $lang->sprintf($lang->isb_newthread, $link);
			$inferno->create_shout($mybb->user['uid'], $shout, true);
		}
	}
}

function inferno_newpost()
{
	global $mybb, $db, $settings, $post, $url, $lang, $thread;
	$counter = (int) $settings['inferno_newpost'];
	$posts = (int) $mybb->user['postnum'] + 1;


	if ($settings['inferno_enabled'] && $counter)
	{
		$inferno = inferno_init();
	
		if ($posts % $counter == 0)
		{
			$inferno->create_shout($mybb->user['uid'], $lang->sprintf($lang->isb_newpost, $posts), true);
		}
	}
	

	if ($settings['inferno_enabled'])
	{
		$inferno = inferno_init();
		$data = $mybb->input;
		$fid = $thread['fid'];
		$inferno_url = htmlspecialchars_decode($url);

		if ($settings['inferno_post_post'] && !in_array($fid, explode(',', $settings['inferno_thread_forums'])))
		{
			$link = '[url=' . $settings['bburl'] . '/' . $inferno_url . ']' . $db->escape_string($thread['subject']) . '[/url]';
			$shout = $lang->sprintf($lang->isb_newpost_shout, $link);
			$inferno->create_shout($mybb->user['uid'], $shout, true);
		}
	}
}

function inferno_delete_user()
{
	global $user;
	$inferno = inferno_init();
	$inferno->delete_user($user['uid']);
}

function dvd($str)
{
	die(var_dump($str));
}

?>

