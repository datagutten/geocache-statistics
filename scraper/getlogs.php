<?Php
require 'class_geocaching_com.php';
$gc=new geocaching_com;
require '../class_cachetools.php';
$cachetools=new cachetools;

$table='geocaches';

if(preg_match('/[0-9a-f\-]{36}/',$argv[1]))
	$guid=$argv[1];
elseif(preg_match('/GC[0-9A-Z]+/',$argv[1]))
	$gccode=$argv[1];
else
	die("Invalid input");

$last_log=json_decode(file_get_contents('last_log.json'),true);
if(!isset($guid) && isset($gccode))
{
	$code=$cachetools->db->quote($gccode);
	$guid=$cachetools->query("SELECT Guid FROM geocaches WHERE GCCode=$code",'single');
	if(empty($guid))
	{
		shell_exec("php cacheinfo.php $gccode");
		//die("Cache $gccode is missing GUID\n");
	}
}
/*$last_visit=$cachetools->query("SELECT Visited FROM logs WHERE CacheGuid='$guid' ORDER BY Visited DESC LIMIT 0,1",'single');
if($last_visit==date('Y-m-d')) //Get last log in DB
	die("Last log is from today for $gccode\n");*/

echo "Fetching logs for $gccode\n";

$start=time();
$id_indb=$cachetools->query("SELECT LogID FROM logs WHERE CacheGuid='$guid'",'all_column',$query_time);
echo "Query time outside: ";
echo time()-$start;
echo "\n";
echo "Query time: $query_time\n";

$st_insert_log=$cachetools->db->prepare("INSERT INTO logs VALUES(:LogID,:CacheID,:CacheGuid,:LogGuid,:Latitude,:Longitude,:LatLonString,:LogType,:LogTypeImage,:LogText,:Created,:Visited,:UserName,:MembershipLevel,:AccountID,:AccountGuid,:Email,:AvatarImage,:GeocacheFindCount,:GeocacheHideCount,:ChallengesCompleted,:IsEncoded,:has_images)");
$st_update_count=$cachetools->db->prepare("UPDATE geocaches SET NumberOfLogs=? WHERE guid=?");

//$logs=$gc->logbook($guid,'all');

$logs=$gc->logbook($guid,100,1,$page_info); //Get first page of logs

$logcount=0;
echo "ID of last log in DB: {$last_log[$guid]}\n";
for($page=1; $page<=$page_info['totalPages']; $page++)
{
	if($page>1)
		$logs=$gc->logbook($guid,100,$page); //Fetch more logs

	foreach($logs as $log)
	{
		if(array_search($log['LogID'],$id_indb)!==false)
		{
			if(!isset($options['refresh']))
				break 2;
			else
				$cachetools->query("DELETE FROM logs WHERE LogId=".$cachetools->db->quote($log['LogID']),false);
			//continue;
		}

		if($last_log[$guid]==$log['LogID'])
		{
			echo "break\n";
			break 2;
		}
		if(!empty($log['Images']))
			$log['has_images']=1;
		else
			$log['has_images']=0;
		unset($log['creator'],$log['Images']);
		$log['CacheGuid']=$guid;
		//print_r($log);
		foreach($log as $key=>$value)
		{
			if($key=='Visited' || $key=='Created')
				$value=date('Y-m-d',strtotime($value));
			$st_insert_log->bindValue(':'.$key,$value);
		}
		if($st_insert_log->execute()===false)
		{
			$errorinfo=$st_insert_log->errorInfo();
			trigger_error("SQL error inserting log: {$errorinfo[2]}");
		}
		$logcount++;
	}
}

//$st_update_count->execute(array(,$guid));

$guid=$cachetools->db->quote($guid);
$last_found=$cachetools->query($q="SELECT Visited FROM logs WHERE LogType='Found it' AND CacheGuid=$guid ORDER BY Visited DESC",'single');
$last_log=$cachetools->query($q="SELECT Visited FROM logs WHERE CacheGuid=$guid ORDER BY Visited DESC",'single');
$NumberOfFinds=$cachetools->query("SELECT count(LogID) FROM logs WHERE CacheGuid=$guid AND LogType='Found it'",'single');

$cachetools->query("UPDATE $table SET NumberOfLogs=$logcount,LastLog='$last_log',LastFoundDate='$last_found',NumberOfFinds='$NumberOfFinds' WHERE Guid=$guid");

echo $logcount." logs inserted\n";