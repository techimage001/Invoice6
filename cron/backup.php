<?php
require_once dirname(__DIR__).'/app_private/config.php';
if(!is_file(DB_PATH))exit("No database file found\n");$dir=dirname(DB_PATH).'/backups';if(!is_dir($dir))mkdir($dir,0770,true);$target=$dir.'/backup-'.date('Ymd-His').'.sqlite';if(!copy(DB_PATH,$target))throw new RuntimeException('Backup failed');foreach(glob($dir.'/backup-*.sqlite')?:[] as $file)if(filemtime($file)<time()-30*86400)@unlink($file);echo "Backup written: ".basename($target)."\n";
