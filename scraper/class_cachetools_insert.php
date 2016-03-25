<?php
require_once dirname(__FILE__).'/../class_cachetools.php';
//require_once '../class_cachetools.php';
require_once 'class_geocaching_com.php';

//A class to insert scraped data into the DB
class cachetools_insert extends cachetools
{
	private $caches_indb=false;
	private $logs_indb=false;
	public $gc;
	private $GCCode_guid=array();
	
	private $st_gccode_to_guid;
	private $st_insert_log;
	public $st_update_count;
	private $options;
	
	function __construct($options=false)
	{
		parent::__construct();
		$this->gc=new geocaching_com;
		if(isset($options['preload_gccode_to_guid']))
		{
			$st_GCCode_guid=$this->GCCode_guid=$this->query("SELECT Guid,GCCode FROM geocaches",false);
			$this->GCCode_guid=$st_GCCode_guid->fetchAll(PDO::FETCH_KEY_PAIR);
		}
		$this->options=$options;
	}
	function cache_gccode_to_guid($GCCode)
	{
		$guid=array_search($GCCode,$this->GCCode_guid);
		if($guid===false)
		{
			if(empty($this->st_gccode_to_guid))
				$this->st_gccode_to_guid=$this->db->prepare('SELECT Guid from geocaches WHERE GCCode=?');
			$guid=$this->execute($this->st_gccode_to_guid,array($GCCode),'single');
		}
		return $guid;
	}
	function log_indb($LogID)
	{
		if($this->logs_indb===false)
			$this->logs_indb=$this->query("SELECT LogID FROM logs",'all_column');
		if(array_search($LogID,$this->logs_indb)===false)
			return false;
		else
			return true;
	}
	function cache_indb($GCCode)
	{
		if($this->caches_indb===false)
			$this->caches_indb=$this->query("SELECT GCCode FROM geocaches",'all_column');
		//print_r($this->caches_indb);
		if(array_search($GCCode,$this->caches_indb)===false)
			return false;
		else
			return true;
	}
	function insert_cache($data)
	{
		$cache=$this->gc->cacheinfo($data);
		if($cache===false)
		{
			trigger_error("Error fetching cache",E_USER_WARNING);
			continue;
		}
		if(empty($cache))
			continue;

		$fields=implode('`,`',array_keys($cache));

		$q="INSERT INTO geocaches (`$fields`) VALUES(";
	
		if(!isset($st_insert_cache)) //Prepare the query
		{
			foreach(array_keys($cache) as $fieldkey) //array keys are used as DB fields
				$q.=":$fieldkey,";
			$q=substr($q,0,-1).')';
			$st_insert_cache=$this->db->prepare($q);
		}
		foreach(array_keys($cache) as $fieldkey)
			$st_insert_cache->bindValue(':'.$fieldkey,$cache[$fieldkey]); //Bind the input values

		$this->execute($st_insert_cache,NULL);
		$this->caches_indb[]=$cache['GCCode'];
		$this->GCCode_guid[$cache['guid']]=$cache['GCCode'];
	}
	function attributes($GCcode,$data)
	{
		$attributes=$this->gc->attributes($data);

		if($attributes!==false)
		{
			$this->db->query("DELETE FROM attributes_caches WHERE gccode=".$this->db->quote($GCcode));
			$st_insert_attribute=$this->db->prepare("INSERT INTO attributes_caches (gccode,attribute_key,attribute_value,uid) VALUES (?,?,?,?)");
			//print_r($attributes);
			foreach($attributes as $key=>$attribute)
			{
				//$st_insert_attribute->execute(array($GCcode,$key,$attribute));
				$this->execute($st_insert_attribute,array($GCcode,$key,$attribute,$GCcode.$key));
			}
		}
	}
	function getlogs($guid,$refetch=false)
	{
		$starttime=microtime(true);
		//Prepare query to insert log
		if(empty($this->st_insert_log))
			$this->st_insert_log=$this->db->prepare("INSERT INTO logs VALUES(:LogID,:CacheID,:CacheGuid,:LogGuid,:Latitude,:Longitude,:LatLonString,:LogType,:LogTypeImage,:LogText,:Created,:Visited,:UserName,:MembershipLevel,:AccountID,:AccountGuid,:Email,:AvatarImage,:GeocacheFindCount,:GeocacheHideCount,:ChallengesCompleted,:IsEncoded,:has_images)");
		$this->st_update_count=$this->db->prepare("UPDATE geocaches SET NumberOfLogs=? WHERE guid=?");
		$logs=$this->gc->logbook($guid,100,1,$page_info); //Get first page of logs

		$logcount=0;
		if(!$refetch)
		{
			$st_logs_indb=$this->db->prepare('SELECT LogID FROM logs WHERE CacheGuid=?');
			$logs_indb=$this->execute($st_logs_indb,array($guid),'all_column');
		}
		else
		{
			$st_logs_indb=$this->db->prepare("DELETE FROM logs WHERE CacheGuid=".$this->db->quote($log['LogID']));
			$this->execute($st_logs_indb,array($guid));
		}
		for($page=1; $page<=$page_info['totalPages']; $page++)
		{
			if($page>1)
				$logs=$this->gc->logbook($guid,100,$page); //Fetch more logs

			//echo sprintf("Elapsed time: %s page %s\n",round(microtime(true)-$starttime,3),$page);

			foreach($logs as $log)
			{
				if(isset($logs_indb) && array_search($log['LogID'],$logs_indb)!==false)
				{
					//echo "Log {$log['LogID']} is already in DB\n";
					continue;
				}

				if(!empty($log['Images']))
					$log['has_images']=1;
				else
					$log['has_images']=0;
				unset($log['creator'],$log['Images']);
				$log['CacheGuid']=$guid;
				//print_r($log);
				foreach($log as $key=>$value)
				{
					if($key=='Visited' || $key=='Created')
						$value=date('Y-m-d',strtotime($value));
					$this->st_insert_log->bindValue(':'.$key,$value);
				}

				$starttime=microtime(true);
				$this->execute($this->st_insert_log,NULL);
				echo sprintf("Log inserted in: %s\n",round(microtime(true)-$starttime,3));
				$this->logs_indb[]=$log['LogID'];

				$logcount++;
			}
		}
		$this->cache_finds_count($guid);
		return $logcount;
	}

	//Update caches table with find and log information
	public function cache_finds_count($guid)
	{	
		$guid=$this->db->quote($guid);

		$st_update_count=$this->db->prepare("UPDATE geocaches SET LastFoundDate=?,LastLog=?,NumberOfFinds=?,NumberOfLogs=? WHERE guid=?");

		//Get cache last found date
		$LastFoundDate=$this->query($q="SELECT Visited FROM logs WHERE LogType='Found it' AND CacheGuid=$guid ORDER BY Visited DESC",'single');
		//Get cache last log date
		$LastLogDate=$this->query($q="SELECT Visited FROM logs WHERE CacheGuid=$guid ORDER BY Visited DESC",'single');
		//Get number of cache finds
		$NumberOfFinds=$this->query("SELECT count(LogID) FROM logs WHERE CacheGuid=$guid AND LogType='Found it'",'single');
		//Get total number of logs
		$NumberOfLogs=$this->query("SELECT count(LogID) FROM logs WHERE CacheGuid=$guid",'single');

		return $st_update_count->execute(array($LastFoundDate,$LastLogDate,$NumberOfFinds,$NumberOfLogs,$guid));
	}
}

function convert($size)
{
    $unit=array('b','kb','mb','gb','tb','pb');
    return @round($size/pow(1024,($i=floor(log($size,1024)))),2).' '.$unit[$i];
}
