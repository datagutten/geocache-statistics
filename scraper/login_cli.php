<?Php
require 'class_geocaching_com.php';
$gc=new geocaching_com;

$username=$gc->login($argv[1],$argv[2]);
if($username===false)
	echo "Login failed\n";
else
	echo "Logged in as $username\n";