<?Php
require 'class_cachetools_stats.php';
$cachetools=new cachetools_stats;
$cachetools->set_locale('nb_NO.utf8','web');

require 'class_cachetools_web.php';
$cachetools_web=new cachetools_web;
$dom=$cachetools_web->dom;
?>
<!DOCTYPE HTML>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title><?Php echo _('Caches with attribute'); ?></title>
</head>

<body>
<?Php
$attribute=$_GET['attribute'];
$attribute_value=$_GET['state'];
$attribute_db=$cachetools->db->quote($_GET['attribute']);
$attribute_value_db=$cachetools->db->quote($_GET['state']);
$attribute_info=$cachetools_web->attributes[$attribute];
$user=$_GET['user'];
$user_db=$cachetools->db->quote($_GET['user']);

$header=_("Caches %s by %s with attribute %s");
$header="<h2>$header</h2>";

//The field for hidden or found is filled in later
if($_GET['state']=='no')
{
	if($attribute_info['word_no']=='No')
		$header=sprintf($header,'%s',$user,strtolower($attribute_info['word_no'].' '.$attribute_info['name'])); //Negative word before name
	else
		$header=sprintf($header,'%s',$user,strtolower($attribute_info['name'].' '.$attribute_info['word_no'])); //Negative word after name
}
else
	$header=sprintf($header,'%s',$user,strtolower($attribute_info['name'])); //Positive

if($_GET['type']=='owned')
{
	echo sprintf($header,_('owned'));
	$st_caches=$cachetools->db->prepare('SELECT * FROM attributes_caches,geocaches WHERE attributes_caches.gccode=geocaches.GCCode AND attribute_key=? AND attribute_value=? AND OwnerName=?');
}
elseif($_GET['type']=='found')
{
	echo sprintf($header,_('found'));
	$st_caches=$cachetools->db->prepare("SELECT * FROM attributes_caches,geocaches,logs WHERE attributes_caches.gccode=geocaches.GCCode AND geocaches.Guid=logs.CacheGuid AND (LogType='Found it' OR LogType='Attended') AND attribute_key=? AND attribute_value=? AND UserName=?");
}
else
	die();
$caches=$cachetools->execute($st_caches,array($_GET['attribute'],$_GET['state'],$_GET['user']),'all');

$fields=array('GCCode','Name','PlacedBy','CacheType','Country','State','PlacedDate','LastFoundDate','Status','FavPoints','Container','Difficulty','Terrain','NumberOfLogs','Guid');
echo $cachetools_web->cachelist($caches,$fields);

?>
</body>
</html>