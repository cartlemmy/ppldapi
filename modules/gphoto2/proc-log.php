<?php

error_reporting(E_ERROR | E_WARNING | E_PARSE);

if (substr($line, 0, 7) == "UNKNOWN") return;
$this->log($line);
//$this->log('from '.( $error ? 'error' : 'log').' file: '.$line);
if (preg_match('/error\s\((\-[\d]+)\:\s\'(.*?)\'\)/i', $line, $m)) {
	ob_start();
	switch ($m[1]) {
		case "-53":
			if ($this->exec('ps aux') == 0) {
				
				foreach ($this->lastOut as $line) {
					//$this->out('ps', $line);
					if (preg_match('/(gphoto2)/', $line, $m2)) {
						if (
							strpos($line, 'modules/gphoto2') !== false && 
							strpos($line, '--hook-script') === false
						) continue;
						$line = preg_split('/\s+/', $line);
						
						if ($line[1] != $this->getPID()) {
							echo 'We must kill '.$line[10].', he may be the problem.'."\n";
							$this->exec('kill '.$line[1]);
						}
					}
				}
	
				
			}
			break;
			
		case "-7":
			if (($user = $this->getInfo("gphoto2User")) != $this->getInfo("wwwUser")) {
				$this->alert( 
					"Please change your apache user to the gphoto2 user.\n".
					"Edit /etc/apache2/envvars so the below lines match your user and group\n".
					"export APACHE_RUN_USER=".$user."\n".   
					"export APACHE_RUN_GROUP=".$user."\n".
					"Be sure to also restart apache"
				);	
			}
			break;
	}
	
	$err = $this->getInfo('lastError', array('code'=>false));
	if ($err['code'] === false || $err['code'] != $m[1]) {
		$this->setInfo('lastError', array('code'=>$m[1], 'message'=>$m[2]));
		$this->out('error', array('code'=>$m[1], 'message'=>$m[2]."\n".ob_get_clean()));
	}
	
	$this->clearCmdQueue();
	$this->doneInit(2);
	file_put_contents($this->dir.'/data/hook-queue', '');
	return;
}

$sline = self::S($line);
$check = array('nocamerafound','waitingforeventsfromcamera');
foreach ($check as $cv) {
	if (strpos($sline, $cv) !== false) {
		switch ($cv) {
			case "nocamerafound":
				$this->doneInit(2);
				file_put_contents($this->dir.'/data/hook-queue', '');
				return;
			
			case "waitingforeventsfromcamera":
				$this->queueCmd('doneInit');
				$this->queueCmd('out','tethered',array('hook-queue'=>file_get_contents($this->dir.'/data/hook-queue')));
			
				$this->queueCmd('setInfo',"lastAction",'tethered');
			
				file_put_contents($this->dir.'/data/hook-queue', '');
				return;
				
			default:
				if ($error) {
					//$this->out('error', $line);
					return;
				}
				break;
		}			
	}
}


if (is_file($this->dir.'/initializing')) {
	//$this->out('hmmm',$sline);	
	return;
}

