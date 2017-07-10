<?php

$scanFile = $this->dir.'/data/last-pre-scan';
$lastScan = is_file($scanFile) ? filemtime($scanFile) : 0;
	if (time() - $lastScan > 30) {
	$summary = explode("\n", $this->execGet('gphoto2 --summary'));

	$cameras = array();
	$camera = array();
	$branch = array();
	$curBranchIndent = 0;
	foreach ($summary as $line) {
		if (trim($line)) {
			$line = explode(":", $line, 2);
			if (count($line) == 2) {
				$n = self::toCamelCase(trim($line[0]));
				$v = trim($line[1]);
				if ($n == "cameraSummary") {
					//Next camera
					if (count($camera)) {
						$cameras[] = $camera;
					}
					$camera = array();
					$curBranchIndent = 0;
				} else {
					if ($v == "") {
						$camera[$n] = array();
						
					} else {
						if (preg_match('/^\s+/', $line[0], $m)) {
							$indent = strlen($m[0]);
						} else $indent = 0;
						while (count($branch) < $indent) {
							$branch[] = array();
						}
						if (!isset($branch[$indent])) $branch[$indent] = array();
						while (count($branch) > $indent) {
							$o = array_pop($branch);
							
							//$this->log('BLOP', count($branch), );
						}
						if ($indent == 0) {
							$camera[$n] = $v;						
						} else {
							$branch[$indent][$n] = $v;					
						}
					}
				}
			}
		}
	}

	if (count($camera)) {
		$cameras[] = $camera;
	}
	$this->setInfo('summary', $cameras);
	touch($scanFile);
}
