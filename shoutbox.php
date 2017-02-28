<?php
define('IN_MYBB', 1); 
require_once("./global.php");
require_once("./inc/plugins/inferno.php");
define('THIS_SCRIPT', 'shoutbox.php');
$tid = $theme['tid'];
$global_css = $mybb->settings['bburl']."/cache/themes/theme".$tid."/global.min.css";
if(@file_get_contents($global_css) === false)
{
	$global_css = $mybb->settings['bburl']."/cache/themes/theme1/global.css";
}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xml:lang="en" lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<meta http-equiv="Content-Script-Type" content="text/javascript" />
<link type="text/css" rel="stylesheet" href="<?php echo $global_css ?>" />
<link type="text/css" rel="stylesheet" href="<?php echo $mybb->settings['bburl'] . "/cache/themes/theme" . $tid . "/inferno.css" ?>" />
<?php
inferno_popup();
echo $inferno_shoutbox;
?>
</body>
</html>