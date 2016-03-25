<?Php
require 'class_geocaching_com.php';
$gc=new geocaching_com;

$data=$gc->get('http://www.geocaching.com');
if($username=$gc->is_logged_in($data))
	echo "Already logged in as $username\n";
else
{
	$username=$gc->login($argv[1],$argv[2]);
	if($username===false)
		echo "Login failed\n";
	else
		echo "Logged in as $username\n";
}