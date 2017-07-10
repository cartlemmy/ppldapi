#!/usr/bin/php
<?php

error_reporting(E_ERROR | E_WARNING | E_PARSE);

$moduleDir = dirname(__FILE__);
chdir(realpath(dirname(__FILE__)."/../.."));

require(getcwd().'/index.php');

if (isset($_SERVER["APACHE_RUN_USER"])) $module->setInfo("gphoto2User",$_SERVER["APACHE_RUN_USER"]);
$module->setInfo("hookCalled", time());

$arg = isset($_SERVER["ARGUMENT"]) ? $_SERVER["ARGUMENT"] : null;
if ($module->getInfo("lastAction") != 'tethered') {
	switch ($_SERVER["ACTION"]) {
		case "init": case "start":
			file_put_contents($moduleDir.'/data/hook-queue', $_SERVER["ACTION"]."\n", FILE_APPEND);
			exit(0);
		
		case "stop":
			file_put_contents($moduleDir.'/data/hook-queue', 'stop');
			break;
	}
} else {
	$module->out('gp-'.$_SERVER["ACTION"], $arg);
	$module->setInfo("lastAction",$_SERVER["ACTION"]);
}


if ($_SERVER["ACTION"] == "download") {
	$file = $_SERVER["ARGUMENT"];
	$mime = $module->execGet('file --mime-type -b [%1]', $file);
	$module->log("MIME: ".$mime);
	
	$fileBase = explode('.',array_pop(explode('/', $file)));
	$ext = array_pop($fileBase);
	$fileBase = implode('.', $fileBase);;
		
	if (!($newName = $module->getInfo('attendee'))) {
		$newName =$fileBase;
	}
	$num = 1;
	do {
		$outFile = $moduleDir.'/queue/'.$newName.($num > 1 ? '-p'.$num : '').'.jpg';
		$num++;
	} while (is_file($outFile));
	
	$thumbFile = $moduleDir.'/queue/'.$newName.($num > 1 ? '-p'.$num : '').'-thumb.jpg';
	
	switch ($mime) {
		case "image/x-canon-cr2":
			//get preview
			$exiv = $module->execGet('exiv2 -pp [%1]', $file);
			
			$previews = array();
			$previewThumb = false;
			$fullJPEG = false;
			if (preg_match_all('/(\d+)\:\s+([\w\d]+\/[\w\d]+)\,\s+([\d]+)x([\d]+)\s(pixels)\,\s+(.*)/i', $exiv, $m)) {
				for ($i = 0; $i < count($m[0]); $i++) {
					if ($m[2][$i] == "image/jpeg" && !$previewThumb) {
						$previewThumb = $m[1][$i];
					} elseif ($m[2][$i] == "image/jpeg" && $m[3][$i] * $m[4][$i] > 5000000) {
						$fullJPEG = $m[1][$i];
					}
				}
			}			
			
			if ($previewThumb) {
				$module->exec(
					'exiv2 -ep'.$previewThumb.' -l [%1] [%2]; mv [%3] [%4]',
					$moduleDir.'/queue/',  $file,
					$moduleDir.'/queue/'.$fileBase.'-preview'.$previewThumb.'.jpg',
					$thumbFile
				);
				$module->out('thumb', $thumbFile, $module->webPath($thumbFile));
			}
			
			
			$outFile = str_replace('.jpg', '.cr2',  $outFile);
			$module->exec(
				'mv [%1] [%2]',
				$file, $outFile
			);
			
			$module->out('photo-ready', $outFile, $module->webPath($outFile));
			
			/*$module->exec(
				'touch [%1]; ufraw-batch --rotate=no '.
				'--compression=95 --out-type=jpg '.
				'--output=[%2] [%3] &',
				$outFile.'.wait',
				$outFile, $file
			);*/
			break;
		
		case "image/jpeg":
			$module->exec(
				'convert [%1] -resize 200000@ -auto-orient [%2]',
				$file, $thumbFile
			);
			$module->out('thumb', $thumbFile, $module->webPath($thumbFile), $file);
			file_put_contents($moduleDir.'/thumbref/'.$newName, json_encode(array($thumbFile, $module->webPath($thumbFile), $file))."\n");
			
			$module->exec(
				'mv [%1] [%2]',
				$file, $outFile
			);
			$module->out('photo-ready', $outFile, $module->webPath($outFile));
			break;
	}
}

$module->commit();
exit(0);


