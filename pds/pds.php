<?php
/**************************************************************************
Copyright (C) Chicken Katsu 2014 -2015

This code is protected by copyright under the terms of the 
Creative Commons Attribution-NonCommercial-NoDerivatives 4.0 International License
http://creativecommons.org/licenses/by-nc-nd/4.0/legalcode

For licenses that allow for commercial use please contact cluck@chickenkatsu.co.uk

// USE AT YOUR OWN RISK - NO GUARANTEES OR ANY FORM ARE EITHER EXPRESSED OR IMPLIED
**************************************************************************/

require_once("$phpinc/ckinc/http.php");
require_once("$phpinc/ckinc/objstoredb.php");
require_once("$spaceinc/misc/indexes.php");


//##########################################################################
class cPDS{
	const OBJDATA_TOP_FOLDER = "[pds]";
	const PDS_SUFFIX = "PDS";
	private static $oObjStore = null;
		
	//********************************************************************
	static function pr_init_objstore(){
		if (!self::$oObjStore){
			self::$oObjStore = new cObjStoreDB();
			self::$oObjStore->realm = "PDS";
		}
	}
	
	//**********************************************************************
	private static function pr__get_objstore_Folder($psSol, $psInstrument){
		cDebug::enter();
		$sFolder = self::OBJDATA_TOP_FOLDER."/$psSol/$psInstrument";
		cDebug::leave();
		return $sFolder;
	}
	
	//**********************************************************************
	public static function get_pds_data($psSol, $psInstrument){
		cDebug::enter();
		$sFolder = self::pr__get_objstore_Folder($psSol,$psInstrument);
		$oData = self::$oObjStore->get_oldstyle($sFolder, cIndexes::get_filename(cIndexes::INSTR_PREFIX, self::PDS_SUFFIX));
		cDebug::leave();

		return $oData; 
	}
	
	//**********************************************************************
	public static function write_index_data($paData){
		foreach ($paData as  $sSol=>$aSolData)	
			foreach ($aSolData as $sInstr=>$aInstrData){
				$sFilename = cIndexes::get_filename(cIndexes::INSTR_PREFIX, self::PDS_SUFFIX);
				$aPDSData = self::$oObjStore->get_oldstyle(self::OBJDATA_TOP_FOLDER."/$sSol/$sInstr", $sFilename);				
				if ($aPDSData){  
					//update existing with new data
					foreach ($aInstrData as $sNewKey=>$aNewData)
						$aPDSData[$sNewKey] = $aNewData;
				}else
					$aPDSData = $aInstrData;
				self::$oObjStore->put_oldstyle( self::OBJDATA_TOP_FOLDER."/$sSol/$sInstr", $sFilename, $aPDSData);
				cDebug::extra_debug("$sSol/$sInstr lines:".count($aPDSData));			
			}
	}
	
	//**********************************************************************
	public static function kill_index_files(){
		cObjStore::kill_folder(self::OBJDATA_TOP_FOLDER);
	}
}
cPDS::pr_init_objstore();
?>