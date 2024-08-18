<?php

/**************************************************************************
Copyright (C) Chicken Katsu 2013 -2024

This code is protected by copyright under the terms of the 
Creative Commons Attribution-NonCommercial-NoDerivatives 4.0 International License
http://creativecommons.org/licenses/by-nc-nd/4.0/legalcode

For licenses that allow for commercial use please contact cluck@chickenkatsu.co.uk

// USE AT YOUR OWN RISK - NO GUARANTEES OR ANY FORM ARE EITHER EXPRESSED OR IMPLIED
 **************************************************************************/
class cCuriosityManifest {
    const MANIFEST_CACHE = 3600;    //1 hour
    const FEED_URL = "https://mars.jpl.nasa.gov/msl-raw-images/image/image_manifest.json";
    const SOL_URL = "https://mars.jpl.nasa.gov/msl-raw-images/image/images_sol";
    const SOL_CACHE = 604800;    //1 week
    const DB_FILENAME = "curiositymanifest.db";

    /**  @var cObjstoreDB $oDB */
    private static $oSQLite = null; //static as same database obj used between instances

    //*****************************************************************************
    static function init_db() {
        if (self::$oSQLite == null) {
            cDebug::extra_debug("opening cSqlLite database");
            $oDB = new cSqlLite(self::DB_FILENAME);
            self::$oSQLite = $oDB;
        } else
            cDebug::extra_debug(" cSqlLite instance exists");
    }

    //*****************************************************************************
    static function getManifest() {
        $oResult = null;
        cDebug::enter();

        cDebug::write("Getting sol manifest from: " . self::FEED_URL);
        $oCache = new cCachedHttp();
        $oCache->CACHE_EXPIRY = self::MANIFEST_CACHE;

        $oResult = $oCache->getCachedJson(self::FEED_URL);
        cDebug::leave();
        return $oResult;
    }

    //*****************************************************************************
    static function getSolJsonUrl($psSol) {
        cDebug::enter();
        $sUrl = self::SOL_URL . "{$psSol}.json";
        cDebug::leave();
        return $sUrl;
    }

    //*****************************************************************************
    public static function getAllSolData($psSol) {
        cDebug::enter();

        $sUrl = self::getSolJsonUrl($psSol);
        cDebug::write("Getting all sol data from: " . $sUrl);
        $oCache = new cCachedHttp();
        $oCache->CACHE_EXPIRY = self::SOL_CACHE;

        $oResult = $oCache->getCachedJson($sUrl);
        cDebug::leave();
        return $oResult;
    }

    //*****************************************************************************
    public static function clearSolDataCache($psSol) {
        cDebug::enter();

        cDebug::write("clearing sol cache : " . $psSol);
        $oCache = new cCachedHttp();
        $sUrl = self::getSolJsonUrl($psSol);
        $oCache->deleteCachedURL($sUrl);

        cDebug::leave();
    }

    //*****************************************************************************
    static function indexManifest() {
        cDebug::enter();
        $oManifest = self::getManifest();
        cDebug::error("stop");
        cDebug::leave();
    }
}
cCuriosityManifest::init_db();
