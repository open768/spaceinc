<?php
/**************************************************************************
Copyright (C) Chicken Katsu 2014 -2015

This code is protected by copyright under the terms of the 
Creative Commons Attribution-NonCommercial-NoDerivatives 4.0 International License
http://creativecommons.org/licenses/by-nc-nd/4.0/legalcode

For licenses that allow for commercial use please contact cluck@chickenkatsu.co.uk

// USE AT YOUR OWN RISK - NO GUARANTEES OR ANY FORM ARE EITHER EXPRESSED OR IMPLIED
**************************************************************************/

require_once("$phpinc/ckinc/debug.php");
require_once("$phpinc/ckinc/cached_http.php");
require_once("$phpinc/ckinc/http-dir.php");

class cSpice{
	//
	//documentation at http://naif.jpl.nasa.gov/naif/tutorials.html
	//
	const NAIF_URL = "http://naif.jpl.nasa.gov/pub/naif";
	public static $aFamilies = array('spk','sclk','pck','lsk','ik','fk','ek','ck');
	public static $sMission = null;
	
	//*******************************************************************************
	//*
	//*******************************************************************************
	public static function get_family_list(){
		//get the directory listing from the URL
		$sURL = self::NAIF_URL."/".self::$sMission."/kernels/";
		//------------------------------------------------------------------
		$aDir = cHttpDirectory::read_dir($sURL);
		$aOut = [];
		foreach ($aDir as $sEntry){
			if (preg_match("/^(.*)\//", $sEntry, $aMatches))
				$aOut[] = $aMatches[1];
		}		
		return $aOut ;
	}
	
	//*******************************************************************************
	public static function get_kernel_list($psFamily){
		//get the directory listing from the URL
		if (!self::is_valid_family($psFamily))
			cDebug::error("not a valid Kernel Family $psFamily");
		cDebug::write("getting kernels for family: $psFamily");
		$sURL = self::NAIF_URL."/".self::$sMission."/kernels/$psFamily/";
		
		//------------------------------------------------------------------
		$aDir = cHttpDirectory::read_dir($sURL);
		$aOut = [];
		foreach ($aDir as $sEntry){
			if (preg_match("/\.\w+$$/", $sEntry) && ($sEntry !== "aareadme.txt"))
				$aOut[] = $sEntry;
		}		
		return $aOut ;
	}
	
	//*******************************************************************************
	public static function load_spk( $psFamily, $psKernel){
		if (!self::is_valid_family($psFamily))
			cDebug::error("not a valid Kernel Family $psFamily");
		
		$sURL = self::NAIF_URL."/".self::$sMission."/kernels/$psFamily/$psKernel";
		$oCache = new cCachedHttp();
		$sFile = $oCache->getCachedUrltoFile($sURL);
		cDebug::write("Url is in File: $sFile");
	}
	
	//*******************************************************************************
	public static function is_valid_family($psFamily){
		return in_array($psFamily, self::$aFamilies);
	}
}

?>