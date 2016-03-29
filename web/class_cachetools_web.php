<?Php
require_once '../tools/DOMDocument_createElement_simple.php';
class cachetools_web
{
	public $dom;
	public $labels;
	public $attributes;
	function __construct()
	{
		error_reporting(E_ALL);
		ini_set('display_errors',true);
		$this->dom=new DOMDocumentCustom;
		$this->dom->formatOutput=true;
		$this->labels=array('LogText'=>_('Log')
		,'PlacedBy'=>_('Placed by'),'Archived'>=_('Archived'));
		$this->labels_caches=array(	'GCCode'=>_('GC Code'),
									'Name'=>_('Cache name'),	
									'PlacedBy'=>_('Placed by'),
									'CacheId'=>_('Cache ID'),
									'CacheType'=>_('Cache type'),
									'Container'=>_('Container'),
									'County'=>_('County'),
									'Country'=>_('Country'),
									'Difficulty'=>_('Difficulty'),
									'LastFoundDate'=>_('Last found'),
									'LastLog'=>_('Last log'),
									'Latitude'=>_('Latitude'),
									'Longitude'=>_('Longitude'),
									'NumberOfLogs'=>_('Number of logs'),
									'OwnerId'=>_('Owner id'),
									'OwnerName'=>_('Owner name'),
									'PlacedDate'=>_('Placed date'),
									'State'=>_('State'),
									'Terrain'=>_('Terrain'),
									'Status'=>_('Status'),
									'Elevation'=>_('Elevation'),
									'Resolution'=>_('Resolution'),
									'IsPremium'=>_('Is premium'),
									'FavPoints'=>_('Favorite points'),
									'NumberOfFinds'=>_('Finds')
									);
		$this->labels_log=array(	'LogType'=>_('Log type'),
									'LogText'=>_('Log text'),
									'Created'=>_('Created'),
									'Visited'=>_('Visited'),
									'UserName'=>_('User name'),
									'MembershipLevel'=>_('Membership level'));
		$this->labels=array_merge($this->labels_caches,$this->labels_log);
		$this->attributes=json_decode(file_get_contents('data/attributes.json'),true);
	}
	function cachelink($gccode)
	{
		$a=$this->dom->createElement('a',$gccode);
		$a->setAttribute('href','http://coord.info/'.$gccode);
		return $a;
	}
	function loglink($log,$date_format=false)
	{
		if($date_format===false)
			$date=$log['Visited'];
		else
			$date=date($date_format,strtotime($log['Visited']));

		$a=$this->dom->createElement('a',$date);
		$a->setAttribute('href','http://www.geocaching.com/seek/log.aspx?LUID='.$log['LogGuid']);
		return $a;
	}
	function cachelist($data,$fields,$separate=false)
	{
		$table=$this->dom->createElement('table');
		$table->setAttribute('border',1);
		$pagecount=1;
		foreach($data as $rowkey=>$row)
		{
			if($rowkey==0)
			{
				$tr=$this->dom->createElement_simple('tr',$table);
				foreach($fields as $fieldkey)
				{
					if(isset($this->labels[$fieldkey]))
						$label=$this->labels[$fieldkey];
					else
						$label=$fieldkey;

					$th=$this->dom->createElement_simple('th',$tr,'',$label);
				}
			}

			$tr=$this->dom->createElement_simple('tr',$table);

			foreach($fields as $fieldkey)
			{
				$td=$this->dom->createElement_simple('td',$tr);
				if($fieldkey=='GCCode')
					$td->appendChild($this->cachelink($row['GCCode']));
				elseif($fieldkey=='Visited')
					$td->appendChild($this->loglink($row,'Y-m-d'));
				elseif($fieldkey=='CacheType' || $fieldkey=='Container')
					$img=$this->dom->createElement_simple('img',$td,array('src'=>sprintf('images/icons/%s/%s.gif',$fieldkey,$row[$fieldkey])));
				elseif($fieldkey=='Difficulty' || $fieldkey=='Terrain')
					$img=$this->dom->createElement_simple('img',$td,array('src'=>sprintf('images/stars/stars%s.gif',$row[$fieldkey])));				
				elseif($fieldkey=='LogText')
				{
					foreach(explode('<br />',$row['LogText']) as $line)
					{
						$this->dom->createElement_simple('span',$td,'',$line);
						$this->dom->createElement_simple('br',$td);
					}
				}
				elseif($fieldkey=='count')
					$td->textContent=$pagecount;
				elseif($fieldkey=='Attributes')
				{
					
				}
				else
					$td->textContent=$row[$fieldkey];
					//$this->dom->createElement_simple('td',$tr,'',$row[$fieldkey]);

			}

			if($pagecount==$separate)
			{
				$tr=$this->dom->createElement_simple('tr',$table);
				$td=$this->dom->createElement_simple('td',$tr,array('colspan'=>count($fields)),'&nbsp');
				$pagecount=1;
			}
			else
				$pagecount++;			
		}
		return $this->dom->saveXML($table);
	}
	function attribute_matrix($attributes_count,$attribute_value='yes',$link=false)
	{
		if($attribute_value!='yes' && $attribute_value!='no')
			throw new Exception("attribute_value must be yes or no");
		$table=$this->dom->createElement('table');
		$count=0;
		foreach($this->attributes as $attribute=>$attribute_info)
		{
			if(isset($attributes_count[$attribute_value][$attribute]))
				$attribute_count=$attributes_count[$attribute_value][$attribute];
			else
				$attribute_count=0;
			if($attribute_value==='no' && $attribute_info['allow_no']===false)
				continue;
			if(!is_float($count/20))
				$tr=$this->dom->createElement_simple('tr',$table);

			$td=$this->dom->createElement_simple('td',$tr,array('style'=>'text-align: center;'));

			if($attribute_count==0)
				$value='';
			else
				$value='-'.$attribute_value;

			$this->dom->createElement_simple('img',$td,array('src'=>"images/attributes/$attribute$value.gif",'title'=>$attribute_info['name']));
			$this->dom->createElement_simple('br',$td);
			if($link!==false && $attribute_count>0)
				$this->dom->createElement_simple('a',$td,array('href'=>sprintf($link,$attribute)),$attribute_count);
			else
				$this->dom->createElement_simple('span',$td,'',$attribute_count);
			$count++;
		}
		return $this->dom->saveXML($table);
	}
	function table_row($table,$columns)
	{
		$tr=$this->dom->createElement_simple('tr',$table);
		foreach($columns as $column_value)
		{
			$this->dom->createElement_simple('td',$tr,'',$column_value);
		}
	}
}