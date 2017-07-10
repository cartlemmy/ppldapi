<?php

$ts = time();
$replace = json_decode(date('{"\$\y\e\a\r":"Y","\$\m\o\n\t\h":"m","\$\d\a\t\e":"d","\$\d\o\w":"w"}', $ts), true);

$replace['$week'] = 'Week of '.date('m-d', strtotime('-'.$replace["dow"].' days', $ts));

$paths = explode(';',str_replace(array_keys($replace), array_values($replace), PPLD_CHECKIN_PHOTO_PATHS));
print_r($paths);
