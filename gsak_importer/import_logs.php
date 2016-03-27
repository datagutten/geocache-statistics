<?Php
$db_gsak=new PDO('sqlite:sqlite.db3');
require '../../cachetools/scraper/class_cachetools_insert.php';
$cachetools=new cachetools_insert;

$st_logs=$db_gsak->query('SELECT
Logs.lLogId AS LogID,
Caches.CacheID AS CacheID,
Caches.Guid AS CacheGuid,
Logs.lType AS LogType,
LogMemo.lText AS LogText,
logs.lDate AS Visited,
logs.lBy AS UserName,
logs.lownerid AS AccountID,
logs.lEncoded AS IsEncoded
FROM Logs,Caches,LogMemo WHERE Logs.lParent=Caches.Code AND Logs.lLogId=LogMemo.lLogId');
if($st_logs===false)
	print_r($db_gsak->errorInfo());
$st_check_log=$cachetools->db->prepare('SELECT LogID FROM logs WHERE LogID=?');
while($row=$st_logs->fetch(PDO::FETCH_ASSOC))
{
	$cachetools->execute($st_check_log,array($row['LogID']));
	if($st_check_log->rowCount()>0)
		continue;
	print_r($row);
	if(!isset($st_insert_log))
	{
		$fields=implode('`,`',array_keys($row));
		$q="INSERT INTO logs (`$fields`) VALUES(";
		foreach(array_keys($row) as $field)
			$q.=":$field,";
		$q=substr($q,0,-1).')';
		$st_insert_log=$cachetools->db->prepare($q);
	}
	foreach($row as $field=>$value)
	{
	/*	if(array_search($field,$datefields)!==false)
			$value=strtotime($value);
		if($field=='Container')
			$value=strtolower($value);*/
		$st_insert_log->bindValue(':'.$field,$value);
	}
	$cachetools->execute($st_insert_log,NULL);
	$cachetools->cache_finds_count($row['CacheGuid']);
	//break;
}