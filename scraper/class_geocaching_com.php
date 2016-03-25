<?Php
//This class does the scraping from geocaching.com
//No database handling in this class
require_once 'class_filecache.php';
class geocaching_com
{
	public $ch;
	public $lang;
	public $filecache;
	function __construct()
	{
		$this->init();
	}
	function init()
	{
		$this->ch=curl_init();
		curl_setopt($this->ch,CURLOPT_RETURNTRANSFER,true);
		curl_setopt($this->ch,CURLOPT_FOLLOWLOCATION,true);
		
		curl_setopt($this->ch,CURLOPT_COOKIEFILE,'cookies_geocaching_com.txt'); //Read cookies
		$this->filecache=new filecache; //Class used to cache data from geocaching.com
		//$this->set_locale('nb_NO.utf8','scraper');
		//echo _("You are using geocaching.com in English")."\n";
		
	}
	function get($url)
	{
		curl_setopt($this->ch,CURLOPT_URL,$url);
		$result=curl_exec($this->ch);
		if($result===false)
			throw new Exception("cURL error: ".curl_error($this->ch));
		
		return $result;
	}
	public function set_locale($locale,$domain)
	{
		$this->locale_path=dirname(__FILE__).'/../locale';
		if(!file_exists($file=$this->locale_path."/$locale/LC_MESSAGES/$domain.mo"))
		{
			trigger_error(sprintf("No translation found for locale %s. It should be placed in %s",$locale,$file));
			return false;
		}
		putenv('LC_ALL='.$locale);
		putenv("LANGUAGE=".$locale);
		setlocale(LC_ALL,$locale);
		//bindtextdomain($domain,$this->locale_path.'/nocache');
		//Specify location of translation tables
		bindtextdomain($domain,$this->locale_path);
		//Choose domain
		textdomain($domain);

		$this->lang=preg_replace('/([a-z]+)_.+/','$1',$locale); //Get the language from the locale
	}
	function check_locale($data=false)
	{
		if($data===false)
			$data=$this->get('https://www.geocaching.com/');
		if(strpos($data,_('How to Go Geocaching'))===false)
			return false;
		else
			return true;
	}
	function get_viewstates($data) //read all viewstates from page (Patterns from C:geo)
	{
		if(empty($data))
			throw new Exception("Empty data");
        // Get the number of viewstates.
        // If there is only one viewstate, __VIEWSTATEFIELDCOUNT is not present
		if(preg_match("#id=\"__VIEWSTATEFIELDCOUNT\"[^(value)]+value=\"(\\d+)\"[^>]+>#si",$data,$viewstatefieldcount))
			$fields['__VIEWSTATEFIELDCOUNT']=$viewstatefieldcount[1];
		preg_match_all("#id=\"__VIEWSTATE(\\d*)\"[^(value)]+value=\"([^\"]+)\"[^>]+>#si",$data,$viewstates);

		foreach($viewstates[2] as $key=>$viewstate)
		{
			$fields['__VIEWSTATE'.$viewstates[1][$key]]=$viewstate;
		}
		if(!isset($fields))
		{
			file_put_contents('check.htm',$data);
			die();
		}
		return $fields;
	}
	
