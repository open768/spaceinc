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
require_once("$phpInc/phpquery/phpQuery-onefile.php");
require_once("$spaceInc/rover.php");

//#####################################################################
//#####################################################################
class cSpiritInstruments extends cRoverInstruments{
	
	protected function prAddInstruments(){
		self::pr_add("FHAZ",	"F",	"Front Hazcam",	"red");
		self::pr_add("RHAZ",	"R",	"Rear Hazcam",	"green");
		self::pr_add("NAVCAM",	"N",	"Navigation Camera",	"steelblue");
		self::pr_add("PANCAM",	"P",	"Panoramic Camera",	"lime");
		self::pr_add("MI_IM",	"M",	"Microscopic Imager",	"blue");
		self::pr_add("ENT",		"E",	"Entry",	"white");
		self::pr_add("DES",		"D",	"Descent",	"yellow");
		self::pr_add("LAND",	"L",	"Landing",	"cyan");
		self::pr_add("EDL",		"EDL",	"Entry, Descent, and Landing",	"tomato");
	}
	
}

//#####################################################################
//#####################################################################
class cSpiritRover extends cRoverManifest{
	const MANIFEST_URL = "spirit.html";
	
	function __construct(){
		self::$BASE_URL = "http://mars.nasa.gov/mer/gallery/all/";
		$this->MISSION = "SPIRIT";
		parent::__construct();
	}
	

	//#####################################################################
	//# implement abstract functions
	//#####################################################################
	protected function pr_generate_details($psSol, $psInstr){
		cDebug::enter();
		
		//find the url where to get the instrument details from
		$oSol = $this->get_sol($psSol);
		$aInstruments = $oSol->instruments;
		if (!array_key_exists( $psInstr, $aInstruments)) cDebug::error("instrument $psInstr doesnt exist for sol $psSol");
		$oInstr = $aInstruments[$psInstr];
		$sFragment = $oInstr->url;
		cDebug::extra_debug("url is $sFragment");
		
		//------------------------------------------------------
		cDebug::write("fetching nasa url");
		$sHTML = self::pr__get_url(self::$BASE_URL.$sFragment);
		
		cDebug::write("extracting data");
		$oDoc = phpQuery::newDocument($sHTML);
		
		//------------------------------------------------------
		cDebug::extra_debug("querying for images");
		$oResults = $oDoc["a:has(img[src$=THM.JPG])"];
		if ($oResults->length == 0) cDebug::error("nothing found");
		cDebug::extra_debug("found  $oResults->length matches");

		$aResults = [];
		$oResults->each( function($oMatch) use (&$aResults, $sFragment){
			$oPQ = pq($oMatch);
			$sDetailFragment = $oPQ->attr("href");	
			cDebug::extra_debug("href url is $sDetailFragment<br>");
			
			$oImages = $oPQ->children('img[src$=THM.JPG]');
			if ($oImages->length == 0) cDebug::error("no images found");				
			cDebug::extra_debug("found $oImages->length images");
			
			$oImg = $oImages->eq(0);
			$sThumbUrl = $oImg->attr('src');
			cDebug::extra_debug("thumbnail  is '$sThumbUrl'<br>");
			
			$sImgUrl = preg_replace("/-THM/","",$sThumbUrl);
			cDebug::extra_debug("image  '$sImgUrl'");
			
			$oDetail = new cRoverImage;
			$oDetail->source = $sFragment;
			$oDetail->thumbnail = $sThumbUrl;
			$oDetail->image = $sImgUrl;
			
			$aResults[] = $oDetail;
		});
		
		cDebug::leave();
		return $aResults;
	}
			
	//*****************************************************************************
	protected function pr_generate_manifest(){
		cDebug::enter();
		
		//------------------------------------------------------
		cDebug::write("fetching page from NASA");
		$sHTML = self::pr__get_url(self::$BASE_URL.self::MANIFEST_URL);
		cDebug::write("building query object");
		$oDoc = phpQuery::newDocument($sHTML);
		$oInstruments = new cSpiritInstruments();
		
		//------------------------------------------------------
		//find all selects with name solfile.
		cDebug::write("locating instruments");
		$oResults = $oDoc["select[name='solFile']"];
		$oParent = $this;
		
		$oResults->each(function($oSelect) use(&$oParent,$oInstruments){
			$oSelectPQ = pq($oSelect);
			
			//get the label
			$sLabel = pq($oSelectPQ)->parent()["label"]->html();
			if (preg_match("/(.*):/", $sLabel, $aMatches))
				$sLabel = $aMatches[1];
			cDebug::write("Instrument found: $sLabel");
			$sAbbr = $oInstruments->getAbbreviation($sLabel);
			
			//iterate the Items in the select
			$oSelectPQ["option"]->each( function ($oOption) use (&$oParent, $sAbbr){
				$oOptionPQ = pq($oOption);
				$sUrl = $oOptionPQ->attr("value");
				
				$sTmp = $oOptionPQ->html();
				preg_match("/Sol (\d+) \((\d+)/", $sTmp, $aMatches);
				$iSol = (int)$aMatches[1];
				$iCount = (int)$aMatches[2];
				$oParent->add($iSol, $sAbbr, $iCount, $sUrl);
			});
		});
		cDebug::leave();
	}
}

?>
