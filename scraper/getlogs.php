<?Php
//Get all logs for a cache
require_once 'class_cachetools_insert.php';
$cachetools=new cachetools_insert();

if(preg_match('/[0-9a-f\-]{36}/',$argv[1]))
	$guid=$argv[1];
elseif(preg_match('/GC[0-9A-Z]+/',$argv[1]))
	$gccode=$argv[1];
else
	die("Invalid input");

if(!isset($guid) && isset($gccode))
{
	$guid=$cachetools->cache_gccode_to_guid($gccode);
	if(empty($guid))
	{
		shell_exec("php cacheinfo.php $gccode");
		//die("Cache $gccode is missing GUID\n");
	}
}

echo "Fetching logs for $guid\n";
$logcount=$cachetools->getlogs($guid);
echo $logcount." logs inserted\n";