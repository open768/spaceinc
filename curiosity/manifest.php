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

//#################################################################################
class cManifestProductData {
    public string $sol;
    public string $instr;
    public string $product;
    public string $image_url;
    public int $utc_date;
    public string $sample_type;
}

class cManifestSolData {
    public string $sol;
    public array  $data = [];
}

//#################################################################################
class cCuriosityManifestIndex {
    const DB_FILENAME = "curiositymanifest.db";
    const MANIFEST_TABLE = "manifest";


    const COL_MISSION = "M";
    const COL_SOL = "SO";
    const COL_INSTR = "I";
    const COL_PRODUCT = "P";
    const COL_IMAGE_URL = "U";
    const COL_SAMPLE_TYPE = "SA";
    const COL_DATE_ADDED = "DA";

    const SAMPLE_THUMB = "thumbnail";


    /**  @var cSQLLite $oSQLDB */
    private static $oSQLDB = null;
    private static $cached_all_data = null;


    //*****************************************************************************
    static function init_db() {
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
    static function get_db() {
        return self::$oSQLDB;
    }

    //*****************************************************************************
    static function replace_sql_params($psSQL) {
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
        if ($bTableExists)
            return;
        else
            cCuriosityManifestIndexStatus::clear_status();

        //-------------create TABLE
        cDebug::extra_debug("table doesnt exist " . self::MANIFEST_TABLE);
        $sSQL =
            "CREATE TABLE `:table` ( " .
            ":mission_col TEXT not null, :sol_col TEXT not null, :instr_col TEXT not null, :product_col TEXT not null, :url_col TEXT not null, :sample_col TEXT, :date_col INTEGER, " .
            "CONSTRAINT cmanifest UNIQUE (:mission_col, :sol_col, :instr_col, :product_col) " .
            ")";
        $sSQL = self::replace_sql_params($sSQL);
        $oSqLDB->querySQL($sSQL);
        cDebug::extra_debug("table created");

        //-------------create INDEX
        $sSQL = "CREATE INDEX idx_manifest on ':table' ( :mission_col, :sol_col, :instr_col )";
        $sSQL = self::replace_sql_params($sSQL);
        $oSqLDB->querySQL($sSQL);
        cDebug::extra_debug("main index created");

        //-------------create INDEX
        $sSQL = "CREATE INDEX idx_manifest_date on ':table' ( :date_col )";
        $sSQL = self::replace_sql_params($sSQL);
        $oSqLDB->querySQL($sSQL);
        cDebug::extra_debug("secondary index created");
    }

    //*****************************************************************************
    static function indexManifest() {
        cDebug::enter();
        cDebug::on(); //turn off extra debugging

        //----------get manifest
        cDebug::write("getting sol Manifest");
        $oManifest = cCuriosityManifest::getManifest();

        //----------get status from odb
        $sStatus = cCuriosityManifestIndexStatus::get_status();

        if ($sStatus === cCuriosityManifestIndexStatus::STATUS_COMPLETE) {
            $sLastIndexedSol = cCuriosityManifestIndexStatus::get_last_indexed_sol();
            $sLatestManifestSol = $oManifest->latest_sol;
            cDebug::write("last indexed sol was: $sLastIndexedSol, latest manifest sol: $sLatestManifestSol");
            if ($sLastIndexedSol >= $sLatestManifestSol)
                cDebug::error("indexing allready complete");
        }

        //----------get last indexed sol  odb
        $sLastSol = cCuriosityManifestIndexStatus::get_last_indexed_sol();
        if ($sLastSol == null) $sLastSol = -1;
        cDebug::write("indexing starting at sol: $sLastSol");

        cDebug::write("processing sol Manifest");
        $aSols = $oManifest->sols;
        ksort($aSols, SORT_NUMERIC);


        //---------------iterate manifest
        foreach ($aSols as $number => $oSol) {
            $sSol = $oSol->sol;
            $bReindex = false;

            //- - - - - - - - -check when SOL was  last updated
            $sStoredLastUpdated = cCuriosityManifestIndexStatus::get_sol_last_updated($sSol);
            $sManifestLastUpdated = $oSol->last_updated;
            //cDebug::write("stored lastindex:$sStoredLastUpdated, manifest date:$sManifestLastUpdated");
            if ($sStoredLastUpdated == null)
                $bReindex = true;
            elseif ($sStoredLastUpdated < $sManifestLastUpdated)
                $bReindex = true;

            //---------------------check if the row needs reindexing
            if (!$bReindex) $bReindex = $sSol > $sLastSol;
            if (!$bReindex) continue;

            //-------perform the index
            self::index_sol($sSol, $sManifestLastUpdated, $bReindex);
        }
        cCuriosityManifestIndexStatus::put_status(cCuriosityManifestIndexStatus::STATUS_COMPLETE);

        //----------------compress database
        cDebug::write("compresssing database");
        cSqlLiteUtils::vacuum(self::DB_FILENAME);
        cDebug::write("done");
        cDebug::leave();
    }

    //*****************************************************************************
    static function index_sol(string $psSol, $sLastUpdatedValue, bool $pbReindex) {
        $oSqlDB = self::$oSQLDB;
        cDebug::extra_debug("indexing sol:$psSol");

        if ($pbReindex) self::delete_sol_index($psSol);

        $bCheckExpiry = !$pbReindex;
        $oSolData = cCuriosityManifest::getSolData($psSol, $bCheckExpiry);

        $aImages = $oSolData->images;
        if ($aImages === null) cDebug::error("no image data");

        $oSqlDB->begin_transaction(); {
            foreach ($aImages as $sKey => $oImgData)
                self::add_to_index($psSol, $oImgData);
            $oSqlDB->commit();
        }


        if (cDebug::is_debugging())
            cPageOutput::scroll_to_bottom();


        //update the status
        cCuriosityManifestIndexStatus::put_last_indexed_sol($psSol);
        cCuriosityManifestIndexStatus::put_sol_last_updated($psSol, $sLastUpdatedValue);
    }

    //*****************************************************************************
    static function delete_sol_index(string $psSol) {
        if (cCommon::is_string_empty($psSol))
            cDebug::error("sol must be provided");

        cDebug::extra_debug("deleting Sol $psSol index");
        $sSQL = "DELETE FROM `:table` where :sol_col=:sol";
        $sSQL = self::replace_sql_params($sSQL);

        $oSqlDB = self::$oSQLDB;
        $oStmt = $oSqlDB->prepare($sSQL);
        $oStmt->bindValue(":sol", $psSol);

        $oSqlDB->exec_stmt($oStmt); //handles retries and errors

        cCuriosityManifestIndexStatus::kill_sol_last_updated($psSol);
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
        $sSQL = self::replace_sql_params($sSQL);

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
        cDebug::write("deleting from sql");
        $sSQL = "DELETE from `:table`";
        $sSQL = self::replace_sql_params($sSQL);
        $oSqlDB = self::$oSQLDB;
        $oStmt = $oSqlDB->prepare($sSQL);
        $oSqlDB->exec_stmt($oStmt); //handles retries and errors

        cDebug::write("vacuuming db");
        cSqlLiteUtils::vacuum(self::DB_FILENAME);

        //update the status
        cDebug::write("updating status");
        cCuriosityManifestIndexStatus::clear_status();

        cDebug::write("done");
        cDebug::leave();
    }

    //******************************************************************************************* */
    static function get_sol_data(string $psSol) {
        cDebug::enter();
        cDebug::write("attempting to get data for $psSol");

        //----------------is it in the index?
        $slastUpdated = cCuriosityManifestIndexStatus::get_sol_last_updated($psSol);
        if ($slastUpdated === null) {
            cDebug::write("sol $psSol is not in the index");
            return null;
        }

        //----------------yes then retrieve it
        cDebug::write("sol $psSol is in the index");

        $oSqlDB = self::$oSQLDB;

        $sSQL = "SELECT :mission_col, :sol_col, :instr_col, :product_col, :url_col, :sample_col, :date_col from `:table` where :sol_col=:sol ORDER BY :sol_col, :instr_col, :product_col";
        $sSQL = self::replace_sql_params($sSQL);
        cDebug::write($sSQL);
        $oStmt = $oSqlDB->prepare($sSQL);
        $oStmt->bindValue(":sol", $psSol);

        $oResultSet = $oSqlDB->exec_stmt($oStmt); //handles retries and errors
        $aSQLData = cSqlLiteUtils::fetch_all($oResultSet);

        //-------------organise the data
        $oOut = new cManifestSolData;
        $oOut->sol = $psSol;
        foreach ($aSQLData as $aItem) {
            $oItem = new cManifestProductData; {
                $oItem->sol = $aItem[self::COL_SOL];
                $oItem->instr = $aItem[self::COL_INSTR];
                $oItem->product = $aItem[self::COL_PRODUCT];
                $oItem->sample_type = $aItem[self::COL_SAMPLE_TYPE];
                $oItem->image_url = $aItem[self::COL_IMAGE_URL];
                $oItem->utc_date = $aItem[self::COL_DATE_ADDED];
                $oOut->data[] = $oItem;
            }
        }


        cDebug::leave();
        return $oOut;
    }
}
cCuriosityManifestIndex::init_db();

class cCuriosityManifestUtils {
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
        $sSQL = cCuriosityManifestIndex::replace_sql_params($sSQL);
        $sSQL = str_replace(":howmany", $piHowmany, $sSQL);

