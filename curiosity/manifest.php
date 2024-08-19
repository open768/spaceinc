<?php

/**************************************************************************
Copyright (C) Chicken Katsu 2013 -2024

This code is protected by copyright under the terms of the 
Creative Commons Attribution-NonCommercial-NoDerivatives 4.0 International License
http://creativecommons.org/licenses/by-nc-nd/4.0/legalcode

For licenses that allow for commercial use please contact cluck@chickenkatsu.co.uk
// USE AT YOUR OWN RISK - NO GUARANTEES OR ANY FORM ARE EITHER EXPRESSED OR IMPLIED
 **************************************************************************/

require_once "$spaceInc/missions/rover.php";

//#################################################################################
class cCuriosityManifestIndex {
    const DB_FILENAME = "curiositymanifest.db";
    const MANIFEST_TABLE = "manifest";

    const FEED_SLEEP = 1200; //milliseconds
    const INDEXING_STATUS = "indexing:status";
    const STATUS_NOT_STARTED = -1;

    const COL_MISSION = "M";
    const COL_SOL = "S";
    const COL_INSTR = "I";
    const COL_PRODUCT = "P";
    const COL_IMAGE_URL = "U";


    /**  @var cObjstoreDB $oDB */
    private static $oDB = null;
    /**  @var cSQLLite $oSQLDB */
    private static $oSQLDB = null;


    //*****************************************************************************
    static function init_db() {

        if (self::$oDB === null) {
            self::$oDB = new cOBjStoreDB(cSpaceRealms::ROVER_MANIFEST, cSpaceTables::ROVER_MANIFEST);
        }

        //-------------- open SQLlite DB
        /** @var cSQLLite $oSqLDB  */
        $oSqLDB = self::$oSQLDB;
        if ($oSqLDB == null) {
            cDebug::extra_debug("opening cSqlLite database");
            $oSqLDB = new cSqlLite(self::DB_FILENAME);
            self::$oSQLDB = $oSqLDB;
        }
        self::pr_create_table();
    }

    //*****************************************************************************
    private static function pr_replace_sql_params($psSQL) {
        $sSQL = str_replace(":table", self::MANIFEST_TABLE, $psSQL);
        $sSQL = str_replace(":m_col", self::COL_MISSION, $sSQL);
        $sSQL = str_replace(":s_col", self::COL_SOL, $sSQL);
        $sSQL = str_replace(":i_col", self::COL_INSTR, $sSQL);
        $sSQL = str_replace(":p_col", self::COL_PRODUCT, $sSQL);
        $sSQL = str_replace(":u_col", self::COL_IMAGE_URL, $sSQL);
        return $sSQL;
    }

    //*****************************************************************************
    private static function pr_create_table() {
        $oSqLDB = self::$oSQLDB;
        //-------------- check table
        $bTableExists = $oSqLDB->table_exists(self::MANIFEST_TABLE);
        if ($bTableExists) {
            cDebug::extra_debug("table exists: " . self::MANIFEST_TABLE);
            return;
        }

        //-------------create TABLE
        cDebug::extra_debug("table doesnt exist " . self::MANIFEST_TABLE);
        $sSQL =
            "CREATE TABLE `:table` ( " .
            ":m_col TEXT not null, :s_col TEXT not null, :i_col TEXT not null, :p_col TEXT not null, :u_col TEXT not null, " .
            "CONSTRAINT cmanifest UNIQUE (:m_col, :s_col, :i_col, :p_col) " .
            ")";
        $sSQL = self::pr_replace_sql_params($sSQL);
        $oSqLDB->query($sSQL);
        cDebug::extra_debug("table created");

        //-------------create INDEX
        $sSQL = "CREATE INDEX idx_manifest on ':table' ( :m_col, :s_col, :i_col )";
        $sSQL = self::pr_replace_sql_params($sSQL);
        $oSqLDB->query($sSQL);
        cDebug::extra_debug("index created");
    }

    //*****************************************************************************
    static function indexManifest() {
        cDebug::enter();
        cDebug::on(); //turn off extra debugging

        //----------get status from odb
        $oDB = self::$oDB;
        $sStatusSol = $oDB->get(self::INDEXING_STATUS);
        if ($sStatusSol === null) {
            cDebug::write("indexing not begun");
            $sStatusSol = self::STATUS_NOT_STARTED;
        } else
            cDebug::write("indexing status at sol: $sStatusSol");

        //----------get manifest
        cDebug::write("getting sol Manifest");
        $oManifest = cCuriosityManifest::getManifest();

        //----work on manifest
        cDebug::write("processing sol Manifest");
        $aSols = $oManifest->sols;
        ksort($aSols, SORT_NUMERIC);
        $oSqlDB = self::$oSQLDB;

        foreach ($aSols as $sSol => $oSol) {
            if ($sStatusSol >= $sSol) continue;
            $sUrl = $oSol->catalog_url;

            $oSolData = cCuriosityManifest::getAllSolData($sSol);
            $oSqlDB->begin_transaction(); {
                $aImages = $oSolData->images;
                foreach ($aImages as $sKey => $oImgData) {
                    $sInstr = $oImgData->instrument;
                    $sProduct = $oImgData->itemName;
                    $sUrl = $oImgData->urlList;
                    self::add_to_index($sSol, $sInstr, $sProduct, $sUrl);
                }
                $oSqlDB->commit();
                cDebug::write("<p> -- sleeping for " . self::FEED_SLEEP . " ms\n");
                usleep(self::FEED_SLEEP);
            }

            //update the status
            $oDB->put(self::INDEXING_STATUS, $sSol, true);
        }
        cDebug::write("done");
        cDebug::leave();
    }

