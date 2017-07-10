<?php

$user = posix_getpwuid(posix_geteuid());
return array(
	"requiredPrograms"=>array("gphoto2", "ufraw-batch", "convert", "exiv2"),
	"name"=>"Camera Tethering",
	"wwwUser"=>$user["name"],
	// TODO: The below param was set specifically for the Canon 5D MkII
	// --set-config-value "capturetarget=Memory Card"
	"pid"=>false//"pid"=>$this->spawn(false, 'gphoto2 --capture-tethered --keep --set-config-value "capturetarget=Memory Card" --force-overwrite --hook-script=[%1]', dirname(__FILE__).'/hook.php')
	
);