	function login($username,$password)
	{
		$data=$this->get($url='https://www.geocaching.com/login/default.aspx'); //Get the login page
		curl_setopt($this->ch,CURLOPT_COOKIEJAR,'cookies_geocaching_com.txt'); //Save cookies
		preg_match("#id=\"__VIEWSTATE(\\d*)\"[^(value)]+value=\"([^\"]+)\"[^>]+>#si",$data,$viewstate); //Pattern from C:Geo
		$postfields=array(	'__EVENTTARGET'=>'',
							'__EVENTARGUMENT'=>'',
							'__VIEWSTATE'=>$viewstate[2],
							'ctl00$ContentBody$tbUsername'=>$username,
							'ctl00$ContentBody$tbPassword'=>$password,
							'ctl00$ContentBody$cbRememberMe'=>"on",
							'ctl00$ContentBody$btnSignIn'=>"Login");
		curl_setopt($this->ch,CURLOPT_POSTFIELDS,$postfields);
		$data=curl_exec($this->ch); //The URL from the GET request is also used for POST
		curl_setopt($this->ch,CURLOPT_HTTPGET,true); //Set the method back to get


		return $this->is_logged_in($data);
	}
	function is_logged_in($data)
	{
		if(preg_match("#class=\"li-user-info\"[^>]*>.*?<span>(.*?)</span>#s",$data,$username)) //Pattern from C:Geo
			return $username[1];
		if(strpos($data,"Join now to view geocache location details. It's free!")===false || strpos($data,'data-event-label="Sign In"')===false)
			return false;
		else
			throw new Exception("Could not detect login state");
	}
	function init_check()
	{
		$data=$this->get('https://www.geocaching.com/');
		if(!$this->is_logged_in($data))
			return "Not logged in";
		elseif(!$this->check_locale($data))
			return "Locale mismatch";
	}

