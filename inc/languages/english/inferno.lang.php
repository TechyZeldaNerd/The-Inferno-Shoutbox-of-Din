<?php

/**
 * Inferno Shoutbox English Language Pack
 *  
 */

############ MAIN ############

// Main Shoutbox
$l['isb_shoutboxtab'] 		= 'Shoutbox';
$l['isb_activetab']   		= 'Active Users';
$l['isb_activeusers'] 		= 'Currently Active Shoutbox Users: {1}';

$l['isb_btn_shout'] 		= 'Shout';
$l['isb_btn_clear'] 		= 'Clear';
$l['isb_btn_smilies']		= 'Smilies';

// Archive
$l['isb_archive']			= 'Archive';
$l['isb_archive_dt']		= 'Archive Disabled';
$l['isb_archive_noview']	= 'You have no permission to view the archive.';
$l['isb_archive_disabled']	= 'The archive has been disabled by administrators.';
$l['isb_archive_page']		= 'Page';
$l['isb_archive_btn_go']	= 'Go';

// Auto-Messages
$l['isb_newthread']			= 'has posted a new thread: {1}';
$l['isb_newpost']			= 'has reached {1} posts';
$l['isb_newpost_shout']			= 'has reached posted in {1}';
//Who's online language
$l['isb_wol']				= 'Viewing Inferno Shoutbox';

############ SHOUTBOX COMMANDS ############ 

/*
	Each command is grouped with the message it displays (if applicable) when the command is executed
	
	Each command has the language prefix: isb_c_ 
	(stands for infernoshoutbox_command_)

	Example: 

	Changing

	$l['isb_c_me'] = 'me';

	TO

	$l['isb_c_me'] = 'mycommand';

	Will change the "/me" command syntax to "/mycommand".

*/

// Me
$l['isb_c_me']				= 'me';

// PM
$l['isb_c_pm']				= 'pm';

// Notice
$l['isb_c_notice']			= 'notice';
$l['isb_c_notice_msg']		= 'Notice : {1}';
$l['isb_c_removenotice']	= 'removenotice';

// General terms
$l['isb_banned']			= 'Banned';
$l['isb_banned_sb']			= 'You are banned from the {1}.';
$l['isb_silenced']			= 'Silenced';

// Show lists
$l['isb_c_banlist']			= 'banlist';
$l['isb_c_silencelist']		= 'silencelist';
$l['isb_list']				= 'Currently {1} Users: {2}.';
$l['isb_list_empty']		= 'No users are currently {1}.';

// Ban
$l['isb_c_ban']				= 'ban';
$l['isb_c_unban']			= 'unban';
$l['isb_ban_msg']			= 'has banned {1} from the shoutbox!';
$l['isb_unban_msg']			= 'has unbanned {1} from the shoutbox!';
	
// Silence	
$l['isb_c_silence']			= 'silence';
$l['isb_c_unsilence']		= 'unsilence';
$l['isb_silence_msg']		= 'has silenced {1} from the shoutbox!';
$l['isb_unsilence_msg']		= 'has unsilenced {1} from the shoutbox!';
	
// Prune	
$l['isb_c_prune']			= 'prune';
$l['isb_prune_msg']			= 'has pruned the shoutbox!';
$l['isb_prune_user_msg']	= 'has pruned all shouts by {1}!';

// Say	
$l['isb_c_say']				= 'say';

// Protected User
$l['isb_protected']			= 'User "{1}" is protected.';
// User doesn't exist
$l['isb_not_exists']		= 'User "{1}" does not exist.';

?>
