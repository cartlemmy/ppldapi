<?php

if ($n == "attendee") {
	$thumbRefFile = $this->dir.'/thumbref/'.$v;
	if (is_file($thumbRefFile)) {
		$this->log('$thumbRefFile: '.$thumbRefFile);
		$this->out('thumb-located', json_decode(file_get_contents($thumbRefFile), true));
	}
}
