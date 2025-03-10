<?php

/**************************************************************************
Copyright (C) Chicken Katsu 2013 -2024

This code is protected by copyright under the terms of the 
Creative Commons Attribution-NonCommercial-NoDerivatives 4.0 International License
http://creativecommons.org/licenses/by-nc-nd/4.0/legalcode

For licenses that allow for commercial use please contact cluck@chickenkatsu.co.uk
// USE AT YOUR OWN RISK - NO GUARANTEES OR ANY FORM ARE EITHER EXPRESSED OR IMPLIED
 **************************************************************************/
//#################################################################################
class cCuriosityManifestIndexStatus {
    const INDEXING_STATUS_KEY = "indexing:status";
    const INDEXING_LASTSOL_KEY = "indexing:lastsol";
    const LAST_SOL_PREFIX = "indexing:lastsol:";
    const STATUS_NOT_STARTED = -1;
    const STATUS_COMPLETE = "complete";

    /**  @var cObjstoreDB $oDB */
    private static $oDB = null;

    //*****************************************************************************
    static function init_db() {
        if (self::$oDB === null)
            self::$oDB = new cOBjStoreDB(cSpaceRealms::ROVER_MANIFEST, cSpaceTables::ROVER_MANIFEST);
    }

    //*****************************************************************************
    static function clear_status() {
        cDebug::extra_debug("deleting status");
        $oDB = self::$oDB;
        $oDB->kill(self::INDEXING_STATUS_KEY);
        $oDB->kill(self::INDEXING_LASTSOL_KEY);
    }

    static function get_status() {
        $oDB = self::$oDB;
        $sStatus = $oDB->get(self::INDEXING_STATUS_KEY);
        return $sStatus;
    }
    static function put_status($psStatus) {
        $oDB = self::$oDB;
        $oDB->put(self::INDEXING_STATUS_KEY, $psStatus, true);
    }

    static function get_last_indexed_sol() {
        $oDB = self::$oDB;
        $sLastSol = $oDB->get(self::INDEXING_LASTSOL_KEY);
        return $sLastSol;
    }
    static function put_last_indexed_sol(string $psSol) {
        $oDB = self::$oDB;
        $oDB->put(self::INDEXING_LASTSOL_KEY, $psSol, true);
    }

    //-----------------------------------------------------------
    static function get_sol_last_updated(string $psSol) {
        $oDB = self::$oDB;
        $sKey = self::LAST_SOL_PREFIX . $psSol;
        $sLastUpdated = $oDB->get($sKey);
        return $sLastUpdated;
    }

    static function put_sol_last_updated($psSol, $psDate) {
        $oDB = self::$oDB;
        $sKey = self::LAST_SOL_PREFIX . $psSol;
        $oDB->put($sKey, $psDate, true);
    }

    static function kill_sol_last_updated(string $psSol) {
        if (cCommon::is_string_empty($psSol))
            cDebug::error("sol must be provided");
        $sKey = self::LAST_SOL_PREFIX . $psSol;
        $oDB = self::$oDB;
        $oDB->kill($sKey);
    }
}
cCuriosityManifestIndexStatus::init_db();
