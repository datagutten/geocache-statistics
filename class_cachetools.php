<?Php
class cachetools
{
	public $db;
	public $locale_path;
	function __construct()
	{
		$this->connect_db();
	}
	function connect_db()
	{
		require 'config_db.php';
		$this->db = new PDO("mysql:host=$db_host;dbname=$db_name",$db_user,$db_password,array(PDO::ATTR_PERSISTENT => true));
	}
	function query($q,$fetch='all',&$timing=false)
	{
		$start=time();
		$st=$this->db->query($q);
		$end=time();

		$timing=$end-$start;
		if($st===false)
		{
			$errorinfo=$this->db->errorInfo();
			//trigger_error("SQL error: {$errorinfo[2]}",E_USER_WARNING);
			throw new Exception("SQL error: {$errorinfo[2]}");
			//return false;
		}
		elseif($fetch===false)
			return $st;
		elseif($fetch=='single')
			return $st->fetch(PDO::FETCH_COLUMN);
		elseif($fetch=='all')
			return $st->fetchAll(PDO::FETCH_ASSOC);
		elseif($fetch=='all_column')
			return $st->fetchAll(PDO::FETCH_COLUMN);		
	}
	function execute($st,$parameters,$fetch=false)
	{
		if($st->execute($parameters)===false)
		{
			$errorinfo=$st->errorInfo();
			trigger_error("SQL error: {$errorinfo[2]}",E_USER_WARNING);
			//throw new Exception("SQL error: {$errorinfo[2]}");
			return false;
		}
		elseif($fetch=='single')
			return $st->fetch(PDO::FETCH_COLUMN);
		elseif($fetch=='all')
			return $st->fetchAll(PDO::FETCH_ASSOC);
	}
	public function set_locale($locale,$domain)
	{
		$this->locale_path=dirname(__FILE__).'/locale';
		if(!file_exists($file=$this->locale_path."/$locale/LC_MESSAGES/$domain.mo"))
		{
			$this->error(sprintf(_("No translation found for locale %s. It should be placed in %s"),$locale,$file));
			return false;
		}
		putenv('LC_MESSAGES='.$locale);
		setlocale(LC_MESSAGES,$locale);
		// Specify location of translation tables
		bindtextdomain($domain,$this->locale_path);
		// Choose domain
		textdomain($domain);

		$this->lang=preg_replace('/([a-z]+)_.+/','$1',$locale); //Get the language from the locale
	}	
}