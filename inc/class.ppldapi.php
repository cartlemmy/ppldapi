<?php

class ppldapi {
	private $module;
	private $dir;
	private $outFile;
	private $info = false;
	private $infoUpdated = array();
	private $noEmit = array();
	private $cmdQueue = array();
	
	public $lastOut;
	
	public function __construct($module = false, $fromHook = false) {
		$this->module = $module;
		$this->setDir($module === false ? dirname(__FILE__) : getcwd().'/modules/'.self::SAFE($module));
		if (!$fromHook) {
			if (!$this->isRunning()) {
				$file = $this->dir.'/pre-proc.php';
				if (is_file($file)) require($file);
	
				$this->log('Not running, attempting init');
				$this->init();
			}
			
			if ($this->isRunning()) {
				$file = $this->dir.'/proc.php';

				if (is_file($file)) {
					require($file);
					$this->touch();
				}
			}
			$this->applyLog();
		}
	}
	
	public function __destruct() {
		$this->commit();
	}
	
	public function commit() {
		$this->commitCmd();
		if (count($this->infoUpdated)) {
			foreach ($this->noEmit as $n=>$v) {
				unset($this->infoUpdated[$n]);
			}
			$this->out('info-updated', $this->infoUpdated);
			file_put_contents($this->dir.'/data/cur-info.json', json_encode($this->info, JSON_PRETTY_PRINT));
			$this->infoUpdated = array();
			$this->noEmit = array();
		}
	}
	
	public function applyLog() {
		$logFiles = array('log','errlog');
		foreach ($logFiles as $t) {
			$logFile = $this->dir.'/data/'.$t;
			if (is_file($logFile) && ($fp = fopen($logFile, 'r'))) {
				while (!feof($fp)) {
					if (($line = trim(fgets($fp))) !== '') {
						if ($this->procLogLine($line, $t == "errlog") === false) {
							while (!feof($fp)) {
								if (($line = trim(fgets($fp))) !== '') {
									$this->log('FLUSHING '.$t.': '.$line);
								}
							}
							break;
						}
					}
				}
				fclose($fp);	
				file_put_contents($logFile, '');		
			}
		}
	}
	
	private function procLogLine($line, $error) {
		$file = $this->dir.'/proc-log.php';
		$res = true;
		if (is_file($file) && ($res = require($file)) === false) {
			//$this->log('proc-log.php handled: '.$line);
			return true;
		}
		if ($res == "flush") {
			$this->log('Flush requested ('.$line.')');
			return false;
		}
		
		if (is_string($res)) $this->out('log', $res);
		return true;
	}
	
	public function action($a) {
		switch ($a) {
			case "info":
				$this->out('info', $this->getInfo('**'));
				break;
			
			default:
				$this->out('unknown-action', $a);
				break;
		}
	}
	
	public function getInfo($n = '*', $def = null) {
		$file = $this->dir.'/data/cur-info.json';
		
		if (!$this->info) {
			$this->info = is_file($file) ? json_decode(file_get_contents($file), true) : array();
			$this->infoUpdated = array();
		}
		
		if ($n == '**') {
			$this->setInfo("lastActive",$this->lastActive());
			$this->setInfo("isRunning",$this->isRunning());
		}
		if (substr($n, 0, 1) == '*') return $this->info;
		return isset($this->info[$n]) ? $this->info[$n] : $def;
	}
	
	public function setInfo($n, $v = false, $noEmit = false) {
		
		if (is_array($n)) {
			foreach ($n as $nn=>$vv) {
				$this->setInfo($nn, $vv);
			}
			return;
		}
		
		if (!$this->info) $this->getInfo();

		$file = $this->dir.'/proc-set-info.php';
		if (is_file($file)) require($file);
		
		if (!array_key_exists($n, $this->info) || $this->info[$n] != $v) {
			$this->info[$n] = $v;
			$this->infoUpdated[$n] = $v;
			if ($noEmit) $this->noEmit[$n] = true;
		}
	}
	 
	public function setDir($dir) {
		$this->dir = false;
		if (is_dir($dir)) {
			$this->dir = $dir;
			$this->outFile = $this->dir.'/data/out';
			$this->initIfInactive();
		} else {
			echo 'setdir-failed:'.$dir."\n";
			exit(-2);
		}
	}
	
