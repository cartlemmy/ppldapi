<?php

$moduleDir = dirname(__FILE__);

$hookQueueFile = $moduleDir.'/data/hook-queue';
if (is_file($hookQueueFile)) {
	if (($hookQueue = trim(file_get_contents($hookQueueFile))) != "") {
		$hookQueue = explode("\n", $hookQueue);
		foreach ($hookQueue as $action) {
			if ($action == "start") {
				$this->out('tethered','from proc.php');
				$this->setInfo(array(
					"lastAction"=>'tethered',
					"lastError"=>null
				));
				$this->doneInit();
			}
		}
		file_put_contents($hookQueueFile, '');
	}
}

if ($dp = opendir($moduleDir.'/test')) {
	while (($file = readdir($dp)) !== false) {
		$path = $moduleDir.'/test/'.$file;
		if (is_file($path)) {
			$mime = $this->execGet('file --mime-type -b [%1]', $path);
			if (substr($mime, 0, 5) == 'image') {
				$this->log('Found test image: '.$path);
				$this->log(
					$this->execGet(
						'export ACTION=[%1]; export ARGUMENT=[%2]; [%3]',
						"download",
						$path,
						$moduleDir.'/hook.php'
					)
				);
				break;
			}
		}
	}
	closedir($dp);
}

$scanFile = $this->dir.'/data/last-scan';
$lastScan = is_file($scanFile) ? filemtime($scanFile) : 0;
if (time() - $lastScan > 30) {
	$camerasDef = explode("\n", $this->execGet('gphoto2 --auto-detect'));
	$header = preg_split('/([\s]+)/', array_shift($camerasDef), null, PREG_SPLIT_OFFSET_CAPTURE);
	array_shift($camerasDef);
	$cameras = array();
	foreach ($camerasDef as $camera) {
		$c = array();
		foreach ($header as $i=>$h) {
			if (isset($header[$i+1])) {
				$c[$h[0]] = trim(substr($camera, $h[1], ($header[$i+1][1] - $h[1])));
			} else {
				$c[$h[0]] = trim(substr($camera, $h[1]));
			}
		}
		$port = $c["Port"];
		unset($c["Port"]);
		$cameras[$port] = $c;
	}
	touch($scanFile);
	$this->setInfo('cameras', $cameras);
}

