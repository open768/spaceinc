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

    const INDEXING_STATUS_KEY = "indexing:status";
    const INDEXING_LASTSOL_KEY = "indexing:lastsol";
    const STATUS_NOT_STARTED = -1;
    const STATUS_COMPLETE = "complete";

    const COL_MISSION = "M";
    const COL_SOL = "SO";
    const COL_INSTR = "I";
    const COL_PRODUCT = "P";
    const COL_IMAGE_URL = "U";
    const COL_SAMPLE_TYPE = "SA";
    const COL_DATE_ADDED = "DA";

    const SAMPLE_THUMB = "thumbnail";
    const REINDEX_SOLS = 10; //how many sols to reindex (ignore cache)


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
        $sSQL = str_replace(":mission_col", self::COL_MISSION, $sSQL);
        $sSQL = str_replace(":sol_col", self::COL_SOL, $sSQL);
        $sSQL = str_replace(":sample_col", self::COL_SAMPLE_TYPE, $sSQL);
        $sSQL = str_replace(":instr_col", self::COL_INSTR, $sSQL);
        $sSQL = str_replace(":product_col", self::COL_PRODUCT, $sSQL);
        $sSQL = str_replace(":url_col", self::COL_IMAGE_URL, $sSQL);
        $sSQL = str_replace(":date_col", self::COL_DATE_ADDED, $sSQL);
        return $sSQL;
    }

    //*****************************************************************************
    private static function pr_create_table() {
        $oSqLDB = self::$oSQLDB;
        //-------------- check table
        $bTableExists = $oSqLDB->table_exists(self::MANIFEST_TABLE);
        if ($bTableExists) {
            //cDebug::extra_debug("table exists: " . self::MANIFEST_TABLE);
            return;
        } else {
            cDebug::extra_debug("deleting status");
            $oDB = self::$oDB;
            $oDB->kill(self::INDEXING_STATUS_KEY);
            $oDB->kill(self::INDEXING_LASTSOL_KEY);
        }

        //-------------create TABLE
        cDebug::extra_debug("table doesnt exist " . self::MANIFEST_TABLE);
        $sSQL =
            "CREATE TABLE `:table` ( " .
            ":mission_col TEXT not null, :sol_col TEXT not null, :instr_col TEXT not null, :product_col TEXT not null, :url_col TEXT not null, :sample_col TEXT, :date_col INTEGER, " .
            "CONSTRAINT cmanifest UNIQUE (:mission_col, :sol_col, :instr_col, :product_col) " .
            ")";
        $sSQL = self::pr_replace_sql_params($sSQL);
        $oSqLDB->querySQL($sSQL);
        cDebug::extra_debug("table created");

        //-------------create INDEX
        $sSQL = "CREATE INDEX idx_manifest on ':table' ( :mission_col, :sol_col, :instr_col )";
        $sSQL = self::pr_replace_sql_params($sSQL);
        $oSqLDB->querySQL($sSQL);
        cDebug::extra_debug("main index created");

        //-------------create INDEX
        $sSQL = "CREATE INDEX idx_manifest_date on ':table' ( :date_col )";
        $sSQL = self::pr_replace_sql_params($sSQL);
        $oSqLDB->querySQL($sSQL);
        cDebug::extra_debug("secondary index created");
    }

    //*****************************************************************************
    static function indexManifest() {
        cDebug::enter();
        cDebug::on(); //turn off extra debugging

        //----------get status from odb
        $oDB = self::$oDB;
        $sStatus = $oDB->get(self::INDEXING_STATUS_KEY);

        if ($sStatus === self::STATUS_COMPLETE)
            cDebug::error("indexing allready complete");

        //----------get last indexed sol  odb
        $sStatusSol = $oDB->get(self::INDEXING_LASTSOL_KEY);
        if ($sStatusSol == null) $sStatusSol = -1;
        cDebug::write("indexing starting at sol: $sStatusSol");

        //----------get manifest
        cDebug::write("getting sol Manifest");
        $oManifest = cCuriosityManifest::getManifest();

        cDebug::write("processing sol Manifest");
        $aSols = $oManifest->sols;
        ksort($aSols, SORT_NUMERIC);
        $oSqlDB = self::$oSQLDB;

        $aKeys = array_keys($aSols);
        $iKeyCount = count($aKeys);
        $iRow = 0;
        $iReindexFrom = $iKeyCount - self::REINDEX_SOLS;

        //---------------iterate manifest
        foreach ($aSols as $number => $oSol) {
            $sSol = $oSol->sol;
            $iRow++;

            //---------------------check if the row needs reindexing
            $bIgnoreCache = $iRow >= $iReindexFrom;
            if ($bIgnoreCache)
                self::delete_sol_index($sSol);
            elseif ($sStatusSol >= $sSol)
                continue;

            //-------perform the index
            self::index_sol($sSol, $bIgnoreCache);

            //update the status
            $oDB->put(self::INDEXING_LASTSOL_KEY, $sSol, true);
        }
        $sStatusSol = $oDB->put(self::INDEXING_STATUS_KEY, self::STATUS_COMPLETE, true);

        //----------------compress database
        cDebug::write("compresssing database");
        cSqlLiteUtils::vacuum(self::DB_FILENAME);
        cDebug::write("done");
        cDebug::leave();
    }

    //*****************************************************************************
    static function index_sol($psSol, $pbIgnoreCache) {
        $oSqlDB = self::$oSQLDB;
        $oSolData = cCuriosityManifest::getAllSolData($psSol, $pbIgnoreCache);
        $aImages = $oSolData->images;
        if ($aImages === null) {
            cDebug::error("no image data");
        }

        $oSqlDB->begin_transaction(); {
            foreach ($aImages as $sKey => $oImgData)
                self::add_to_index($psSol, $oImgData);
            $oSqlDB->commit();
        }
    }

    //*****************************************************************************
    static function delete_sol_index($psSol) {
        cDebug::extra_debug("deleting Sol $psSol index");
        $sSQL = "DELETE FROM `:table` where :sol_col=:sol";
        $sSQL = self::pr_replace_sql_params($sSQL);

        $oSqlDB = self::$oSQLDB;
        $oStmt = $oSqlDB->prepare($sSQL);
        $oStmt->bindValue(":sol", $psSol);

        $oSqlDB->exec_stmt($oStmt); //handles retries and errors
    }

    //*****************************************************************************
    static function add_to_index($psSol, $poItem) {
        //cDebug::enter();
        //--------------get the data out of the item
        $sSampleType = $poItem->sampleType;
        $sInstr = $poItem->instrument;
        $sProduct = $poItem->itemName;
        $sUrl = $poItem->urlList;
        $sUtc = $poItem->utc; {
            $dUtc = new DateTime($sUtc, new DateTimeZone("UTC"));
            $iUtc = $dUtc->format('U');
        }

        //--------------get the data out of the item
        cDebug::extra_debug("adding to index: $psSol, $sInstr, $sProduct, $sSampleType");
        echo ".";
        $sSQL = "INSERT INTO `:table` (:mission_col, :sol_col, :instr_col, :product_col, :url_col, :sample_col ,:date_col) VALUES (:mission, :sol, :instr, :product, :url, :sample , :d_val)";
        $sSQL = self::pr_replace_sql_params($sSQL);

        //--------------put it into the database
        /** @var cSQLLite $oSqlDB  */
        $oSqlDB = self::$oSQLDB;
        $oStmt = $oSqlDB->prepare($sSQL);
        $oStmt->bindValue(":mission", cSpaceMissions::CURIOSITY);
        $oStmt->bindValue(":sol", $psSol);
        $oStmt->bindValue(":instr", $sInstr);
        $oStmt->bindValue(":product", $sProduct);
        $oStmt->bindValue(":url", $sUrl);
        $oStmt->bindValue(":sample", $sSampleType);
        $oStmt->bindValue(":d_val", $iUtc);


        $oSqlDB->exec_stmt($oStmt); //handles retries and errors

        //cDebug::leave();
    }

    //******************************************************************************************* */
    static function deleteIndex() {
        cDebug::enter();
        //delete everything
        $sSQL = "DELETE from `:table`";
        $sSQL = self::pr_replace_sql_params($sSQL);
        $oSqlDB = self::$oSQLDB;
        $oStmt = $oSqlDB->prepare($sSQL);
        $oSqlDB->exec_stmt($oStmt); //handles retries and errors

        //update the status
        $oDB = self::$oDB;
        $oDB->put(self::INDEXING_STATUS_KEY, self::STATUS_NOT_STARTED, true);

        cDebug::write("done");
        cDebug::leave();
    }

    //******************************************************************************************* */
    /**
     * returns a random image
     * @param string $sIntrumentPattern 
     * @param int $piHowmany 
     * @return array
     */
    static function get_random_images(string $sIntrumentPattern, int $piHowmany) {
        cDebug::enter();

        //----------------prepare statement
        $sSQL = "SELECT :mission_col,:sol_col,:instr_col,:product_col,:url_col FROM `:table` WHERE ( :mission_col=:name AND  :instr_col LIKE :pattern AND :sample_col != 'thumbnail')  ORDER BY RANDOM() LIMIT :howmany";
        $sSQL = self::pr_replace_sql_params($sSQL);
        $sSQL = str_replace(":howmany", $piHowmany, $sSQL);

        $oSqlDB = self::$oSQLDB;
        $oStmt = $oSqlDB->prepare($sSQL);
        $oStmt->bindValue(":name", cSpaceMissions::CURIOSITY);
        $oStmt->bindValue(":pattern", $sIntrumentPattern);
        $sSQL = $oStmt->getSQL(true);
        cDebug::extra_debug("SQL: $sSQL");

        $oResultSet = $oSqlDB->exec_stmt($oStmt); //handles retries and errors

        //----------------fetch results
        $aResults = cSqlLiteUtils::fetch_all($oResultSet);
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
    const FEED_SLEEP = 200; //milliseconds


    //*****************************************************************************
    static function getManifest() {
        $oResult = null;
        cDebug::enter();

        $oCache = new cCachedHttp(); {
            $oCache->CACHE_EXPIRY = self::MANIFEST_CACHE;
            $oResult = $oCache->getCachedJson(self::FEED_URL);
        }
        cDebug::leave();
        return $oResult;
    }

    //*****************************************************************************
    static function getSolJsonUrl($psSol) {
        cDebug::enter();
        //---- get the manifest
        $oManifest = self::getManifest();
        $aSols = $oManifest->sols;
        $iSol = (int) $psSol; //make sure we have an integer
        ksort($aSols, SORT_NUMERIC);

        //----array is not keyed on sol, so we have to find the sol
        $sUrl = null;
        foreach ($aSols as $oSol) {
            if ($oSol->sol === $iSol) {
                $sUrl = $oSol->catalog_url;
                break;
            }
        }
        cDebug::leave();
        return $sUrl;
    }

    //*****************************************************************************
    public static function getAllSolData($psSol, $pbCheckExpiry = true) {
        cDebug::enter();

        $sUrl = self::getSolJsonUrl($psSol);
        if (cCommon::is_string_empty($sUrl)) cDebug::error("empty url for $psSol");

        cDebug::write("Getting all sol data for sol $psSol");

        $oCache = new cCachedHttp(); {
            $oCache->CACHE_EXPIRY = self::SOL_CACHE;
            $bIsCached = $oCache->is_cached($sUrl, $pbCheckExpiry);
            $oResult = $oCache->getCachedJson($sUrl, $pbCheckExpiry);
        }

        if (!$bIsCached) {
            cDebug::write("<p> -- sleeping for " . self::FEED_SLEEP . " ms\n");
            usleep(self::FEED_SLEEP);
        }

        cDebug::leave();
        return $oResult;
    }

    //*****************************************************************************
    public static function clearSolDataCache($psSol) {
        cDebug::enter();

        cDebug::write("clearing sol cache : " . $psSol);
        $sUrl = self::getSolJsonUrl($psSol);
        $oCache = new cCachedHttp();
        $oCache->deleteCachedURL($sUrl);

        cDebug::leave();
    }
}
