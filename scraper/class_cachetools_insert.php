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
	public $GCCode_guid=false;
	
	public $st_insert_log;
	public $st_update_count;
	private $options;
	
	function __construct($options=false)
	{
		parent::__construct();
		$this->gc=new geocaching_com;
		
		$this->st_insert_log=$this->db->prepare("INSERT INTO logs VALUES(:LogID,:CacheID,:CacheGuid,:LogGuid,:Latitude,:Longitude,:LatLonString,:LogType,:LogTypeImage,:LogText,:Created,:Visited,:UserName,:MembershipLevel,:AccountID,:AccountGuid,:Email,:AvatarImage,:GeocacheFindCount,:GeocacheHideCount,:ChallengesCompleted,:IsEncoded,:has_images)");
		$this->st_update_count=$this->db->prepare("UPDATE geocaches SET NumberOfLogs=? WHERE guid=?");
		if(!isset($options['guid']))
		{
			$st_GCCode_guid=$this->GCCode_guid=$this->query("SELECT Guid,GCCode FROM geocaches",false);
			$this->GCCode_guid=$st_GCCode_guid->fetchAll(PDO::FETCH_KEY_PAIR);
		}
		$this->options=$options;
	}
	function cache_gccode_to_guid($GCCode)
	{
		return array_search($GCCode,$this->GCCode_guid);
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
	function getlogs($guid)
	{
		$starttime=microtime(true);
		$logs=$this->gc->logbook($guid,100,1,$page_info); //Get first page of logs

		$logcount=0;

		for($page=1; $page<=$page_info['totalPages']; $page++)
		{
			if($page>1)
				$logs=$this->gc->logbook($guid,100,$page); //Fetch more logs

			echo sprintf("Elapsed time: %s page %s\n",round(microtime(true)-$starttime,3),$page);
			foreach($logs as $log)
			{
				if($this->log_indb($log['LogID']))
				{
					if(!isset($this->options['refresh']))
					{
						echo "Log in DB\n";
						return $logcount;
					}
					else
						$this->query("DELETE FROM logs WHERE LogId=".$this->db->quote($log['LogID']),false);
					//continue;
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
	function cache_finds_count($guid) //Update caches table with find counts
	{	
		$table='geocaches';
		$guid=$this->db->quote($guid);
		$last_found=$this->query($q="SELECT Visited FROM logs WHERE LogType='Found it' AND CacheGuid=$guid ORDER BY Visited DESC",'single');
		$last_log=$this->query($q="SELECT Visited FROM logs WHERE CacheGuid=$guid ORDER BY Visited DESC",'single');
		$NumberOfFinds=$this->query("SELECT count(LogID) FROM logs WHERE CacheGuid=$guid AND LogType='Found it'",'single');
		$NumberOfLogs=$this->query("SELECT count(LogID) FROM logs WHERE CacheGuid=$guid",'single');
		return $this->query("UPDATE $table SET NumberOfLogs=$NumberOfLogs,LastLog='$last_log',LastFoundDate='$last_found',NumberOfFinds='$NumberOfFinds' WHERE Guid=$guid");
	}
}

function convert($size)
{
    $unit=array('b','kb','mb','gb','tb','pb');
    return @round($size/pow(1024,($i=floor(log($size,1024)))),2).' '.$unit[$i];
}