	public function clean($files = false) {
		
		if ($files === false) $files = array();
		if ($this->getInfo('pid')) {
			$files[] = $this->get('pidfile');
		}
		$this->setInfo('pid', false);
		
		foreach ($files as $file) {
			if (is_file($file)) {
				@unlink($file);
			}
		}
	}
	
	public function shouldWait() {
		if (is_file($this->dir.'/initializing')) {
			$took = time() - filemtime($this->dir.'/initializing');
			if ($took >= 60) {
				$this->out('init-failed','Initialization took too long ('.$took.' seconds), resetting');
				unlink($this->dir.'/initializing');	
				return false;			
			}
			$this->log('Waiting for previous initialization');
			return true;
		}
		if (is_file($this->dir.'/wait-to-init')) {
			if ((int)file_get_contents($this->dir.'/wait-to-init') > time()) {
				$this->log('Wating to init');
				return true;
			}
		}
		return false;
	}
	
	public function init() {
		if ($this->shouldWait()) return;
		
		if ($this->isRunning()) {
			$this->out('init-failed',"Attempted init, but already running");
		}
		
		$this->clean();
		
		$this->log($this->dir.'/init.php?');
		if (!is_file($this->dir.'/init.php')) return false;
		
		
		$info = require($this->dir.'/init.php');
		$this->log($info);
		if (!isset($info["init"])) $info["init"] = false;
		if ($info["pid"] !== false || $info["init"] !== false) {
			if (!isset($info["src"])) {
				$src = $this->dir.'/main.js';
				if (is_file($src)) {
					$info["src"] = $src;;
				} else $info["src"] = false;
			}
			
			$this->touch(!$info["init"]);
			if (isset($info["requiredPrograms"])) {
				$req = array();
				foreach ($info["requiredPrograms"] as $name) {
					if (!$this->programInstalled($name)) {
						$req[] = $name.' must be installed';
					}
				}
				if (count($req)) $this->alert(implode("\n",$req));
			}
			$this->setInfo($info);
			return true;			
		}
		$this->setInfo($info);
		$this->out('init-failed',$info);
		return false;
	}
	
	public function getJS() {
		ob_start();
		$info = $this->getInfo();
		if ($info["src"]) {
			$src = $this->dir.'/main.js';
			echo "/*\n".$this->dir.'/main.js'."\n*/\n";
			readfile($this->dir.'/main.js');
			echo "self.jsDone(true);\n";
		} else {
			echo "self.jsDone(false);\n";
		}
		return ob_get_clean();
	}
	
	public function doneInit($aborted = false) {
		@unlink($this->dir.'/initializing');
		if ($aborted) file_put_contents($this->dir.'/wait-to-init', time() + 3);
	}
	
	public function touch($initializing = false) {
		touch($this->dir.'/last-active');
		if ($initializing) {
			$this->log('initializing');
			touch($this->dir.'/initializing');
		}
	}
	
	public function initIfInactive() {
		if (!$this->active()) $this->init();
	}
	
	public function active($since = '-1 hour') {
		return $this->lastActive() > strtotime($since);
	}
	
	public function lastActive() {
		$file = $this->dir.'/last-active';
		return is_file($file) ? filemtime($file) : 0;
	}
	
	public function spawn() {
		$args = func_get_args();
		$with = array_shift($args);
		//$args[0] = ($with ? ' '.$with : '').$args[0].' > [%logfile] 2>&1 & echo $! > [%pidfile]';
		$args[0] = ($with ? ' '.$with : '').$args[0].' > [%logfile] 2>[%errlogfile] & echo $! > [%pidfile]';
		$rv = call_user_func_array(array($this,'exec'), $args);
		if ($rv != 0) return false;
		return $this->getPID();
	}

	private function getPID() {
		if ($this->getInfo('init')) return false;
		if ($pidfile = $this->get('pidfile')) {
			if (is_file($pidfile)) {
				$pid = (int)trim( @file_get_contents($pidfile));
				return $pid > 0 ? $pid : false;
			}
		}
		return false;
	}
	
	public function exec() {
		$args = func_get_args();

		$cmd = array_shift($args);
		$replace = array();
		if (preg_match_all('/\[\%([a-z0-9]+)\]/i', $cmd, $m)) {
			foreach ($m[1] as $i=>$n) {
				$replace[$m[0][$i]] = escapeshellarg(isset($args[$n - 1]) ? $args[$n - 1] : $this->get($n, ''));
			}
		}
		$cmd = PPLD_EXEC_PREFIX.str_replace(array_keys($replace), array_values($replace), $cmd);

		exec($cmd, $out, $rv);
		$this->log(array_shift(explode(' 2>', $cmd)));
		$this->lastOut = $out;
		return $rv;
	}

