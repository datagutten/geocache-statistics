<?Php
require 'class_cachetools_stats.php';
$cachetools=new cachetools_stats;
require 'class_cachetools_web.php';
$web=new cachetools_web;
require_once '../tools/DOMDocument_createElement_simple.php';
$dom=new DOMDocumentCustom;
$dom->formatOutput=true;
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title><?Php echo _('CSV logbook'); ?></title>
</head>

<?Php
$body=$dom->createElement('body');
$form=$dom->createElement_simple('form',$body,array('method'=>'post'));
$p=$dom->createElement_simple('p',$form,false,_('Owner name').': ');
$dom->createElement_simple('input',$p,array('type'=>'text','name'=>'owner'));
$p=$dom->createElement_simple('p',$form,false,_('GC code').': ');
$dom->createElement_simple('input',$p,array('type'=>'text','name'=>'gccode'));

$dom->createElement_simple('input',$form,array('type'=>'submit','name'=>'submit','value'=>_('Create CSV')));

if(isset($_POST['submit']))
{
	if(!empty($_POST['owner']))
	{
		$st_caches_by_owner=$cachetools->db->prepare("SELECT GCCode,Guid FROM geocaches WHERE OwnerName=?");
		$caches=$cachetools->execute($st_caches_by_owner,array($argument=$_POST['owner']),'all');
	}
	elseif(!empty($_POST['gccode']))
	{
		$st_gccode_to_guid=$cachetools->db->prepare('SELECT GCCode,Guid from geocaches WHERE GCCode=?');
		$caches=$cachetools->execute($st_gccode_to_guid,array($argument=$_POST['gccode']),'all');
	}
	else
		die('No valid argument');
	if(!empty($caches))
	{
		$st_get_logs=$cachetools->db->prepare("SELECT UserName,Visited,LogText FROM logs WHERE LogType='Found it' AND CacheGuid=?");
		foreach($caches as $cache)
		{
			$dir='Logs '.$argument;
			if(!file_exists($dir))
				mkdir($dir);
			$fp=fopen(sprintf('%s/Logs %s %s.csv',$dir,$cache['GCCode'],date('Y-m-d')),'w+');
			if($fp===false)
				break;
			$cachetools->execute($st_get_logs,array($cache['Guid']));
			fputcsv($fp,array('Username','Visited','Text'),';');
			while($row=$st_get_logs->fetch(PDO::FETCH_ASSOC))
			{
				foreach($row as $field=>$value)
					$row[$field]=utf8_decode($value);
				fputcsv($fp,array($row['UserName'],$row['Visited'],strip_tags($row['LogText'])),';');
			}
			fclose($fp);
		}
		$zipfile=sprintf('Logs %s.zip',$argument);
		shell_exec(sprintf('zip -m -o -r "%s" "%s"',$zipfile,$dir));
		$p=$dom->createElement_simple('p',$body);
		if(file_exists($zipfile))
			$dom->createElement_simple('a',$p,array('href'=>$zipfile),_('Download zip file with logs'));
		else
			$dom->createElement_simple('span',$p,false,_('Error creating zip'));
	}
}
echo $dom->saveXML($body);

?>
</html>