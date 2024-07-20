<?php
/**************************************************************************
Copyright (C) Chicken Katsu 2013 - 2024
This code is protected by copyright under the terms of the 
Creative Commons Attribution-NonCommercial-NoDerivatives 4.0 International License
http://creativecommons.org/licenses/by-nc-nd/4.0/legalcode

For licenses that allow for commercial use please contact cluck@chickenkatsu.co.uk

// USE AT YOUR OWN RISK - NO GUARANTEES OR ANY FORM ARE EITHER EXPRESSED OR IMPLIED

uses phpQuery https://code.google.com/archive/p/phpquery/ which is Licensed under the MIT license

**************************************************************************/
require_once("$phpInc/ckinc/debug.php");
require_once("$phpInc/ckinc/http.php");
require_once("$phpInc/ckinc/objstore.php");
require_once("$phpInc/ckinc/objstoredb.php");

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
	
	public function add($piSol, $psInstr, $piCount, $psUrl){
		if (!$this->aSols) $this->aSols = [];
		
		$sKey = (string) $piSol;
		if (!array_key_exists($sKey, $this->aSols)) $this->aSols[$sKey] = new cRoverSol();
		$oSol = $this->aSols[$sKey];
		$oSol->add($psInstr, $piCount, $psUrl);
	}	

	public function get_sol_numbers	(){
		if (!$this->aSols) cDebug::error("no sols loaded");
		ksort($this->aSols);
		return array_keys($this->aSols);
	}
	
	public function get_sol($piSol){
		if (!array_key_exists((string)$piSol, $this->aSols)) cDebug::error("Sol $piSol not found");
		return $this->aSols[(string)$piSol];
	}
}

//#####################################################################
//#####################################################################
abstract class cRoverManifest{
	public static $BASE_URL = null;
	public $MISSION = null;
	const USE_CURL = false;
	private $oSols = null;

	//#####################################################################
	//# constructor
	//#####################################################################
	function __construct() {
		if (!$this->MISSION) cDebug::error("MISSION not set");
		$sPath = $this->MISSION."/".cRoverConstants::MANIFEST_PATH;
		$this->oSols = cObjStore::get_file( $sPath, cRoverConstants::MANIFEST_FILE);
		if (!$this->oSols){
			cDebug::write("generating manifest");
			$this->pr_generate_manifest();
			cObjStore::put_file( $sPath, cRoverConstants::MANIFEST_FILE, $this->oSols);
		}
	}
		
	//#####################################################################
	//# abstract functions
	//#####################################################################
	protected abstract function pr_generate_manifest();
	protected abstract function pr_generate_details($psSol, $psInstr);
	
	//#####################################################################
	//# PUBLIC functions
	//#####################################################################
	public function get_details($psSol, $psInstr){
		$sPath  = $this->MISSION."/".cRoverConstants::DETAILS_PATH."/$psSol";
		$oDetails =  cObjStore::get_file( $sPath, $psInstr);
		if ($oDetails) return $oDetails;
		
		//------------------------------------------------------
		cDebug::write("generating details");
		$oDetails = $this->pr_generate_details($psSol, $psInstr);
		cObjStore::put_file( $sPath, $psInstr, $oDetails);
		return $oDetails;
	}
	
	//*************************************************************************************************
	public function add(  $piSol, $psInstr, $piCount, $psUrl){
		if (!$this->oSols) $this->oSols = new cRoverSols();
		$this->oSols->add(  $piSol, $psInstr, $piCount, $psUrl);
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

	//#####################################################################
	//# PRIVATE functions
	//#####################################################################
	protected static function pr__get_url( $psUrl){
		$oHttp = new cHttp();
		$oHttp->USE_CURL = self::USE_CURL;
		return  $oHttp->fetch_url($psUrl);
	}

}

//#####################################################################
//#####################################################################
class cRoverSol{
	public $instruments = [];
	
	public function add($psInstr, $piCount, $psUrl){
		if (!array_key_exists($psInstr, $this->instruments)) $this->instruments[$psInstr] = new cRoverInstrument();
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
		$this->aInstrument_map[$psName] = $aInstr;
		$this->aInstrument_map[$psAbbreviation] = $aInstr;
	}

	//*****************************************************************************
	public  function getAbbreviation($psName){
		cDebug::enter();
		$this->getInstruments();
		if (array_key_exists($psName,$this->aInstrument_map)){
			cDebug::leave();
			return $this->aInstrument_map[$psName]["abbr"];
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
		return  $this->aInstrument_map[$psAbbr]["name"];
	}

	//*****************************************************************************
	public  function getDetails($psAbbr){
		cDebug::enter();
		$this->getInstruments();
		cDebug::leave();
		return  $this->aInstrument_map[$psAbbr];
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
