<?Php
require 'class_cachetools_stats.php';
$cachetools=new cachetools_stats;
require 'class_cachetools_web.php';
$cachetools_web=new cachetools_web;
require_once '../tools/DOMDocument_createElement_simple.php';
$dom=new DOMDocumentCustom;

if(isset($_GET['user']))
	$user=$_GET['user'];
else
	$user=_('user');
?>
<!DOCTYPE HTML>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title><?Php echo $title=sprintf(_('Attributes on caches found or owned by %s'),$user); ?></title>
</head>

<?Php
$body=$dom->createElement('body');
$dom->createElement_simple('h1',$body,false,$title);
if(!isset($_GET['user']))
{
	$form=$dom->createElement_simple('form',$body,array('method'=>'get'));
	$p=$dom->createElement_simple('p',$form,false,_('User name').': ');
	$dom->createElement_simple('input',$p,array('type'=>'text','name'=>'user'));
	$dom->createElement_simple('input',$form,array('type'=>'submit','value'=>_('Show attributes')));
	echo $dom->saveXML($body);
}
else
{
	$header_template=_("%s attributes on caches %s by %s");
	foreach(array('found'=>_('found'),'owned'=>_('owned')) as $type=>$word_type)
	{
		$attribute_count=$cachetools->attribute_count($user,$type);
		foreach(array('yes'=>_('Positive'),'no'=>_('Negative')) as $state=>$word_state)
		{
			echo '<h2>'.sprintf($header_template,$word_state,$word_type,$user).'</h2>';

			$link="caches_with_attribute.php?attribute=%s&state=$state&user=$user&type=$type";
			echo $cachetools_web->attribute_matrix($attribute_count,$state,$link);
		}
	}
}
?>
</html>