    //*****************************************************************************
    static function add_to_index($psSol, $psInstr, $psProduct, $psUrl) {
        //cDebug::enter();

        cDebug::extra_debug("adding to index: $psSol, $psInstr, $psProduct");
        echo ".";

        $sSQL = "INSERT INTO `:table` (:m_col, :s_col, :i_col, :p_col, :u_col ) VALUES (:mission, :sol, :instr, :product, :url)";
        $sSQL = self::pr_replace_sql_params($sSQL);

        /** @var cSQLLite $oSqlDB  */
        $oSqlDB = self::$oSQLDB;
        $oStmt = $oSqlDB->prepare($sSQL);
        $oStmt->bindValue(":mission", cSpaceMissions::CURIOSITY);
        $oStmt->bindValue(":sol", $psSol);
        $oStmt->bindValue(":instr", $psInstr);
        $oStmt->bindValue(":product", $psProduct);
        $oStmt->bindValue(":url", $psUrl);

        $oSqlDB->exec_stmt($oStmt); //handles retries and errors

        //cDebug::leave();
    }

    //******************************************************************************************* */
    /**
     * returns a random image
     * @param string $sIntrumentPattern 
     * @param int $piHowmany 
     * @return array
     */
    static function random_images(string $sIntrumentPattern, int $piHowmany) {
        cDebug::enter();

        //----------------prepare statement
        $sSQL = "SELECT :m_col,:s_col,:i_col,:p_col,:u_col FROM `:table` WHERE ( :m_col=:name AND  :i_col LIKE :pattern )  ORDER BY RANDOM() LIMIT :howmany";
        $sSQL = str_replace(":table", self::MANIFEST_TABLE, $sSQL);
        $sSQL = str_replace(":m_col", self::COL_MISSION, $sSQL);
        $sSQL = str_replace(":s_col", self::COL_SOL, $sSQL);
        $sSQL = str_replace(":i_col", self::COL_INSTR, $sSQL);
        $sSQL = str_replace(":p_col", self::COL_PRODUCT, $sSQL);
        $sSQL = str_replace(":u_col", self::COL_IMAGE_URL, $sSQL);
        $sSQL = str_replace(":howmany", $piHowmany, $sSQL);

        $oSqlDB = self::$oSQLDB;
        $oStmt = $oSqlDB->prepare($sSQL);
        $oStmt->bindValue(":name", cSpaceMissions::CURIOSITY);
        $oStmt->bindValue(":pattern", $sIntrumentPattern);
        $sSQL = $oStmt->getSQL(true);
        cDebug::extra_debug("SQL: $sSQL");

        $oResultSet = $oSqlDB->exec_stmt($oStmt); //handles retries and errors

        //----------------fetch results
        $aResults = $oSqlDB->fetch_all($oResultSet);
        $aOut = [];
        foreach ($aResults as $aRow) {
            $sMission = $aRow[self::COL_MISSION];
            $sSol = $aRow[self::COL_SOL];
            $sInstr = $aRow[self::COL_INSTR];
            $sProduct = $aRow[self::COL_PRODUCT];
            $sUrl = $aRow[self::COL_IMAGE_URL];
            $oProduct = new cRoverManifestImage($sMission, $sSol, $sInstr, $sProduct, $sUrl);
            $aOut[] = $oProduct;
        }

        cDebug::leave();
        return $aOut;
    }
}
cCuriosityManifestIndex::init_db();

//###############################################################################
class cCuriosityManifest {
    const MANIFEST_CACHE = 3600;    //1 hour
    const FEED_URL = "https://mars.jpl.nasa.gov/msl-raw-images/image/image_manifest.json";
    const SOL_URL = "https://mars.jpl.nasa.gov/msl-raw-images/image/images_sol";
    const SOL_CACHE = 604800;    //1 week


    //*****************************************************************************
    static function getManifest() {
        $oResult = null;
        cDebug::enter();

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

        cDebug::write("Getting all sol data for sol $psSol");
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
}
