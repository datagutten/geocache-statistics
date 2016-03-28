<?Php
require 'class_cachetools_web.php';
$cachetools_web=new cachetools_web;
$dom=$cachetools_web->dom;
require 'class_cachetools_stats.php';
$cachetools=new cachetools_stats;


if(isset($_GET['user']))
	$user=$_GET['user'];
else
	$user=_('user');
if(isset($_GET['mode']))
	$mode=$_GET['mode'];
else
	$mode='';

$title=sprintf(_('Difficulty and terrain on caches %s by %s'),$mode,$user);

?>
<!DOCTYPE HTML>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title><?Php echo $title ?></title>
</head>

<?php
$body=$dom->createElement('body');
$dom->createElement_simple('h1',$body,false,$title);
$form=$dom->createElement_simple('form',$body,array('method'=>'get'));

$p=$dom->createElement_simple('p',$form);
$dom->createElement_simple('label',$p,array('for'=>'user'),_('User name').': ');
$dom->createElement_simple('input',$p,array('type'=>'text','name'=>'user','id'=>'user'));

$p=$dom->createElement_simple('p',$form);
$input=$dom->createElement_simple('input',$p,array('type'=>'radio','name'=>'mode','value'=>'found','id'=>'mode_0'));
$label=$dom->createElement_simple('span',$p,false,_('Found by user'));

$input=$dom->createElement_simple('input',$p,array('type'=>'radio','name'=>'mode','value'=>'owned','id'=>'mode_1'));
$label=$dom->createElement_simple('span',$p,false,_('Owned by user'));

$p=$dom->createElement_simple('p',$form);
$dom->createElement_simple('input',$p,array('type'=>'submit','value'=>_('Show')));
if(isset($_GET['mode']))
{
	if($_GET['mode']=='found')
		$st_caches=$cachetools->db->prepare("SELECT Difficulty,Terrain,count(*) as count FROM geocaches,logs WHERE geocaches.Guid=logs.CacheGuid AND (LogType='Found it' OR LogType='Attended') AND logs.UserName=? GROUP BY Difficulty,Terrain");
	elseif($_GET['mode']=='owned')
		$st_caches=$cachetools->db->prepare('SELECT Difficulty,Terrain,count(*) as count FROM geocaches WHERE OwnerName=? GROUP BY Difficulty,Terrain');
	else
	{
		echo _('Invalid mode');
		unset($_GET['user']);
	}
}
if(isset($_GET['user']))
{
	$table=$dom->createElement_simple('table',$body);
	$table->setAttribute('border',1);
	$difficulties=range(1,5,0.5);

	$range=array_combine(range(1,5,0.5),array_fill(0,9,0));

	array_unshift($difficulties,'top');
	$difficulties[]='bottom';
	$difficulty_sum=$range;
	$terrain_sum=$range;

	$cachetools->execute($st_caches,array($_GET['user']));

	while($cache=$st_caches->fetch(PDO::FETCH_ASSOC))
	{
		$dt_combos[$cache['Difficulty']][$cache['Terrain']]=$cache['count'];
		$difficulty_sum[$cache['Difficulty']]+=$cache['count'];
		$terrain_sum[$cache['Terrain']]+=$cache['count'];
	}
	foreach(array_merge(array('top'),range(1,5,0.5),array('bottom')) as $difficulty)
	{
		$difficulty=(string)$difficulty; //Float cannot be used in array keys, convert to string
		$tr=$cachetools_web->dom->createElement_simple('tr',$table);
		foreach(array_merge(array('left'),range(1,5,0.5),array('right')) as $terrain)
		{
			$terrain=(string)$terrain; //Float cannot be used in array keys, convert to string
			if($terrain=='left' && is_numeric($difficulty))
				$td=$cachetools_web->dom->createElement_simple('th',$tr,'','D'.str_pad($difficulty,3,'.0')); //Difficulty header (left)
			elseif($terrain=='right' && is_numeric($difficulty))
				$td_sum_difficulty[$difficulty]=$cachetools_web->dom->createElement_simple('th',$tr,'',$difficulty_sum[$difficulty]); //Difficulty sum (right)
			elseif($difficulty=='top' && is_numeric($terrain))
				$td=$cachetools_web->dom->createElement_simple('th',$tr,'','T'.str_pad($terrain,3,'.0')); //Terrain header (top)
			elseif($difficulty=='bottom' && is_numeric($terrain))
				$cachetools_web->dom->createElement_simple('th',$tr,'',$terrain_sum[(string)$terrain]); //Terrain sum (bottom)
			elseif($difficulty=='bottom' && $terrain=='right')
				$cachetools_web->dom->createElement_simple('th',$tr,'',array_sum($terrain_sum));
			elseif(is_numeric($difficulty) && is_numeric($terrain))
			{
				if(isset($dt_combos[$difficulty][$terrain]))
					$cachetools_web->dom->createElement_simple('td',$tr,array('id'=>"D{$difficulty}T$terrain"),$dt_combos[$difficulty][$terrain]);
				else
					$cachetools_web->dom->createElement_simple('td',$tr,array('id'=>"D{$difficulty}T$terrain"));
			}
			else
				$td=$cachetools_web->dom->createElement_simple('td',$tr);
		}
	}
}

echo $cachetools_web->dom->saveXML($body);
?>
</body>
</html>