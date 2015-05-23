<html>
<body>
<?php

define('IN_MYBB', 1); require_once("./global.php"); 
require_once("./inc/plugins/inferno.php");
$tid = $theme['tid'];
inferno_popup();
echo $inferno_shoutbox;
?>
<style type="text/css" src="cache/themes/theme<?php echo $tid ?>/global.css" >
@import url(cache/themes/theme<?php echo $tid ?>/global.css);
</style>
<style type="text/css">
@import url(cache/themes/theme<?php echo $tid ?>/inferno.css);
</style>

</body></html>