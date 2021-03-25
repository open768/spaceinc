<?php
/**************************************************************************
Copyright (C) Chicken Katsu 2014 -2015

This code is protected by copyright under the terms of the 
Creative Commons Attribution-NonCommercial-NoDerivatives 4.0 International License
http://creativecommons.org/licenses/by-nc-nd/4.0/legalcode

For licenses that allow for commercial use please contact cluck@chickenkatsu.co.uk

// USE AT YOUR OWN RISK - NO GUARANTEES OR ANY FORM ARE EITHER EXPRESSED OR IMPLIED
**************************************************************************/

require_once("$phpinc/ckinc/objstore.php");
require_once("$spaceinc/misc/indexes.php");
require_once("$phpinc/ckinc/gz.php");
require_once("$spaceinc/pds/pdsreader.php");
require_once("$spaceinc/pds/pds.php");
require_once("$spaceinc/curiosity/curiositypds.php");


//##########################################################################
class cCuriosityPdsIndexer{
	
	//**********************************************************************
	public static function index_everything(){
		$aCatalogs = cCuriosityPDS::catalogs();
		foreach ($aCatalogs as $sCatalog)
			self::run_indexer( $sCatalog, "EDRINDEX");
	}
	
	//**********************************************************************
	public static function run_indexer( $psVolume, $psIndex){
		cDebug::write("<b>running indexer</b>");
		$oPDSReader = new cPDS_Reader;
		$oPDSReader->set_product_column("MSL:INPUT_PRODUCT_ID");
		$oPDSReader->PDS_URL = cCuriosityPDS::MSL_PDS_URL;
		
		//-------------------------------------------------------------------------------
		//get the LBL file to understand how to parse the file 
		$oLBL = $oPDSReader->fetch_volume_lbl($psVolume, $psIndex);
		if (cDebug::$EXTRA_DEBUGGING) $oLBL->__dump();
		
		//-------------------------------------------------------------------------------
		//get the TAB file
		$oPDSReader->fetch_tab($oLBL,$psVolume );
		
		cDebug::write("Done OK");
	}
}
?>