        $oSqlDB = cCuriosityManifestIndex::get_db();
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
            $sMission = $aRow[cCuriosityManifestIndex::COL_MISSION];
            $sSol = $aRow[cCuriosityManifestIndex::COL_SOL];
            $sInstr = $aRow[cCuriosityManifestIndex::COL_INSTR];
            $sProduct = $aRow[cCuriosityManifestIndex::COL_PRODUCT];
            $sUrl = $aRow[cCuriosityManifestIndex::COL_IMAGE_URL];
            $oProduct = new cRoverManifestImage($sMission, $sSol, $sInstr, $sProduct, $sUrl);
            $aOut[] = $oProduct;
        }

        cDebug::leave();
        return $aOut;
    }
}

//###############################################################################
class cCuriosityManifest {
    const MANIFEST_CACHE = 3600;    //1 hour
    const FEED_URL = "https://mars.jpl.nasa.gov/msl-raw-images/image/image_manifest.json";
    const SOL_URL = "https://mars.jpl.nasa.gov/msl-raw-images/image/images_sol";
    const SOL_CACHE = 604800;    //1 week
    const FEED_SLEEP = 200; //milliseconds
    static $cached_manifest = null;


    //*****************************************************************************
    static function getManifest() {
        $oResult = null;
        cDebug::enter();

        $oResult = self::$cached_manifest;
        if ($oResult == null) {
            $oCache = new cCachedHttp(); {
                $oCache->CACHE_EXPIRY = self::MANIFEST_CACHE;
                $oResult = $oCache->getCachedJson(self::FEED_URL);
            }
            self::$cached_manifest = $oResult;
        }
        cDebug::leave();
        return $oResult;
    }

    //*****************************************************************************
    static function getSolEntry(string $psSol) {
        cDebug::enter();
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
        cDebug::leave();
        return $oMatched;
    }

    //*****************************************************************************
    static function getSolJsonUrl($psSol) {
        cDebug::enter();

        $oSol = self::getSolEntry($psSol);
        $sUrl = $oSol->catalog_url;

        cDebug::leave();
        return $sUrl;
    }

    //*****************************************************************************
    public static function getSolData($psSol, $pbCheckExpiry = true) {
        cDebug::enter();

        $sUrl = self::getSolJsonUrl($psSol);
        if (cCommon::is_string_empty($sUrl)) cDebug::error("empty url for $psSol");

        cDebug::extra_debug("Getting all sol data for sol $psSol");

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
