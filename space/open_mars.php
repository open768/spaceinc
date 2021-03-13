<?php
/**************************************************************************
Copyright (C) Chicken Katsu 2014 -2015-2015

This code is protected by copyright under the terms of the 
Creative Commons Attribution-NonCommercial-NoDerivatives 4.0 International License
http://creativecommons.org/licenses/by-nc-nd/4.0/legalcode

Uses API provided by Chris C Cerami at https://github.com/chrisccerami/mars-photo-api
hosted at https://api.nasa.gov/mars-photos/api/v1/rovers

For licenses that allow for commercial use please contact cluck@chickenkatsu.co.uk

// USE AT YOUR OWN RISK - NO GUARANTEES OR ANY FORM ARE EITHER EXPRESSED OR IMPLIED
**************************************************************************/
require_once("$phpinc/ckinc/debug.php");
require_once("$phpinc/ckinc/http.php");
require_once("$phpinc/ckinc/cached_http.php");

class cNasaOpenMarsAPI{
	public static $API_KEY = null;
	const BASE_URL = "https://api.nasa.gov/mars-photos/api/v1/rovers";
	const API_KEY_FIELD = "api_key";
	public static $HTTPS_CERT_FILENAME = null;

	//#####################################################################
	//# PUBLIC functions
	//#####################################################################
	public static function get_missions(){
		$aData = self::pr_get_all_mission_data();
		return  $aData->rovers;
	}
	
	//*********************************************************************
	public static function get_mission_instruments($psMission){
		$aMissions = self::get_missions();
		
		$bFound = false;
		$oMission = null;
		
		foreach ($aMissions as $oMission){
			if ($oMission->name == $psMission){
				$bFound = true;
				break;
			}
		}
		
		if ($bFound)
			return $oMission->cameras;
		else
			return null;
	}

	//#####################################################################
	//# PRIVATES
	//#####################################################################
	private static function pr_get_all_mission_data(){
		$sUrl = self::BASE_URL . self::pr__KEY_Querystring("?");
		$oCache = new cCachedHttp();
		$oCache->USE_CURL = false;
		return $oCache->getCachedJson($sUrl);
	}

	//*********************************************************************
	private static function pr__KEY_Querystring($psPrefix){
		if (!self::$API_KEY){
				cDebug::error("API key not set in cNasaOpenMarsAPI");
		}
		
		return ( $psPrefix.self::API_KEY_FIELD."=".self::$API_KEY);
	}
}

?>
