<?php

/**************************************************************************
Copyright (C) Chicken Katsu 2013 -2024

This code is protected by copyright under the terms of the 
Creative Commons Attribution-NonCommercial-NoDerivatives 4.0 International License
http://creativecommons.org/licenses/by-nc-nd/4.0/legalcode

For licenses that allow for commercial use please contact cluck@chickenkatsu.co.uk
// USE AT YOUR OWN RISK - NO GUARANTEES OR ANY FORM ARE EITHER EXPRESSED OR IMPLIED
 **************************************************************************/

require_once cAppGlobals::$spaceInc . "/missions/rover.php";
require_once cAppGlobals::$spaceInc . "/curiosity/instrument.php";
require_once cAppGlobals::$spaceInc . "/db/mission-manifest.php";
require_once cAppGlobals::$spaceInc . "/misc/constants.php";


class cManifestSolData {
    public string $sol;
    public array  $data = [];
}


//###############################################################################
class cCuriosityJPLManifest {
    const MANIFEST_CACHE = 4 * 3600;    //4 hour
    const FEED_URL = "https://mars.jpl.nasa.gov/msl-raw-images/image/image_manifest.json";
    const SOL_URL = "https://mars.jpl.nasa.gov/msl-raw-images/image/images_sol";
    const SOL_CACHE = 7 * 24 * 3600;    // a week
    const FEED_SLEEP = 250; //milliseconds
    static $cached_manifest = null;
    private static $dont_check_sol_index = false;



    //*****************************************************************************
    static function getManifest() {
        $oResult = null;
        //cTracing::enter();

        $oResult = self::$cached_manifest;
        if ($oResult == null) {
            $oCache = new cCachedHttp(); {
                $oCache->CACHE_EXPIRY = self::MANIFEST_CACHE;
                $oResult = $oCache->getCachedJson(self::FEED_URL);
            }
            self::$cached_manifest = $oResult;
        }
        //cTracing::leave();
        return $oResult;
    }

    //*****************************************************************************
    static function getSolEntry(string $psSol) {
        //cTracing::enter();
        //---- get the manifest
        $oManifest = self::getManifest();
        $aSols = $oManifest->sols;
        if (!is_numeric($psSol)) cDebug::error("not an integer");

        $iSol = (int) $psSol; //make sure we have an integer
        ksort($aSols, SORT_NUMERIC);

        $oMatched = null;

        //----array is not keyed on sol, so we have to find the sol
        foreach ($aSols as $oSol) {
            if ($oSol->sol === $iSol) {
                $oMatched = $oSol;
                break;
            }
        }

        if ($oMatched == null) cDebug::write("unable to find the SOL entry");
        //cTracing::leave();
        return $oMatched;
    }

    //*****************************************************************************
    static function getSolJsonUrl($psSol) {
        //cTracing::enter();

        $oSol = self::getSolEntry($psSol);
        $sUrl = $oSol->catalog_url;

        //cTracing::leave();
        return $sUrl;
    }

    //*****************************************************************************
    public static function getSolRawData($psSol, $pbCheckExpiry = true, $pbIndexMissing = false) {
        cTracing::enter();

        $sUrl = self::getSolJsonUrl($psSol);
        if (cCommon::is_string_empty($sUrl)) cDebug::error("empty url for $psSol");

        cDebug::extra_debug("Getting all sol data for sol $psSol");
        $oCache = new cCachedHttp(); {
            $oCache->CACHE_EXPIRY = self::SOL_CACHE;
            $bIsCached = $oCache->is_cached($sUrl, $pbCheckExpiry);
            $oResult = $oCache->getCachedJson($sUrl, $pbCheckExpiry);
        }

        if ($oResult === null)
            cDebug::write("nothing found - sol doesnt exist at $sUrl");
        elseif (! self::$dont_check_sol_index) {
            $bInIndex = cCuriosityORMManifest::is_sol_in_index($psSol);
            if (!$bInIndex && $pbIndexMissing) {
                self::$dont_check_sol_index = true;
                cDebug::extra_debug_warning("sol found, but isnt in manifestindex.. ");
                try {
                    cCuriosityORMManifestIndexer::reindex_if_needed($psSol);
                } finally {
                    self::$dont_check_sol_index = false;
                }
            }
        }



        if (cDebug::is_debugging() && !$bIsCached) {
            cDebug::write("<p> -- sleeping for " . self::FEED_SLEEP . " ms\n");
            usleep(self::FEED_SLEEP);
        }

        cTracing::leave();
        return $oResult;
    }

    //*****************************************************************************
    public static function clearSolDataCache($psSol) {
        cTracing::enter();

        cDebug::write("clearing sol cache : " . $psSol);
        $sUrl = self::getSolJsonUrl($psSol);
        $oCache = new cCachedHttp();
        $oCache->deleteCachedURL($sUrl);

        cTracing::leave();
    }
}
