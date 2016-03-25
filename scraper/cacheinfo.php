<?Php
$options=getopt('',array('owner:','user:','getlogs','show_gccodes','gccodes_file:','write_gccodes','refresh','gccode:','nocache','guid:','logqueue','cachequeue','cache_gccodes'));
if(isset($options['guid']))
	$options['nocache']=true;

require_once 'class_geocaching_com.php';
$gc=new geocaching_com;
require_once 'class_cachetools_insert.php';
$cachetools=new cachetools_insert($options);
require '../queuemanager/class_queuemanager.php';
require '../config_db.php';
$queue=new queuemanager(dirname(__FILE__));
$queue->init('cachetools');

$error=$gc->init_check();

if(!empty($error))
	trigger_error($error,E_USER_ERROR);

$table='geocaches';

if(isset($options['owner']))
	$gccodes=$gc->caches_by_owner($options['owner']);
elseif(isset($options['user']))
	$gccodes=$gc->user_finds($options['user']);
elseif(isset($options['gccodes_file']))
	$gccodes=file($options['gccodes_file'],FILE_IGNORE_NEW_LINES);
elseif(isset($options['gccode']))
	$gccodes=array($options['gccode']);
elseif(isset($options['guid']))
	$gccodes=array($options['guid']);

if(isset($options['show_gccodes']))
	die(implode("\n",$gccodes));


$gccodes_indb=$cachetools->query("SELECT gccode FROM geocaches",'all_column');
if(isset($options['logqueue']) || isset($options['cachequeue']) || isset($options['cache_gccodes']) || isset($options['write_gccodes']))
{
	if(isset($options['refresh']))
		$cmd_extra=' --refresh';
	else
		$cmd_extra='';
	if(isset($options['owner']))
		$action_text="owned by {$options['owner']}";
	elseif(isset($options['user']))
		$action_text="found by {$options['user']}";
	else
		$action_text='';

	if(isset($options['write_gccodes']))
	{
		/*if(isset($options['user']))
			$filename=$options['user'].'_finds.txt';
		elseif(isset($options['owner']))
			$filename=$options['owner'].'_owned.txt';*/
		if(!empty($action_text))
			file_put_contents('gccodes '.$action_text.'.txt',implode("\r\n",$gccodes));
		else
			die("Unknown filename\n");
	}
}
foreach($gccodes as $key=>$GCcode)
{
	if(!isset($options['nocache']))
	{
		if(array_search($GCcode,$gccodes_indb)!==false && !isset($options['refresh']))
			echo "Skipping $GCcode, already in DB\n";
		else
		{
			if(!isset($options['cachequeue']))
			{
				echo "Fething info for $GCcode\n";
				$data=$gc->get("http://coord.info/$GCcode");
				if(isset($options['refresh']))
					$cachetools->query("DELETE FROM geocaches WHERE gccode=".$cachetools->db->quote($GCcode),false);
				$cachetools->insert_cache($data);
				$cachetools->attributes($GCcode,$data);
				echo "Inserted $GCcode\n";
			}
			else
			{
				$cmd="php cacheinfo.php --gccode $GCcode $cmd_extra";
				$description=sprintf('Get cache %s %s',$GCcode,$action_text);
				//$cachetools->query("INSERT INTO queue (command,folder,description) VALUES ('$cmd','scraper','$description')");
				$queue->add_job($cmd,$description);
			}
		}
	}
	if(isset($options['getlogs']))
	{
		//echo shell_exec("php getlogs.php $GCcode>/dev/null &");

		$time=time();
		if(!isset($options['guid']))
		{
			$guid=array_search($GCcode,$cachetools->GCCode_guid);
			/*echo "Find guid: ";
			echo time()-$time;
			echo "\n";*/
		}
		else
			$guid=$options['guid'];
		if(empty($guid))
			echo "Missing guid for $GCcode\n";
		else
		{
			if(isset($options['logqueue']))
			{
				$cmd="php cacheinfo.php --guid $guid --getlogs --nocache";
			
				$description=sprintf('Get logs for %s %s',$GCcode,$action_text);
				//$cachetools->query("INSERT INTO queue (command,folder,description) VALUES ('$cmd','scraper','$description')");
				$queue->add_job($cmd,$description);
			}
			else
			{
				$time=time();
				$logcount=$cachetools->getlogs($guid);
				echo "$logcount logs inserted in ";
				echo time()-$time;
				echo " seconds\n";
			}
		}
	}
}