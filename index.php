<?php

define('PPLD_LOG_FILE', dirname(__FILE__).'/data/log.txt');

error_reporting(E_ERROR | E_WARNING | E_PARSE);
ini_set('display_errors', 0);
ini_set("log_errors", 1);
$errorLog = dirname(__FILE__)."/data/error-log.txt";
if (is_file($errorLog)) {
	file_put_contents(PPLD_LOG_FILE, "\n\n".file_get_contents($errorLog)."\n\n", FILE_APPEND);
	unlink($errorLog);
}

ini_set("error_log", $errorLog);

require('inc/config.php'); 
require('inc/post-config.php');

if (PPLD_LOG_TO_FILE) file_put_contents(PPLD_LOG_FILE, "\n=== PPLDAPI ===\n".date('Y-m-d g:i:sa T')."\n", FILE_APPEND);
if (php_sapi_name() != "cli" && !isset($_GET['js'])) {
	header('Access-Control-Allow-Origin: https://paliportal.com');  
	require('inc/lock.php');
}

require_once('inc/class.ppldapi.php');



if (isset($_GET['js'])) {
	header('Content-Type: application/javascript');
	ob_start();
	
	?><script>
	/*
	 PPLDAPI - Pali Portal Local Device API
	 Module: <?=$_GET["js"];?>
	 <?php $module = new ppldapi($_GET["js"], true); ?>
	*/
	var res = (function(){ 
		var self = window.top._ppldapi.getModule(<?=json_encode($_GET["js"]);?>);
	
<?="\t\t".str_replace("\n","\n\t\t", $module->getJS()); ?>
		
	 })();
	</script><?php
	
	
	$c = ob_get_clean();
	echo preg_replace('/\<\/?script\>/','', $c);
	exit();
}

if (strpos($_SERVER["SCRIPT_NAME"], '/ppldapi/modules/') !== false) {
	$name = explode('/',$_SERVER["SCRIPT_NAME"]);
	array_pop($name);
	$name = array_pop($name);
	$module = new ppldapi($name, true);
	return;
}

$actions = isset($_GET["a"]) ? explode(',',$_GET["a"]) : array();
$sq = isset($_GET["sq"]) ?  json_decode($_GET["sq"], true) : null;

if (in_array('info', $actions)) {
	echo "config:set:[\"RATE\",".PPLD_POLL_SPEED."]\n";
}

if ($dp = opendir('modules')) {
	while (($name = readdir($dp)) !== false) {
		$path = 'modules/'.$name;
		if (is_file($path.'/init.php')) {
			//echo "\n= MODULE: ".$name." =\n";
			$module = new ppldapi($name);;
			if ($sq && $sq[0] == $name) $module->setInfo($sq[1], $sq[2], true);
			
			foreach ($actions as $action) {
				$module->action($action);
			}
			$module->flushOut();
			$module->commit();
			unset($module);
			echo "\n";
		}
	}
}