	public function execGet() {
		$tmpFile = $this->get('tmperrlogfile');
		@unlink($tmpFile);
		$args = func_get_args();
		$args[0] .= ' 2>[%tmperrlogfile]';
		call_user_func_array(array($this,'exec'), $args);
		$err = file_exists($tmpFile) ? trim(file_get_contents($tmpFile)) : "";
		return trim(implode("\n",$this->lastOut)).($err ? "\n\nERROR:\n".$err : "");
	}
	
	public function programInstalled($name) {
		return $this->exec('hash [%1]', $name) == 0;
	}
	
	public function get($n, $def = null) {
		switch ($n) {
			case "logfile":
			case "errlogfile":
			case "pidfile":
			case "tmperrlogfile":
				return $this->dir.'/data/'.substr($n, 0, -4);
		}
		return $def;
	}
	
	public function alert($message, $uid = false) {
		if ($uid === false) $uid = md5($message);
		$this->out('alert', $uid, $message);
	}

	public function queueCmd() {
		$this->cmdQueue[] = func_get_args();
	}
	
	public function undoCmd() {
		array_pop($this->cmdQueue);
	}
	
	public function clearCmdQueue() {
		$this->cmdQueue = array();
	}
	
	public function commitCmd() {
		$ran = array();
		while ($args = array_shift($this->cmdQueue)) {
			$func = array_shift($args);
			$res = call_user_func_array(array($this, $func), $args);
			//$ran[] = $func.'('.json_encode($args).'):'."\n\r".json_encode($res);
		}
		//if (count($ran)) $this->out('cmd-queue-ran', $ran);
	}
	
	
	public function out() { 
		$args = func_get_args();
		$action = array_shift($args);
		file_put_contents($this->outFile, $this->module.':'.self::SAFE($action).':'.json_encode($args)."\n", FILE_APPEND);
		if ($action != 'log') {
			array_unshift($args, $action);
			$this->log($args);
		}
	}
	
	private function dbg($txt) {
		file_put_contents($this->dir.'/data/debug.txt', $txt."\n", FILE_APPEND);
	}
	
	public function log() {
		$args = func_get_args();
		if (PPLD_LOG_TO_FILE) {
			$logArgs = count($args) == 1 ? $args[0] : $args;
			file_put_contents(
				PPLD_LOG_FILE,
				$this->module.': '.str_replace("\n", "\n\t",
					is_string($logArgs) ? $logArgs : 
						preg_replace('/^(\[|\{)\s+/', '$1',
						preg_replace('/\s+(\]|\})$/', '$1',
							json_encode($logArgs, JSON_PRETTY_PRINT)
						))
				)."\n",
				FILE_APPEND
			);
		}
		array_unshift($args, 'log');
		if (PPLD_VERBOSE) call_user_func_array(array($this,'out'), $args);
	}
	
	public function flushOut() {
		readfile($this->outFile);
		file_put_contents($this->outFile, '');
	}
	
	public function webPath($path) {
		return str_replace(LOCAL_ROOT, WWW_ROOT, $path);
		
	}
	
	private static function S($n) {
		return strtolower(preg_replace('/[^\w\d\-]+/','',$n));
	}
	
	private static function SAFE($n) {
		return trim(preg_replace('/[^\w\d\-]+/','-',$n), '-');
	}

	private function isRunning(){
		if ($this->getInfo('init')) {
			return $this->active('-1 minute');
		}
		
		try {
			$result = shell_exec(sprintf("ps %d", $this->getPID()));
			if( count(preg_split("/\n/", $result)) > 2){
				$this->setInfo("pid",$this->getPID());
				return true;
			}
		} catch(Exception $e){}

		return false;
	}
	
	public static function toCamelCase($string) {
		$v = explode("-",preg_replace("/[^\w\d]+/","-",$string));
		$rv = array();
		for ($i = 0; $i < count($v); $i ++) {
			if ($i == 0) {
				$rv[] = strtolower($v[$i]);
			} else {
				$rv[] = ucfirst(strtolower($v[$i]));
			}
		}
		$rv = implode("",$rv);
		if (preg_replace("/[\d]/","",substr($rv,0,1)) == '') {
			$rv = "_"+$rv;
		}
		return $rv;
	}
}
