<?php
/**************************************************************************
Copyright (C) Chicken Katsu 2013 -2024

This code is protected by copyright under the terms of the 
Creative Commons Attribution-NonCommercial-NoDerivatives 4.0 International License
http://creativecommons.org/licenses/by-nc-nd/4.0/legalcode

For licenses that allow for commercial use please contact cluck@chickenkatsu.co.uk

// USE AT YOUR OWN RISK - NO GUARANTEES OR ANY FORM ARE EITHER EXPRESSED OR IMPLIED
**************************************************************************/

require_once("$phpInc/ckinc/http.php");
require_once("$spaceInc/misc/indexes.php");
require_once("$spaceInc/curiosity/curiosity.php");
require_once("$spaceInc/curiosity/instrument.php");
require_once("$spaceInc/curiosity/pdsindexer.php");
require_once("$spaceInc/pds/lbl.php");
require_once("$spaceInc/pds/pdsreader.php");
require_once("$spaceInc/pds/pds.php");


//##########################################################################
class cCuriosityPDS{
	const SHORT_REGEX = "/^(\d{4})(\D{2})(\d{4})(\d{3})(\d{3})(\D)(\d{1})_(\D{4})/";
	const PICNO_REGEX = "/^(\d{4})(\D{2})(\d{6})(\d{3})(\d{2})(\d{5})(\D)(\d{2})_(\D{4})/";
	const PICNO_FORMAT = "%04d%s%06d%03d%02d%05d%s%02d_%s";
	const PICNO_REGEX_FORMAT = "/%04d%s%06d%03d\d{7}.*%s/";
	const PRODUCT_TYPE_PICNO = 0;
	const PRODUCT_TYPE_SHORT = 1;
	const PRODUCT_TYPE_UNKNOWN = -1;
	const MSL_PDS_URL = "http://pds-imaging.jpl.nasa.gov/data/msl";

	//*****************************************************************************
	//* see http://pds-imaging.jpl.nasa.gov/data/msl/MSLMST_0005/DOCUMENT/MSL_MMM_EDR_RDR_DPSIS.PDF pg23 PICNO
	public static function explode_productID($psProduct){
		$aResult = null;
		
		if (preg_match(self::SHORT_REGEX, $psProduct, $aMatches)){	
			cDebug::write("matches SHORTREGEX");
			$aResult = [
				"sol"=>(int)$aMatches[1],
				"instrument"=>$aMatches[2],
				"seqid" => (int) $aMatches[3],
				"seq line" => (int)$aMatches[4],
				"CDPID" => (int)$aMatches[5],
				"product type" => $aMatches[6],
				"gop counter" => (int) $aMatches[7],
				"version" => 0,
				"processing code" => $aMatches[8]
			];
		}elseif (preg_match(self::PICNO_REGEX, $psProduct, $aMatches)){
			cDebug::write("matches PICNOREGEX");
			$aResult = [
				"sol"=>(int)$aMatches[1],
				"instrument"=>$aMatches[2],
				"seqid" => (int) $aMatches[3],
				"seq line" => (int) $aMatches[4],
				"CDPID" => (int) $aMatches[5],
				"product type" => $aMatches[6],
				"gop counter" => (int) $aMatches[7],
				"version" => (int) $aMatches[8],
				"processing code" => $aMatches[9],
			];
		}else{
			cDebug::error("not a valid MSL product: '$psProduct'", true);
		}
		return $aResult;
	}

	//**********************************************************************
	public static function get_product_type($psProduct){
		cDebug::enter();
		$iProduct_type = self::PRODUCT_TYPE_UNKNOWN;
		
		if (preg_match(self::SHORT_REGEX, $psProduct, $aMatches))
			$iProduct_type = self::PRODUCT_TYPE_SHORT;
		elseif (preg_match(self::PICNO_REGEX, $psProduct, $aMatches))
			$iProduct_type = self::PRODUCT_TYPE_PICNO;
		
		cDebug::extra_debug("Product $psProduct is of type $iProduct_type");
		cDebug::enter();
		return $iProduct_type;
	}
	
	//**********************************************************************
	private static function get_MSL_ProductID($psSearch){
		$aExplode = self::explode_productID($psSearch);
		##TBD;
	}
	
	//**********************************************************************
	public static function get_pds_productRegex($psProduct){
		//split the MSL product apart	
		//seq_line and CPD_ID may be zero
		//BROKEN!!!

		$aExploded = self::explode_productID($psProduct);
		cDebug::vardump($aExploded);

		cDebug::write($aExploded["product type"]);
		$sRegex = sprintf(	self::PICNO_REGEX_FORMAT, 
			$aExploded["sol"],
			$aExploded["instrument"] , 
			$aExploded["seqid"] ,
			$aExploded["seq line"] ,		
			$aExploded["processing code"] 
		);
		
		cDebug::extra_debug("PDS regex: $sRegex");
		return $sRegex;
	}
	
