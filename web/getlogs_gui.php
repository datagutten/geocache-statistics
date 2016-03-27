<?Php
require 'class_cachetools_stats.php';
$cachetools=new cachetools_stats;
require_once '../tools/DOMDocument_createElement_simple.php';
$dom=new DOMDocumentCustom;
$dom->formatOutput=true;

?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title><?Php echo _('Get caches and logs'); ?></title>
</head>
<?php
$body=$dom->createElement('body');
$dom->createElement_simple('h1',$body,false,_('Get caches and logs'));
$form=$dom->createElement_simple('form',$body,array('method'=>'post'));

$p=$dom->createElement_simple('p',$form);
$dom->createElement_simple('label',$p,array('for'=>'user'),_('User name'));
$dom->createElement_simple('input',$p,array('type'=>'text','name'=>'user','id'=>'user'));

$p=$dom->createElement_simple('p',$form);
$input=$dom->createElement_simple('input',$p,array('type'=>'radio','name'=>'mode','value'=>'found','id'=>'mode_0'));
$label=$dom->createElement_simple('span',$p,false,_('Found by user'));

$input=$dom->createElement_simple('input',$p,array('type'=>'radio','name'=>'mode','value'=>'owned','id'=>'mode_1'));
$label=$dom->createElement_simple('span',$p,false,_('Owned by user'));

$p=$dom->createElement_simple('p',$form);
$dom->createElement_simple('input',$p,array('type'=>'checkbox','name'=>'refresh'));
$label=$dom->createElement_simple('span',$p,false,_('Reload logs already in database'));

$p=$dom->createElement_simple('p',$form);
$dom->createElement_simple('input',$p,array('type'=>'submit','name'=>'submit','value'=>_('Add to job queue')));

if(isset($_POST['submit']))
{
	//require '../../queuemanager/class_queuemanager.php';
	require dirname(__FILE__).'/../queuemanager/class_queuemanager.php';
	$queue=new queuemanager(realpath(dirname(__FILE__).'/../scraper'));
	$queue->init('cachetools');
	$base_cmd='php cacheinfo.php --getlogs --logqueue --cachequeue';
	if($_POST['mode']=='found')
		$cmd=sprintf($base_cmd.' --user=%s',$_POST['user']);
	elseif($_POST['mode']=='owned')
		$cmd=sprintf($base_cmd.' --owner=%s',$_POST['user']);
	if(isset($_POST['refresh']))
		$cmd.=' --refresh';
	$job_id=$queue->add_job($cmd,'Added from getlogs_gui.php');
	//var_dump($job_id);
	//echo "<p>$cmd</p>";
}
echo $dom->saveXML($body);
?>
</body>
</html>