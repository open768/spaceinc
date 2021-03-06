<?php
/**************************************************************************
Copyright (C) Chicken Katsu 2016

This code is protected by copyright under the terms of the 
Creative Commons Attribution-NonCommercial-NoDerivatives 4.0 International License
http://creativecommons.org/licenses/by-nc-nd/4.0/legalcode

For licenses that allow for commercial use please contact cluck@chickenkatsu.co.uk

// USE AT YOUR OWN RISK - NO GUARANTEES OR ANY FORM ARE EITHER EXPRESSED OR IMPLIED

**************************************************************************/
require_once("$phpinc/ckinc/debug.php");
require_once("$phpinc/ckinc/http.php");
require_once("$phpinc/ckinc/objstoredb.php");

class cRoverConstants{
	const MANIFEST_PATH = "[manifest]";
	const DETAILS_PATH = "[details]";
	const MANIFEST_FILE = "manifest";
	const SOLS_FILE = "sols";
}

//#####################################################################
//#####################################################################
class cRoverSols{
	private $aSols = null;
	private $iExpires = null;
	
	public function add($piSol, $psInstr=null, $piCount=null, $psUrl=null){
		if (!$this->aSols) $this->aSols = [];
		
		$sKey = (string) $piSol;
		if (!isset($this->aSols[$sKey])) $this->aSols[$sKey] = new cRoverSol();
		$oSol = $this->aSols[$sKey];
		$oSol->add($psInstr, $piCount, $psUrl);
	}	

	public function get_sol_numbers	(){
		if (!$this->aSols) cDebug::error("no sols loaded");
		ksort($this->aSols);
		return array_keys($this->aSols);
	}
	
	public function get_sol($piSol){
		if (!isset($this->aSols[(string)$piSol])) cDebug::error("Sol $piSol not found");
		return $this->aSols[(string)$piSol];
	}
}

//#####################################################################
//#####################################################################
abstract class cRoverManifest{
	const EXPIRY_TIME = 3600; //hourly expiry
	protected static $oObjStore = null;
	public $MISSION = null;
	protected $oSols = null;

	//#####################################################################
	//# constructor
	//#####################################################################
	//********************************************************************
	static function pr_init_objstore(){
		if (!self::$oObjStore){
			$oStore = new cObjStoreDB();
			$oStore->realm = "ROVMA";
			$oStore->check_expiry = true;
			$oStore->expire_time = self::EXPIRY_TIME;
			$oStore->set_table("ROVER");
			self::$oObjStore = $oStore;
		}
	}
	
	function __construct() {
		if (!$this->MISSION) cDebug::error("MISSION not set");
		$sPath = $this->pr__get_manifest_path();
		$this->oSols = self::$oObjStore->get( $sPath);
		if (!$this->oSols or cDebug::$IGNORE_CACHE){
			cDebug::write("generating manifest");
			$this->pr__get_manifest(); 
		}
	}
		
	//#####################################################################
	//# abstract functions
	//#####################################################################
	
	//must return an array of cRoverSol
	protected abstract function pr_build_manifest();
	
	protected abstract function pr_generate_details($psSol, $psInstr);
	
	//#####################################################################
	//# private class functions
	//#####################################################################
	private function pr__get_manifest_path(){
		return  $this->MISSION."/".cRoverConstants::MANIFEST_PATH."/".cRoverConstants::MANIFEST_FILE;
	}
	private function pr__get_manifest(){

		$sPath = $this->pr__get_manifest_path();
		$oSols = $this->pr_build_manifest(); 
		
		//check return type 
		if (! $oSols instanceof  cRoverSols) cDebug::error("return from pr_build_manifest must be cRoverSols");
		
		self::$oObjStore->put( $sPath, $oSols, true);
		$this->oSols = $oSols;
	}
	
	//#####################################################################
	//# PUBLIC functions
	//#####################################################################
	public function get_details($psSol, $psInstr){
		$sPath  = $this->MISSION."/".cRoverConstants::DETAILS_PATH."/$psSol/$psInstr";
		$oDetails =  self::$oObjStore->get($sPath);
		if ($oDetails) return $oDetails;
		
		//------------------------------------------------------
		cDebug::write("generating details");
		$oDetails = $this->pr_generate_details($psSol, $psInstr);
		self::$oObjStore->put( $sPath, $oDetails, true);
		return $oDetails;
	}
	
	//*************************************************************************************************
	public function get_sol_numbers(){
		cDebug::enter();
		if (!$this->oSols) cDebug::error("no sols");
		$aSols = $this->oSols->get_sol_numbers();
		cDebug::leave();
		return $aSols;
	}
	
	//*************************************************************************************************
	public function get_sol($piSol){
		if (!$this->oSols) cDebug::error("no sols");
		return $this->oSols->get_sol($piSol);
	}

}
cRoverManifest::pr_init_objstore();


//#####################################################################
//#####################################################################
class cRoverSol{
	public $instruments = [];
	
	public function add($psInstr, $piCount, $psUrl){
		if (!isset($this->instruments[$psInstr])) $this->instruments[$psInstr] = new cRoverInstrument();
		$oEntry = $this->instruments[$psInstr];
		$oEntry->count = $piCount;
		$oEntry->url = $psUrl;
	}
}

//#####################################################################
//#####################################################################
abstract class cRoverInstruments{
	private $aInstruments = [];
	private $aInstrument_map = [];
	
	protected abstract function prAddInstruments();
	
	//********************************************************************
	public  function getInstruments(){
		cDebug::enter();
		if (count($this->aInstruments)==0) 	$this->prAddInstruments();
		cDebug::leave();
		return $this->aInstruments;
	}
	
	//*****************************************************************************
	protected function pr_add($psName, $psAbbreviation ,$psCaption ,$psColour){
		$aInstr = ["name"=>$psName,"colour"=>$psColour, "abbr"=>$psAbbreviation,	"caption"=>$psCaption];
		$this->aInstruments[] = $aInstr;
		$this->aInstrumentMap[$psName] = $aInstr;
		$this->aInstrumentMap[$psAbbreviation] = $aInstr;
	}

	//*****************************************************************************
	public  function getAbbreviation($psName){
		cDebug::enter();
		$this->getInstruments();
		if (isset($this->aInstrumentMap[$psName])){
			cDebug::leave();
			return $this->aInstrumentMap[$psName]["abbr"];
		}
		
		foreach ($this->aInstruments as $aInstrument)
			if ($aInstrument["caption"] == $psName){
				cDebug::leave();
				return $aInstrument["abbr"];
			}
			
		cDebug::error("unknown Instrument: $psName");
	}
	
	//*****************************************************************************
	public  function getInstrumentName($psAbbr){
		cDebug::enter();
		$this->getInstruments();
		cDebug::leave();
		return  $this->aInstrumentMap[$psAbbr]["name"];
	}

	//*****************************************************************************
	public  function getDetails($psAbbr){
		cDebug::enter();
		$this->getInstruments();
		cDebug::leave();
		return  $this->aInstrumentMap[$psAbbr];
	}
}

class cRoverInstrument{
	public $count = -1;
	public $url = null;
}

//#####################################################################
//#####################################################################
class cRoverImage	{
	public $source = null;
	public $thumbnail = null;
	public $image = null;
}
?>