	//**********************************************************************
	public static function get_pds_productID($psProduct){
		//split the MSL product apart	
		if (self::get_product_type($psProduct) == self::PRODUCT_TYPE_PICNO){
			cDebug::write("product is allread a PICNO");
			$sPDSProduct = $psProduct;
		}else{
			$aMSLProduct = self::explode_productID($psProduct);
			cDebug::vardump($aMSLProduct);

			$sPDSProduct = sprintf(	self::PICNO_FORMAT, 
				$aMSLProduct["sol"],
				$aMSLProduct["instrument"] , 
				$aMSLProduct["seqid"] ,
				$aMSLProduct["seq line"],
				$aMSLProduct["CDPID"],
				$aMSLProduct["product type"] , 
				$aMSLProduct["gop counter"] , 
				$aMSLProduct["version"],
				$aMSLProduct["processing code"] 
			);
			
			cDebug::write("PDS: $sPDSProduct");
		}
		return $sPDSProduct;
	}
	
	
	//**********************************************************************
	public static function search_pds($psSol, $psInstrument, $psProduct){
		$bIsRegex = true;
		$aProducts = [];
		$sI01Product = null;
		
		cDebug::enter();
		cDebug::write("looking for $psSol, $psInstrument, $psProduct");
		
		//---- convert to PDS format ------------------
		$iType = self::get_product_type($psProduct);
		cDebug::write("Product Type = $iType");
		switch($iType){
			case self::PRODUCT_TYPE_PICNO:
				cDebug::write("product is a PICNO");
				$sI01Product = str_replace("E01_","I01_",$psProduct);
				cDebug::write("could also be $sI01Product");
				$bIsRegex = false;
				break;
			case self::PRODUCT_TYPE_SHORT:
				$sPDSRegex = self::get_pds_productRegex($psProduct);
				break;
			default:
				$bIsRegex = false;
				break;
		}
		
		//-----retrive PDS stuff for instrument----------------
		$aData = cPDS::get_pds_data($psSol, $psInstrument );
		if ($aData === null){
			cDebug::write("no pds data found for $psSol, $psInstrument ");
			cDebug::leave();
			return null;
		}	
			
		//debug
		cDebug::write("performing match");
		$oMatch = null;
		$sProducts = "<br>";
		foreach ($aData as $sKey=>$oData){
			$aProducts[] = $sKey;
			if ($bIsRegex){
				if ( preg_match($sPDSRegex, $sKey)){
					cDebug::write("got a match with $sKey");
					$oMatch=["p"=>$sKey, "s"=>$psSol, "i"=>$psInstrument, "d"=>$oData];
					break;
				}
			}else{
				if ($sKey === $psProduct || $sKey === $sI01Product){
					cDebug::write("found matching product $sKey");
					$oMatch=["p"=>$psProduct, "s"=>$psSol, "i"=>$psInstrument, "d"=>$oData];
					break;
				}
			}
		}
		
		if ($oMatch == null){
			cDebug::write("no matches found within the following products");
			cDebug::vardump($aProducts);
		}else{
			//enrich object to create the full url
			$oMatch["u"] = self::MSL_PDS_URL."/".$oMatch["d"]["v"]."/".$oMatch["d"]["p"].$oMatch["d"]["f"];
			
			//and have a guess at the RDR image
			$rdr_path = str_replace("DATA/EDR/SURFACE","EXTRAS/RDR/SURFACE/FULL",$oMatch["d"]["p"] );
			$rdr_file = str_replace("_XXXX.LBL","_DRCX.JPG", $oMatch["d"]["f"]);
			$oMatch["rdr"] = self::MSL_PDS_URL."/".$oMatch["d"]["v"]."/".$rdr_path.$rdr_file;
			
			//and have a guess at Notebook link
			//$sNotebook = "https://an.rsl.wustl.edu/msl/mslbrowser/br2.aspx?tab=solsumm&p=" . $psProduct;
			$sNB_product = str_replace("_DXXX","_XXXX", $psProduct);
			$sNotebook = "https://an.rsl.wustl.edu/msl/mslbrowser/product.aspx?B1=$sNB_product&xw=1";
			$oMatch["notebook"] = $sNotebook ;
		}
		
		cDebug::leave();
		return $oMatch;
	}
	
	//**********************************************************************
	public function map_MSL_Instrument($psInstrument){
		$aMapping = [ 
			"ML"=>"MST",
			"MR"=>"MST",
			"MH"=>"MHL",
			"MD"=>"MRD"
		];
		return $aMapping[$psInstrument];
	}
	
	//**********************************************************************
	public static function catalogs(){
		$aOut = [];
		for ($i=1; $i<=10; $i++){
			$aOut[] = "MSLMHL_".str_pad("$i",4,"0",STR_PAD_LEFT);
			$aOut[] = "MSLMRD_".str_pad("$i",4,"0",STR_PAD_LEFT);
			$aOut[] = "MSLMST_".str_pad("$i",4,"0",STR_PAD_LEFT);
		}
		
		//self::run_indexer( "MSLNAV_0XXX", "INDEX");
		//self::run_indexer( "MSLNAV_1XXX", "INDEX");
		//self::run_indexer( "MSLHAZ_0XXX", "INDEX");
		//self::run_indexer( "MSLHAZ_1XXX", "INDEX");
		//self::run_indexer( "MSLHAZ_1XXX", "INDEX");
		
		//mosaics are different!
		//self::run_indexer( "MSLMOS_1XXX", "INDEX");
		return $aOut;
	}
	
}
?>