<?php

$scanners = array('ifconfig'=>120, 'nmap'=>300);
foreach ($scanners as $scanName=>$interval) {
	$scanFile = $this->dir.'/last-scan-'.$scanName;
	$lastScan = is_file($scanFile) ? filemtime($scanFile) : 0;
	if (time() - $lastScan > $interval) {
		if ($this->exec('ifconfig') == 0) {
			foreach ($this->lastOut as $lo) {
				if (preg_match_all('/(inet|netmask|destination)\s(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})/', $lo, $m)) {
					$interface = array();
					foreach ($m[1] as $i=>$n) {
						$interface[$n] = $m[2][$i];
					}
					if ($interface["inet"] == "127.0.0.1") continue;
					file_put_contents(LOCAL_ROOT.'/modules/net/data/host',$interface["inet"]); 
					break;
				}
			}
		}
		touch($scanFile);
	}
}
