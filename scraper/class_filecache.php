<?Php
class filecache
{
	public $cachedir;
	public $max_age=259200; //3 days
	public $filename_template;
	
	function __construct()
	{
		$this->cachedir=dirname(__FILE__).'/cache';	
		$this->filename_template=$this->cachedir.'/%s_time%s';
	}
	function write($name,$data)
	{
		echo "Write $name\n";
		file_put_contents($this->cachedir.'/'.$name.'_time'.time(),$data);
	}
	function read($name)
	{
		//echo "Read $name\n";
		$file=glob($this->cachedir.'/'.$name.'_time*');

		if(empty($file))
			return false;
		preg_match('/_time([0-9]+)/',$file[0],$time);
		if(time()-$time[1]>$this->max_age)
		{
			unlink($file[0]);
			return false;	
		}
		return file_get_contents($file[0]);
	}
	function clear($name)
	{
		echo "Clear $name\n";
		$file=glob($this->cachedir.'/'.$name.'*');
		unlink($file[0]);
	}
}

/*$cache=new filecache;
$cache->max_age=2;
//$cache->write('test','testdata');
var_dump($cache->read('test'));*/