<?Php
$db_gsak=new PDO('sqlite:sqlite.db3');
require '../scraper/class_cachetools_insert.php';
$cachetools=new cachetools_insert;
//$removefields=array('Changed','Archived','Degrees','Distance','Bearing','DNF','DNFDate','Found','FoundCount','FoundByMeDate','LastGPXDate','LastUserDate','FTF','HasCorrected','HasTravelBug','HasUserNote','MacroFlag','MacroSort','UserData','User2','User3','User4','UserFlag','UserNoteDate','UserSort','Created','GcNote','Color','Source','Symbol','Watch','IsOwner','LatOriginal','LonOriginal','ChildLoad','LinkedTo','GetPolyFlag','SmartName','SmartOverride','Lock');

$st=$db_gsak->query('SELECT
Code AS GCCode,
Name,
PlacedBy,
CacheId,
CacheType AS CacheType_text,
Container,
County,
Country,
Difficulty,
LastFoundDate,
LastLog,
Latitude,
LongHtm,
Longitude,
NumberOfLogs,
OwnerId,
OwnerName,
PlacedDate,
ShortHtm,
State,
Terrain,
Status,
Elevation,
IsPremium,
Guid,
FavPoints,
Changed as LastUpdated
FROM Caches
WHERE Guid!=\'\'');


//$wantedfields=array_diff(array_keys($caches[0]),$removefields);
/*echo "ALTER TABLE `geocaches`.`geocaches`\n";
foreach($removefields as $field)
{
	echo "DROP COLUMN `$field`,\n";
}	
die();*/
$cachetypes=array('T'=>2,'M'=>3,'V'=>4,'U'=>8,'R'=>137,'B'=>5);
$datefields=array('LastUpdated');

//foreach($caches as $cachekey=>$cache)
$st_check=$cachetools->db->prepare('SELECT LastUpdated FROM geocaches WHERE GCCode=?');

while($row=$st->fetch(PDO::FETCH_ASSOC))
{
	$updated=$cachetools->execute($st_check,array($row['GCCode']),'single');
	if($st_check->rowCount()>0)
		continue;
	/*if(!empty($updated))
		continue;
	if($updated>strtotime($row['LastUpdated']))
		continue;*/

	echo $row['GCCode']."\n";

	if(!isset($cachetypes[$row['CacheType_text']]))
		throw new Exception(sprintf('Unkown cache type: %s (%s)',$row['CacheType_text'],$row['GCCode']));
	else
	{
		$row['CacheType']=$cachetypes[$row['CacheType_text']];
		unset($row['CacheType_text']);
	}
	$fields=array_keys($row);

	//continue;
	if(!isset($st_insert_cache))
	{
		$fields=implode('`,`',array_keys($row));
		$q="INSERT INTO geocaches (`$fields`) VALUES(";
		foreach(array_keys($row) as $field)
			$q.=":$field,";
		$q=substr($q,0,-1).')';
		$st_insert_cache=$cachetools->db->prepare($q);
	}
	foreach($row as $field=>$value)
	{
		if(array_search($field,$datefields)!==false)
			$value=strtotime($value);
		if($field=='Container')
			$value=strtolower($value);
		$st_insert_cache->bindValue(':'.$field,$value);
	}

	
	$cachetools->execute($st_insert_cache,NULL);
	//break;
}