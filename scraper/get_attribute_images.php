<?Php
require '../class_cachetools.php';
$cachetools=new cachetools;
$st_attributes=$cachetools->query("SELECT attribute_key,allow_no FROM attributes",false);
$attributes=$st_attributes->fetchAll(PDO::FETCH_KEY_PAIR);

foreach($attributes as $attribute_key=>$allow_no)
{
	$values=array('','-no','-yes');
	foreach($values as $value)
	{
		if($value=='-no' && $allow_no===0)
			continue;
		if(!file_exists("../web/images/attributes/$attribute_key$value.gif"))
			copy("http://www.geocaching.com/images/attributes/$attribute_key$value.gif","../web/images/attributes/$attribute_key$value.gif");
	}
}