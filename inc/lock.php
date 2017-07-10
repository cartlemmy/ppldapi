<?php

if (!tryLock()) die("WAIT");
   
function tryLock() {
	if (is_file(LOCK_FILE) && filemtime(LOCK_FILE) > time() - 60) return false;
	file_put_contents(LOCK_FILE, getmypid());
	register_shutdown_function('unlink', realpath(LOCK_FILE));
	return true;
}
