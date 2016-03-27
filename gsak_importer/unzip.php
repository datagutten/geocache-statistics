<?Php
if(!empty($argv[1]) && file_exists($argv[1]))
	$zipfile=$argv[1];
else
	die();
shell_exec(sprintf('unzip -j -d "%s" "%s" Default/sqlite.db3',dirname(__FILE__),$zipfile));