<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Get info</title>
</head>

<body>
<h1>Get caches and logs</h1>
<form method="post">
		<p>
				<label for="user">User name:</label>
				<input type="text" name="user" id="user">
		</p>
		<p>
				<label>
						<input type="radio" name="mode" value="found" id="mode_0">
						Found by user</label>
				<br>
				<label>
						<input type="radio" name="mode" value="owned" id="mode_1">
						Owned by user</label>
				<br>
		</p>
		<p>
				<input type="checkbox" name="refresh" id="refresh">
				<label for="refresh">Reload logs alredy in database</label>
		</p>
		<p>
				<input type="submit" name="submit" id="submit" value="Submit">
		</p>
</form>
<?Php
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
	var_dump($job_id);
	echo "<p>$cmd</p>";
}

?>
</body>
</html>