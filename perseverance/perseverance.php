<?php

/**************************************************************************
Copyright (C) Chicken Katsu 2013 - 2024
This code is protected by copyright under the terms of the 
Creative Commons Attribution-NonCommercial-NoDerivatives 4.0 International License
http://creativecommons.org/licenses/by-nc-nd/4.0/legalcode

For licenses that allow for commercial use please contact cluck@chickenkatsu.co.uk

// USE AT YOUR OWN RISK - NO GUARANTEES OR ANY FORM ARE EITHER EXPRESSED OR IMPLIED

example of RSS requests reverse engineered from 

# number of sols (starting at 0): 
#		https://mars.nasa.gov/rss/api/?feed=raw_images&category=mars2020&feedtype=json&latest=true

# 1st page of results for  SOL 0
#		https://mars.nasa.gov/rss/api/?feed=raw_images&category=mars2020&feedtype=json&num=50&page=0&order=sol+desc&condition_2=0:sol:gte&condition_3=0:sol:lte&extended=sample_type::thumbnail,
#
#		look for "total_results: 201" in result set to determine how many pages to fetch.

# look for an error message to see the last entry in the sol 0
#		https://mars.nasa.gov/rss/api/?feed=raw_images&category=mars2020&feedtype=json&num=50&page=1000&order=sol+desc&&&condition_2=0:sol:gte&condition_3=0:sol:lte&extended=sample_type::thumbnail,

# full caption example
#		https://mars.nasa.gov/mars2020/multimedia/raw-images/EUF_0001_0667022672_630ECV_N0010052EDLC00001_0010LUJ01
 **************************************************************************/

require_once  cAppGlobals::$spaceInc . "/missions/rover.php";
require_once  "$phpInc/ckinc/cached_http.php";

class cPerseverance extends cRoverManifest {

    function __construct() {
        //self::$BASE_URL = "http://mars.nasa.gov/mer/gallery/all/";
        $this->MISSION = "Perseverance";
        parent::__construct();
    }

    //*******************************************************************************
    protected  function pr_build_manifest() {
        cDebug::enter();
        $oSols = $this->pr__do_build_manifest();
        cDebug::leave();
        return $oSols;
    }

    //*******************************************************************************
    protected  function pr_generate_details($psSol, $psInstr) {
        cDebug::enter();
        cDebug::leave();
    }

    //#####################################################################
    //# PRIVATES
    //#####################################################################
    private function pr__do_build_manifest() {
        cDebug::enter();

        //get the total number of sols from the RSS feed
        $oHttp = new cCachedHttp;
        $oHttp->CACHE_EXPIRY = self::EXPIRY_TIME;
        $oJson = $oHttp->getCachedJson("https://mars.nasa.gov/rss/api/?feed=raw_images&category=mars2020&feedtype=json&latest=true");
        if (!isset($oJson->sol_count)) cDebug::error("unable to get sol count");

        //build the sols object with blanks
        $oSols = new cRoverSols;
        cDebug::leave();
        return $oSols;
    }
}