	function get_token($data)
	{
		preg_match("/userToken = '(.+)';/",$data,$matches);
		return $matches[1];
	}
	function logbook($guid,$num_logs=10,$page=1,&$page_info=false)
	{
		if(empty($guid) || empty($num_logs)) //Avoid sending bad requests to groundspeak
		{
			throw new Exception("Missing arguments for logbook()");
			return false;
		}
		if($num_logs=='all')
			$num=100;
		elseif(!is_numeric($num_logs))
		{
			throw new Exception("Number of logs must be numeric");
			return false;
		}
		else
			$num=$num_logs;
		$token=$this->get_token($this->get('http://www.geocaching.com/seek/cache_logbook.aspx?guid='.$guid));
		$parameters=array('tkn'=>$token,'idx'=>$page,'num'=>$num,'sp'>='false','sf'=>'false','decrypt'=>'true');
		$url="http://www.geocaching.com/seek/geocache.logbook?".http_build_query($parameters);
		$logs_json=$this->get($url);
		$logs=json_decode($logs_json,true);
		$page_info=$logs['pageInfo'];

		if($logs['status']=='success')
		{
			if($num_logs=='all' && $logs['pageInfo']['totalPages']>1)
			{
				//print_r($logs['pageInfo']);
				$all_logs=$logs['data'];
				for($page=2; $page<=$logs['pageInfo']['totalPages']; $page++)
				{
					$log_page=$this->logbook($guid,100,$page);
					$all_logs=array_merge($all_logs,$log_page);
				}
				return $all_logs;
				//return $this->logbook($guid,$logs['pageInfo']['totalRows']);
			}
			else
				return $logs['data'];
		}
		else
			return $logs;
	}
	function attributes($data) //Get cache attributes
	{
		//$data=$this->get("http://coord.info/$gccode");

		if(!preg_match("#Attributes\\s*</h3>[^<]*<div class=\"WidgetBody\">((?:[^<]*<img src=\"[^\"]+\" alt=\"[^\"]+\"[^>]*>)+?)[^<]*<p#",$data,$attributes_raw))
			return false; //No attributes
		else
		{
			preg_match_all("#[^<]*<img src=\"/images/attributes/(.+?)\-*([no|yes]*)\.gif\" alt=\"([^\"]+?)\"#",$attributes_raw[1],$attributes);
			$attributes=array_combine($attributes[1],$attributes[2]);
			unset($attributes['attribute-blank']);
			return $attributes;
		}
	}
	function cacheinfo($data)
	{
		//https://github.com/cgeo/cgeo/blob/master/main/src/cgeo/geocaching/connector/gc/GCConstants.java
		//$data=$this->get("http://coord.info/$gccode");
		preg_match("#class=\"CoordInfoCode\">(GC[0-9A-Z]+)</span>#",$data,$gccode);
		$gccode=$gccode[1];
		preg_match("/var lat=(-?[0-9\.]+), lng=(-?[0-9\.]+), guid='([a-f0-9\-]+)'/",$data,$baseinfo); //Coordinates and cache GUID
		preg_match("#<span id=\"ctl00_ContentBody_CacheName\">(.*?)</span>#",$data,$name); //Cache name (C:geo)
		preg_match('/'._('A.+?cache by').'.+guid=([a-f0-9\-]+).+\>(.+)\</',$data,$ownerinfo); //Placed by and owner GUID
		preg_match("#/seek/log\\.aspx\\?ID=(\\d+)#",$data,$CacheId); //Cache ID (C:geo)
		preg_match('^images\/WptTypes\/([0-9]+).gif"^',$data,$CacheType);
		preg_match("#<img src=\"/images/icons/container/([^\\.]+)\\.gif\"#",$data,$container); //Container (C:geo)
		
		if(!preg_match("#<div id=\"ctl00_ContentBody_mcd2\">\\W*Hidden[\\s:]*([^<]+?)</div>#",$data,$PlacedDate)) //Hidden date (C:Geo)
			preg_match('#Event\\s*Date\\s*:\\s*([^<]+)<div id=\"calLinks\">#s',$data,$PlacedDate); //Event date (C:Geo)

		if(!preg_match("#<span id=\"ctl00_ContentBody_Location\">"._('In')." (.+), (.+)</span>#",$data,$location))
		{
			preg_match("#<span id=\"ctl00_ContentBody_Location\">"._('In')." (?:<a href=[^>]*>)?(.*?)<#",$data,$location);
			$location[2]=$location[1]; //Contry
			$location[1]=''; //No state
		}
		preg_match("#other caches <a href=\"/seek/nearest\\.aspx\\?u=(.*?)\">"._('hidden')."</a> or#",$data,$owner_name); //C:geo
		preg_match("#<span id=\"ctl00_ContentBody_uxLegendScale\"[^>]*>[^<]*<img src=\"[^\"]*/images/stars/stars([0-9_]+)\\.gif\"#",$data,$difficulty);
		preg_match("#<span id=\"ctl00_ContentBody_Localize[\\d]+\"[^>]*>[^<]*<img src=\"[^\"]*/images/stars/stars([0-9_]+)\\.gif\"#",$data,$terrain);
		preg_match("#<span class=\"favorite-value\">\\D*([0-9]+?)\\D*</span>#",$data,$FavPoints);
		
		if(!isset($FavPoints[1]))
			$FavPoints[1]=false;
		if(strpos($data,'<li>This cache has been archived,')!==false)
			$status='X';
		elseif(strpos($data,'<li>This cache is temporarily unavailable.')!==false)
			$status='T';
		else
			$status='A';

		return array(	'GCCode'=>$gccode,
						'Name'=>$name[1],
						'PlacedBy'=>$ownerinfo[2],
						'CacheId'=>$CacheId[1],
						'CacheType'=>$CacheType[1],
						'Container'=>$container[1],
						'Country'=>$location[2],
						'Difficulty'=>str_replace('_','.',$difficulty[1]),
						'Latitude'=>$baseinfo[1],
						'Longitude'=>$baseinfo[2],
						'OwnerName'=>urldecode($owner_name[1]),
						'PlacedDate'=>date('Y-m-d',strtotime(trim($PlacedDate[1]))),
						'State'=>$location[1],
						'Terrain'=>str_replace('_','.',$terrain[1]),
						'Status'=>$status,
						'FavPoints'=>$FavPoints[1],
						'guid'=>$baseinfo[3],
						'OwnerGuid'=>$ownerinfo[1]
						);
	}

