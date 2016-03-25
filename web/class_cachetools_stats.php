<?php
require '../class_cachetools.php';
class cachetools_stats extends cachetools
{
	public $st_attributes;
	public $query_found_logs="SELECT %s FROM geocaches,logs WHERE geocaches.Guid=logs.CacheGuid AND (LogType='Found it' OR LogType='Attended')";
	function __construct()
	{
		parent::__construct();
		$this->set_locale('nb_NO.utf8','web');
	}
	function number_query($q)
	{
		$st=$this->db->query($q);
		return $st->fetch(PDO::FETCH_COLUMN);
	}

	function unique_find_hides($OwnerName)
	{
		$OwnerName=$this->db->quote($OwnerName);
		return $this->number_query("SELECT count(distinct UserName) FROM logs,geocaches WHERE geocaches.OwnerName=$OwnerName AND logs.CacheId=geocaches.CacheID AND LogType='Found it'");
	}
	function total_finds($OwnerName) //Total finds for caches owned by a user
	{
		$OwnerName=$this->db->quote($OwnerName);
		return $this->number_query("SELECT count(UserName) FROM logs,geocaches WHERE geocaches.OwnerName=$OwnerName AND logs.CacheId=geocaches.CacheID AND LogType='Found it'");
	}
	function caches_by_owner($OwnerName)
	{
		$OwnerName=$this->db->quote($OwnerName);
		return $this->query("SELECT * FROM geocaches WHERE OwnerName=$OwnerName");
	}
	function top_finders($OwnerName) //Top finders of caches owned by a user
	{
		$OwnerName=$this->db->quote($OwnerName);
		return $this->query("SELECT UserName, COUNT(*) AS CachesFound FROM logs,geocaches WHERE geocaches.OwnerName=$OwnerName AND logs.CacheId=geocaches.CacheID AND LogType='Found it' GROUP BY UserName ORDER BY CachesFound DESC");
	}
	function user_finds_by_owner($UserName,$OwnerName,$LogType='Found it',$LogType_not=false)
	{
		$UserName=$this->db->quote($UserName);
		$OwnerName=$this->db->quote($OwnerName);
		$LogType=$this->db->quote($LogType);
		
		if($LogType_not)
			$LogType_comparator='!=';
		else
			$LogType_comparator='=';
		return $this->query("SELECT * FROM logs,geocaches WHERE geocaches.OwnerName=$OwnerName AND logs.UserName=$UserName AND logs.CacheId=geocaches.CacheID AND LogType$LogType_comparator$LogType ORDER BY Name");
	}
	function favorite_points($OwnerName)
	{
		return $this->query("SELECT sum(FavPoints) FROM  geocaches WHERE OwnerName=".$this->db->quote($OwnerName),'single');
	}
	function states_caches_found($UserName)
	{
		$UserName=$this->db->quote($UserName);
		return $this->query("SELECT distinct State FROM geocaches,logs WHERE geocaches.Guid=logs.CacheGuid AND UserName=".$UserName);
	}
	function cache_finds($UserName,$user_owner='found',$dates=false) //Get all finds on caches owned or found by a user
	{
		$UserName=$this->db->quote($UserName);
		if($user_owner=='found')
			$user_field='UserName'; //Caches found by user
		elseif($user_owner=='owned')
			$user_field='OwnerName'; //Caches owned by user
		else
			throw new Exception("user_owner must be found or owned");

		if($dates===false)
		{
			$fields='*'; //Get all logs
			$fetch='all';
		}
		else
		{
			$fields='distinct Visited'; //Get unique find dates
			$fetch='all_column';
		}
		
		return $this->query("SELECT $fields FROM geocaches,logs WHERE geocaches.Guid=logs.CacheGuid AND $user_field=$UserName AND (LogType='Found it' OR LogType='Attended') ORDER BY Visited,LogId",$fetch);
	}
	function attributes($GCcode)
	{
		if(empty($this->st_attributes))
			$this->st_attributes=$this->db->prepare("SELECT attribute_key,attribute_value FROM attributes_caches WHERE gccode=?");
		//$this->st_attributes->execute(array($GCcode));
		$this->execute($this->st_attributes,array($GCcode));
		return $this->st_attributes->fetchAll(PDO::FETCH_KEY_PAIR);	
	}
	function attribute_count($user,$mode='owned')
	{
		if($mode=='owned')
			$caches=$this->caches_by_owner($user);
		elseif($mode=='found')
			$caches=$this->cache_finds($user,$mode);

		foreach($caches as $cache)
		{
			$attributes_cache=$this->attributes($cache['GCCode']);
			foreach($attributes_cache as $attribute_key=>$attribute_value)
			{
				//$attributes_caches[$attribute_key][$cache['GCCode']]=$attribute_value;
				if(isset($attribute_count[$attribute_value][$attribute_key]))
					$attribute_count[$attribute_value][$attribute_key]++;
				else
					$attribute_count[$attribute_value][$attribute_key]=1;
			}
		}
		return $attribute_count;
	}
	function dt($user,$mode='owned')
	{
		$user_db=$this->db->quote($user);
		$baseq='SELECT %1$s,count(%1$s) FROM geocaches%2$s WHERE %3$s GROUP BY %1$s';
		if($mode=='found')
		{
			$baseq="SELECT Difficulty,Terrain,count(*) as count FROM geocaches,logs WHERE geocaches.Guid=logs.CacheGuid AND (LogType='Found it' OR LogType='Attended') AND logs.UserName=%s GROUP BY Difficulty,Terrain";
			return $this->query(sprintf($baseq,$user_db),'all');
		}
		elseif($mode=='owned')
		{
			$baseq="SELECT Difficulty,Terrain,count(*) as count FROM geocaches WHERE OwnerName=%s GROUP BY Difficulty,Terrain";
			$q_owner=sprintf($baseq,'Difficulty','','OwnerName=?');
		}
		//$q_found=sprintf($baseq,'Difficulty',',logs','logs.UserName=? AND logs.CacheId=geocaches.CacheID');
		$baseq_found=sprintf($this->query_found_logs,'%1$s,count(%1$s)').' AND logs.UserName=? GROUP BY %1$s';
		echo $baseq_found."<br />\n";
		echo sprintf($baseq_found,'Difficulty');
		//echo $q_found;
	}
	function difficulty_sum()
	{
		$q="SELECT Difficulty,count(*) as count FROM geocaches WHERE OwnerName='datagutten' GROUP BY Difficulty";
	}
	function total_days($cache_finds_date)
	{
		$first=array_shift($cache_finds_date);
		$last=array_pop($cache_finds_date);
		$diff_seconds=strtotime($last)-strtotime($first);
		return $diff_seconds/60/60/24+1;
	}
	function total_months($cache_finds_date)
	{
		$first=array_shift($cache_finds_date);
		$last=array_pop($cache_finds_date);
		$diff_seconds=strtotime($last)-strtotime($first);
		return $diff_seconds/60/60/24+1;
	}
	function caches_per_month($cache_finds_date,$average=true)
	{
		return 0;
		foreach($cache_finds_date as $date)
		{
			$month=substr($date,0,7);
			if(!isset($count[$month]))
				$count[$month]=1;
			else
				$count[$month]++;
		}
		foreach($count as $month=>$month_count)
		{
			$days_month=cal_days_in_month(CAL_GREGORIAN,substr($month,5,2),substr($month,0,2));
			$average_month[$month]=$month_count/$days_month;
		}
		print_r($count);
		var_dump(array_sum($average_month));
		var_Dump(array_sum($count).'/'.count($count));
		if($average===false)
			return $count;
		elseif($average===true)
			return round(array_sum($count)/count($count),2);
	}
}