<?php


// Logging / Debug
define('PPLD_VERBOSE', false);
define('PPLD_LOG_TO_FILE', true);
define('PPLD_POLL_SPEED', 5);


//define('PPLD_EXEC_PREFIX', 'sudo -u josh ');
define('PPLD_EXEC_PREFIX', '');
define('PPLD_CHECKIN_PHOTO_PATHS', '/var/www/html/ppldapi/data/media/Summer $year/$week');
define('LOCAL_ROOT', realpath(dirname(__FILE__)."/.."));
$file = LOCAL_ROOT.'/modules/net/data/host';
define('WWW_ROOT', 'http://'.(is_file($file) ? file_get_contents($file) : '127.0.0.1').'/ppldapi');
define('LOCK_FILE', "data/ppldapi.lock");