	function parse_search($data)
	{
		preg_match_all("#\\|\\W*(GC[0-9A-Z]+)[^\\|]*\\|#",$data,$gccodes);
		if(empty($gccodes[1]))
			return false;
		else
			return $gccodes[1];
	}
	function caches_by_owner($owner)
	{
		$data=$this->get("http://www.geocaching.com/seek/nearest.aspx?u=".urlencode($owner));
		return $this->search_parse_pages($data,"owned_$owner");
	}
	function user_finds($user)
	{
		$data=$this->get('http://www.geocaching.com/seek/nearest.aspx?ul='.urlencode($user));
		return $this->search_parse_pages($data,"finds_$user");
	}
	function search_number_of_pages($data)
	{
		$pattern_local=sprintf('^<span>%s: <b>([0-9]+)</b> .{1,3} %s: <b>([0-9]+)</b> %s <b>([0-9]+)</b>^U',_('Total Records'),_('Page'),_('of')); //In Norwegian there is a strange - counting as 3 chars before the page number
		if(!preg_match($pattern_local,$data,$pages))
		{
			echo "Failed to parse search. Does the language selection of cachetools match the one on geocaching.com?\n";
			$lang=$this->lang;
			if($this->check_locale()===false)
				echo "Locale mismatch detected. Lang is set to $lang\n";
			return false;
		}
		else
			return $pages;
	}
	function search_parse_pages($data,$cache_name=false)
	{
		//$cache_name=false;
		$cache_name=strtolower($cache_name);
		$pages=$this->search_number_of_pages($data);
		//print_r($pages);
		$gccodes=$this->parse_search($data); //Parse the first page
		//echo "Parsing {$pages[3]} pages\n";
		for($i=2; $i<=$pages[3]; $i++) //Get the remaining pages
		{
			if(!isset($data_page)) //First page
				$data_page=$data;
			if($cache_name!==false)
			{
		 		if(($data_page_cache=$this->filecache->read($cache_name.'_page'.$i))===false)
				{
					echo "Fetching page $i\n";
					$data_page=$this->getpage($data_page,$i);
					$this->filecache->write($cache_name.'_page'.$i,$data_page);
				}
				else
					$data_page=$data_page_cache;
			}
			else
				$data_page=$this->getpage($data_page,$i);

			$gccodes_page=$this->parse_search($data_page);
			$pages=$this->search_number_of_pages($data_page);
			if(empty($gccodes_page) || $pages[2]!=$i)
			{
				if($cache_name!==false)
					$this->filecache->clear($cache_name.'_page'.$i); //Clear bad page from cache
				if(empty($gccodes_page))
				{
					echo "No gccodes on page $i, retrying\n";
					$data_page=$this->getpage($data_page,'next');
				}
				elseif($pages[2]!=$i) //Page mismatch
				{
					if($cache_name!==false)
						$this->filecache->write($cache_name.'_page'.$pages[2],$data_page);
					//$data_page=$this->getpage($data_page,'first');
					throw new Exception ("Page mismatch, requested $i, got {$pages[2]}\n");
					die();
				}
				//Go to next page and retry
				
				$i--;
				continue;
				//throw new Exception("No gccodes on page $i");	
			}
			else
				$gccodes=array_merge($gccodes,$gccodes_page);
		}
		return $gccodes;
	}
	
	function getpage($data,$page) //Get a page of a multipage search
	{
		$dom=new DOMDocument;
		@$dom->loadHTML($data);
		$inputs=$dom->getElementsByTagName('input');
		foreach($inputs as $input)
		{
			//print_r($input);
			$name=$input->getAttribute('name');
			if(substr($name,0,2)!='__')
				continue;
			$value=$input->getAttribute('value');
			$postfields[$name]=$value;
		}
		if(is_numeric($page))
			$postfields['__EVENTTARGET']='ctl00$ContentBody$pgrTop$lbGoToPage_'.$page;
		elseif($page=='next')
			$postfields['__EVENTTARGET']='ctl00$ContentBody$pgrBottom$ctl08';
		elseif($page=='first')
			$postfields['__EVENTTARGET']='ctl00$ContentBody$pgrBottom$ctl03';
		$postfields=array_merge($postfields,$this->get_viewstates($data));
		
		curl_setopt($this->ch,CURLOPT_POSTFIELDS,http_build_query($postfields));
		$return=curl_exec($this->ch);
		if($return===false)
			throw new Exception('cURL error: '.curl_error($this->ch));
		curl_setopt($this->ch,CURLOPT_HTTPGET,true);
		return $return;	
		
	}
	function __destruct()
	{
		curl_close($this->ch);	
	}
}