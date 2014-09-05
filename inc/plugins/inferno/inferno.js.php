<?php

header('Content-Type: text/javascript');
define('IN_MYBB', true);
require_once '../../../global.php';
require_once MYBB_ROOT . 'inc/plugins/inferno/class_core.php';
$inferno = inferno_shoutbox::get_instance();
$userinfo = $inferno->userinfo;

?>

/**
 *
 * Inferno Shoutbox Lite JavaScript File
 *
 * Inferno Shoutbox created by Mattbox Solutions
 * ectomatt @ mybb.com
 * u mirin?
 *
 */
inferno = function()
{
	this.url = 'infernoshout.php';
	this.refresh_rate = <?php echo intval($settings['inferno_js_refresh'] * 1000); ?>;
	this.shout_max_chars = <?php echo $settings['inferno_shout_max_chars']; ?>;
	this.shout_entry = null;
	this.interval = null;
	this.shoutbox_content = null;
	this.active_user_number = null;
	this.alert_div = false;
	this.smiley_div = false;
	this.styles = {
		'bold': <?php echo ($userinfo['bold']) ? 'true' : 'false'; ?>,
		'italic': <?php echo ($userinfo['italic']) ? 'true' : 'false'; ?>,
		'underline': <?php echo ($userinfo['underline']) ? 'true' : 'false'; ?>,
		'color': '<?php echo ($userinfo['color']); ?>',
		'font': '<?php echo ($userinfo['font']); ?>'
	}
	this.style_update = false;
	this.linkbar = false;
	this.private_chats = new Array();
	this.tab = null;
	this.editdiv = null;
	this.editdivtext = null;
	this.editshoutid = null;
	this.editshout = null;
	this.shoutwait = <?php echo $settings['inferno_shoutbox_flood']; ?>;
	this.lastshout = null;
	this.idlesetting = <?php echo $settings['inferno_idle_timeout'] * 60; ?>;
	this.idletimeout = time() + this.idlesetting;
	this.idlealert = false;
	this.idleinterval = null;
	this.asc = <?php echo $settings['inferno_shout_order'] ? 'false' : 'true'; ?>

	this.anus = <?php echo $settings['inferno_shoutbox_anus'] ? 'true' : 'false'; ?>;
	this.last_anus = time();
	this.anus_time = null;

	this.manage_anus = function()
	{
		// peek inside the anus and save its contents
		this.open_anus();

		if (this.anus_time > this.last_anus)
		{
			return true;
		}
		return false;
	}

	this.open_anus = function()
	{
		jQuery.ajax({
			type: 'GET',
			url: this.url + '?action=openanus' + inferno.screw_ie(),
			success: function(transport) {
			inferno.anus_time = transport;
			},
			error: function() { inferno.alert('Something went wrong opening the anus...'); }
		});
	}

	this.close_anus = function()
	{
		this.last_anus = time();
	}

	this.is_idle = function()
	{
		return (time() > this.idletimeout) ? true : false;
	}

	this.clear_idle = function()
	{
		this.clear_alert();
		this.update_idle_time();

		if (this.tab > 0) {
    		this.load_private_shouts(this.tab);
    	} else {
    		this.load_shouts();
    	}
	}

	this.update_idle_time = function()
	{
		this.idlealert = false;
		this.idletimeout = time() + this.idlesetting;
		return true;
	}

	// fix for IE's stupid ass caching ajax requests
	this.screw_ie = function()
	{
		return '&t=' + new Date().getTime();
	}

	this.init = function()
	{
		if (this.interval !== null)
		{
			clearInterval(this.interval);
		}

		this.idleinterval = setInterval(function() {
			if (inferno.is_idle())
			{
				if (!inferno.idlealert)
					inferno.alert('You are now idle. Click <a href="#" onclick="javascript: inferno.clear_idle(); return false;">here</a> to un-idle.', false);
			}
		}, 1000);

		this.shout_entry = document.getElementById('inferno_shout_entry');
		this.shoutbox_content = document.getElementById('inferno_content');
		this.active_user_number = document.getElementById('inferno_active_users');
		this.alert_div = document.getElementById('inferno_alert');
		this.smiley_div = document.getElementById('inferno_smilies');
		this.linkbar = document.getElementById('inferno_links');
		this.editdiv = document.getElementById('inferno_edit_shout');
		this.editdivtext = document.getElementById('inferno_update_shout');

		if (this.tab != -1)
		{
			this.tab = -1;
			this.shoutbox_content.innerHTML = 'Loading...';
			this.load_shouts();
		}

		if (this.tab == -1)
		{
			this.interval = setInterval(function() {
				if (inferno.tab == -1)
				{
					if (inferno.is_idle() == false)
					{
						if (inferno.anus)
						{
							if (inferno.manage_anus())
							{
								inferno.load_shouts();
								inferno.close_anus();
							}
						}
						else
						{
							inferno.load_shouts();
						}
					}
				}
			}, this.refresh_rate);
			inferno.update_idle_time();
		}
	}

	this.edit_shout = function(id)
	{
		this.editshoutid = id;
		this.get_shout(id);
	}

	this.get_shout = function(id)
	{
		jQuery.ajax({
			type: 'GET',
			url: this.url + '?action=getshout&id=' + id + inferno.screw_ie(),
			success: function(transport) {
				if (transport != '')
				{
					inferno.editshout = JSON.parse(transport);
					inferno.display_update();
					inferno.update_idle_time();
				}
			},
			error: function() { inferno.alert('Something went wrong...'); }
		});
	}

	this.display_update = function()
	{
		obj = this.editshout;
		editdiv = this.editdiv;
		editdivtext = this.editdivtext;
		editdiv.style.display = 'block';
		editdivtext.value = this.trim(obj.shout); //.trim();
	}

	this.update_shout = function()
	{
		sid = this.editshoutid;
		editdivtext = this.editdivtext;
		shout = this.trim(editdivtext.value); //.trim();
		condition = this.is_valid_shout(shout);

		if (condition == true)
		{
			var options = {
			    type: 'post',
			    url: this.url + '?action=updateshout',
			    data: {sid: sid, shout: shout},
			    success: function() {
			    	inferno.cancel();
			    	if (inferno.tab > 0) {
			    		inferno.load_private_shouts(inferno.tab);
			    	} else {
			    		inferno.load_shouts();
			    	}
			    	inferno.update_idle_time();
				}
			};
			jQuery.ajax(options);
		}
		else
		{
			this.alert(condition);
		}
	}

	this.delete_shout = function()
	{
		sid = this.editshoutid;
		var options = {
		    type: 'POST',
		    url: this.url + '?action=deleteshout',
		    data: {sid: sid},
		    success: function() {
		    	inferno.alert('Shout deleted successfully.');
		    	inferno.cancel();
		    	if (inferno.tab > 0) {
		    		inferno.load_private_shouts(inferno.tab);
		    	} else {
		    		inferno.load_shouts();
		    	}
		    }
		};
		jQuery.ajax(options);
	}

	this.cancel = function()
	{
		this.editshout = null;
		this.editdiv.style.display = 'none';
	}

	this.update_style = function(style, obj)
	{
		if (style == 'bold' || style == 'italic' || style == 'underline') {
			vals = {
				bold: 'B',
				italic: 'I',
				underline: 'U'
			}
			if (obj.value.indexOf('*') == -1) {
				obj.value += '*';
				this.styles[style] = true;
				this.update_entry_style(style, true);
			} else {
				obj.value = vals[style];
				this.styles[style] = false;
				this.update_entry_style(style, false);
			}
		} else {
			this.styles[style] = obj.value;
			this.update_entry_style(style, obj.value);
		}
		this.alert('Your style properties will be updated the next time that you shout.');
		this.style_update = true;
	}

	this.update_entry_style = function(style, value)
	{
		entry = this.shout_entry;

		switch (style)
		{
			case 'bold':
				entry.style.fontWeight = (value) ? 'bold' : '';
				break;
			case 'italic':
				entry.style.fontStyle = (value) ? 'italic' : '';
				break;
			case 'underline':
				entry.style.textDecoration = (value) ? 'underline' : '';
				break;
			case 'font':
				entry.style.fontFamily = (value == 'Default') ? '' : value;
				break;
			case 'color':
				entry.style.color = (value == 'Default') ? '' : value;
		}
	}

	this.add_private_chat = function(uid, name)
	{
		if (!in_array(uid, this.private_chats))
		{
			this.private_chats.push(uid);
			this.linkbar.innerHTML += '<div id="inferno_pm_chat_' + uid + '"><a href="#" onclick="javascript: inferno.open_chat(' + uid + '); return false;">' 
			+ name + 
			'</a> [<a href="#" onclick="javascript: inferno.close_chat(' + uid + '); return false;">X</a>]</div>';
		}
		this.open_chat(uid);
	}

	this.open_chat = function(uid)
	{
		if (this.tab == uid)
		{
			return false;
		}
		this.load_private_shouts(uid);
	}

	this.close_chat = function(uid)
	{
		tempdiv = document.getElementById('inferno_pm_chat_' + uid);
		tempdiv.parentNode.removeChild(tempdiv);
		remove_array_piece(uid, this.private_chats);
		this.init();
	}

	this.submit_styles = function()
	{
		if (this.style_update)
		{
			s = this.styles;
			s = JSON.stringify(s);

			var options = {
			    type: 'POST',
			    url: this.url + '?action=updatestyles',
			    data: 'styles=' + s
			};

			jQuery.ajax(options);
			this.style_update = false;
			this.alert('Your style properties have been updated.');
		}
	}

	this.trim = function(string)
	{
		return string.replace(/^\s+|\s+$/g, '');
	}

	this.submit_shout = function()
	{

		lastshout = this.lastshout;
		now = time();
		wait = lastshout + (this.shoutwait);

		shout = this.trim(this.shout_entry.value); // .trim();
		condition = this.is_valid_shout(shout);

		if (this.tab == -1 || this.tab == 0) {
			params = {shout: shout};
		} else {
			params = {shout: shout, pmid: this.tab};
		}

		if (condition == true)
		{
			if (!lastshout) {
				this.lastshout = now;
			} else {
				if (now < wait) {
					this.alert('You must wait ' + (wait - now) + ' more second' + ((wait - now == 1) ? '' : 's') + ' to shout again.');
					return false;
				} else {
					this.lastshout = null;
				}
			}

			var options = {
			    type: 'POST',
			    url: inferno.url + '?action=newshout',
			    data: params,
			    success: function() {
			    	inferno.submit_styles();
			    	if (inferno.tab > 0) {
			    		inferno.load_private_shouts(inferno.tab);
			    	} else {
			    		inferno.load_shouts();
			    	}
			    	inferno.update_idle_time();
			    }
			};
			jQuery.ajax(options);
			inferno.clear_shout();
			console.log(inferno.url + '?action=newshout');
		}
		else
		{
			this.alert(condition);
		}
	}

	this.toggle_smilies = function()
	{
		smileydiv = this.smiley_div;

		if (smileydiv.innerHTML == '')
		{
			jQuery.ajax({
				type:'get',
				url: this.url + '?action=getsmilies',
				success: function(transport) {
					response = transport;
					if (response == '') {
						inferno.alert('There are no smilies to display.');
					} else {
						smileydiv.innerHTML = response;
					}
				},
				error: function() { inferno.alert('Something went wrong...'); }
			});
		}
		else
		{
			smileydiv.innerHTML = '';
		}
	}

	this.append = function(text)
	{
		entry = this.shout_entry;
		entry.value += ' ' + text;
		entry.value = this.trim(entry.value); //.trim();
	}

	this.alert = function(message, timeout)
	{
		timeout = (typeof timeout === "undefined") ? true : timeout;
		alertdiv = this.alert_div;
		alertdiv.className = 'inferno_alert_div';
		alertdiv.innerHTML = '<b class="inferno_alert">Shoutbox Notice:</b> ' + message;

		if (timeout)
		{
			clearTimeout(this.timeout);
			this.timeout = setTimeout(function(){
				inferno.clear_alert();
			}, 4000);
		}
	}

	this.clear_alert = function()
	{
		alertdiv.innerHTML = '';
		alertdiv.className = '';
	}

	this.is_valid_shout = function(shout)
	{
		if (shout.length > this.shout_max_chars)
		{
			return 'You have used ' + shout.length + '/' + this.shout_max_chars + ' characters. Please shorten your shout.';
		}
		if (shout.length == 0)
		{
			return 'Please enter a message first.';
		}
		return true;
	}

	this.load_active_user_number = function(text)
	{
		spanid = this.active_user_number;
		spanid.innerHTML = text;
	}

	this.load_private_shouts = function (uid)
	{
		contentdiv = this.shoutbox_content;
		
		if (this.tab != uid)
		{
			clearInterval(this.interval);

			this.interval = setInterval(function() {
				if (inferno.is_idle() == false)
				{
					if (inferno.anus)
					{
						if (inferno.manage_anus())
						{
							inferno.load_private_shouts(uid);
							inferno.close_anus();
						}
					}
					else
					{
						inferno.load_private_shouts(uid);
					}
				}
			}, this.refresh_rate);

			contentdiv.innerHTML = 'Loading...';
			this.tab = uid;
		}

		jQuery.ajax({
			type: 'GET',
			url: this.url + '?action=getshouts&id=' + uid + inferno.screw_ie(),
			success: function(transport) {
				response = transport;
				if (response.indexOf('<<~!PARSE_SHOUT!~>>') != -1) {
					active_users = response.substring(0, response.indexOf('<<~!PARSE_SHOUT!~>>'));
					inferno.load_active_user_number(active_users);
					contentdiv.innerHTML = response.substring(response.indexOf('<<~!PARSE_SHOUT!~>>') + '<<~!PARSE_SHOUT!~>>'.length, response.length);
				}
				inferno.update_idle_time();
			},
			error: function() { inferno.alert('Something went wrong...'); }
		});
	}

	this.load_shouts = function()
	{
		contentdiv = this.shoutbox_content;

		if (this.tab != -1)
		{
			contentdiv.innerHTML = 'Loading...';
		}

		jQuery.ajax({ type: "GET",
				url: this.url + '?action=getshouts' + inferno.screw_ie(),
				success: function(transport) {
				response = transport;

				if (response.indexOf('<<~!PARSE_SHOUT!~>>') != -1) {
					active_users = response.substring(0, response.indexOf('<<~!PARSE_SHOUT!~>>'));
					inferno.load_active_user_number(active_users);
					contentdiv.innerHTML = response.substring(response.indexOf('<<~!PARSE_SHOUT!~>>') + '<<~!PARSE_SHOUT!~>>'.length, response.length);
					if (inferno.asc)
					{
						contentdiv.scrollTop = contentdiv.scrollHeight;
					}
				}
			},
			error: function() { inferno.alert('Something went wrong...'); }
		});
	}

	this.load_active_users = function()
	{
		if (this.tab != 0)
		{
			this.tab = 0;
			clearInterval(this.interval);
			contentdiv = this.shoutbox_content;
			contentdiv.innerHTML = 'Loading...';

			jQuery.ajax({
				type: 'GET',
				url: this.url + '?action=getactiveusers' + inferno.screw_ie(),
				success: function(transport) {
					contentdiv.innerHTML = transport;
					inferno.update_idle_time();
				},
				error: function() { alert('Something went wrong...'); }
			});
		}
	}

	this.clear_shout = function()
	{
		this.shout_entry.value = '';
		this.shout_entry.select();
	}
}

function in_array(needle, haystack)
{
	for (j = 0; j < haystack.length; j++)
	{
		if (needle === haystack[j])
		{
			return true;
		}
	}
	return false;
}

function remove_array_piece(needle, haystack)
{
	for (k = 0; k < haystack.length; k++)
	{
		if (needle === haystack[k])
		{
			haystack.splice(k, 1);
		}
	}
}

function time()
{
	return Math.round(new Date().getTime() / 1000);
}

window.onload = function() {
	inferno = new inferno();
	inferno.init();
}
