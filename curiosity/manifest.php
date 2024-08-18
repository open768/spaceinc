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
    const MANIFEST_TABLE = "manifest";

    const INDEXING_STATUS = "indexing:status";
    const STATUS_NOT_STARTED = -1;

    const COL_MISSION = "M";
    const COL_SOL = "S";
    const COL_INSTR = "I";
    const COL_PRODUCT = "P";
    const COL_IMAGE_URL = "U";

    /**  @var cObjstoreDB $oDB */
    private static $oDB = null;

    /**  @var cSQLLite $oSQLite */
    private static $oSQLite = null;

    //*****************************************************************************
    static function init_db() {

        if (self::$oDB === null) {
            self::$oDB = new cOBjStoreDB(cSpaceRealms::ROVER_MANIFEST, cSpaceTables::ROVER_MANIFEST);
        }

        //-------------- open SQLlite DB
        /** @var $oDB cSQLLite */
        $oDB = self::$oSQLite;
        if ($oDB == null) {
            cDebug::extra_debug("opening cSqlLite database");
            $oDB = new cSqlLite(self::DB_FILENAME);
            self::$oSQLite = $oDB;
        }

        //-------------- check table
        $bTableExists = $oDB->table_exists(self::MANIFEST_TABLE);
        if ($bTableExists) {
            cDebug::extra_debug("table exists");
            return;
        }

        //-------------create TABLE
        cDebug::extra_debug("table doesnt exist " . self::MANIFEST_TABLE);
        $sSQL =
            "CREATE TABLE ':t' ( " .
            "':m' TEXT not null, ':s' TEXT not null, ':i' TEXT not null, ':p' TEXT not null, ':u' TEXT not null, " .
            "CONSTRAINT cmanifest UNIQUE (:m, :s, :i, :p) " .
            ")";
        $sSQL = str_replace(":t", self::MANIFEST_TABLE, $sSQL);
        $sSQL = str_replace(":m", self::COL_MISSION, $sSQL);
        $sSQL = str_replace(":s", self::COL_SOL, $sSQL);
        $sSQL = str_replace(":i", self::COL_INSTR, $sSQL);
        $sSQL = str_replace(":p", self::COL_PRODUCT, $sSQL);
        $sSQL = str_replace(":u", self::COL_IMAGE_URL, $sSQL);
        $oDB->query($sSQL);
        cDebug::extra_debug("table created");

        //-------------create INDEX
        $sSQL = "CREATE INDEX idx_manifest on ':t' ( :m, :s, :i )";
        $sSQL = str_replace(":t", self::MANIFEST_TABLE, $sSQL);
        $sSQL = str_replace(":m", self::COL_MISSION, $sSQL);
        $sSQL = str_replace(":s", self::COL_SOL, $sSQL);
        $sSQL = str_replace(":i", self::COL_INSTR, $sSQL);
        $oDB->query($sSQL);
        cDebug::extra_debug("index created");
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
        $oManifest = self::getManifest();
        $aSols = $oManifest->sols;
        $oSol = $aSols[$psSol];
        $sUrl = $oSol->catalog_url;
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

        //----------get status from odb
        $oDB = self::$oDB;
        $sStatusSol = $oDB->get(self::INDEXING_STATUS);
        if ($sStatusSol === null) {
            cDebug::write("indexing not begun");
            $sStatusSol = self::STATUS_NOT_STARTED;
        } else
            cDebug::write("indexing status at sol: $sStatusSol");

        //----------get manifest
        $oManifest = self::getManifest();

        //work on manifest
        $aSols = $oManifest->sols;
        ksort($aSols, SORT_NUMERIC);
        $oSqlDB = self::$oSQLite;

        foreach ($aSols as $sSol => $oSol) {
            if ($sStatusSol >= $sSol) continue;
            $sUrl = $oSol->catalog_url;

            cDebug::write("sol:$sSol url:$sUrl");
            $oSolData = self::getAllSolData($sSol);
            $oSqlDB->begin_transaction(); {
                $aImages = $oSolData->images;
                foreach ($aImages as $sKey => $oImgData) {
                    $sInstr = $oImgData->instrument;
                    $sProduct = $oImgData->itemName;
                    $sUrl = $oImgData->urlList;
                    self::add_to_index($sSol, $sInstr, $sProduct, $sUrl);
                }
                $oSqlDB->commit();
            }

            //update the status
            $oDB->put(self::INDEXING_STATUS, $sSol, true);
        }

        cDebug::leave();
    }

    //*****************************************************************************
    static function add_to_index($psSol, $psInstr, $psProduct, $psUrl) {
        cDebug::enter();

        cDebug::write("$psSol, $psInstr, $psProduct, $psUrl");
        $sSQL = "INSERT INTO ':t' (:m, :s, :i, :p, :u ) VALUES (?, ?, ?, ?, ?)";
        $sSQL = str_replace(":t", self::MANIFEST_TABLE, $sSQL);
        $sSQL = str_replace(":m", self::COL_MISSION, $sSQL);
        $sSQL = str_replace(":s", self::COL_SOL, $sSQL);
        $sSQL = str_replace(":i", self::COL_INSTR, $sSQL);
        $sSQL = str_replace(":p", self::COL_PRODUCT, $sSQL);
        $sSQL = str_replace(":u", self::COL_IMAGE_URL, $sSQL);

        $oSqlDB = self::$oSQLite;
        $oStmt = $oSqlDB->prepare($sSQL);
        $oStmt->bindValue(1, cSpaceMissions::CURIOSITY);
        $oStmt->bindValue(2, $psSol);
        $oStmt->bindValue(3, $psInstr);
        $oStmt->bindValue(4, $psProduct);
        $oStmt->bindValue(5, $psUrl);
        $oSqlDB->exec_stmt($oStmt);

        cDebug::leave();
    }
}
cCuriosityManifest::init_db();
